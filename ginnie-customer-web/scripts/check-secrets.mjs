#!/usr/bin/env node
/**
 * Pre-commit secret guard.
 *
 * Refuses a commit that would put deploy credentials into a tracked file.
 *
 * It inspects the **staged** blob of each file (`git show :path`), not the copy
 * on disk. Those differ whenever something was staged and then edited, and it
 * is the staged content that becomes the commit — checking the working tree
 * would miss exactly the case that matters.
 *
 * Blocks rather than rewrites. Silently editing files mid-commit means the
 * thing you reviewed is not the thing that landed, and a scrubbed-but-still-
 * present secret reads as safe when it is not. The fix is always the same and
 * is printed with the failure: move the value to `.env.local`.
 *
 * Escape hatch: `git commit --no-verify` (use it knowingly).
 */
import { execFileSync } from "node:child_process";

const git = (args) =>
  execFileSync("git", args, { encoding: "utf8", maxBuffer: 20 * 1024 * 1024 });

/** Files staged for this commit, excluding deletions (nothing to scan). */
const stagedFiles = () =>
  git(["diff", "--cached", "--name-only", "--diff-filter=ACMR"])
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean);

/** The staged content of a path — what is actually about to be committed. */
const stagedContent = (file) => {
  try {
    return git(["show", `:${file}`]);
  } catch {
    // Binary or unreadable: nothing text-based to match against.
    return "";
  }
};

/**
 * Each rule reports a credential written as a literal.
 *
 * Deliberately narrow. A guard that cries wolf gets bypassed with --no-verify
 * out of habit, and then it protects nothing — so these match assignments with
 * a non-empty value, not merely the presence of the word "password".
 */
const RULES = [
  {
    id: "ftp-config-literal",
    // `host: "1.2.3.4"` / `password: "hunter2"` — empty strings are fine.
    pattern: /\b(host|user|username|password)\s*:\s*["'`][^"'`]+["'`]/gi,
    appliesTo: (file) => /(^|\/)ftp\.js$/.test(file),
    hint: "FTP credential written as a literal in ftp.js",
  },
  {
    id: "ftp-env-assignment",
    // Catches FTP_PASSWORD=… landing in any tracked file, `.env` included.
    pattern: /^\s*FTP_(HOST|USER|PASSWORD)\s*=\s*\S+/gim,
    appliesTo: (file) => !file.endsWith(".env.local"),
    hint: "FTP credential assigned in a tracked file",
  },
];

const findings = [];

for (const file of stagedFiles()) {
  const rules = RULES.filter((rule) => rule.appliesTo(file));
  if (!rules.length) continue;

  const content = stagedContent(file);
  if (!content) continue;
  const lines = content.split("\n");

  for (const rule of rules) {
    lines.forEach((line, index) => {
      // Fresh lastIndex per line: /g regexes are stateful across .test() calls.
      rule.pattern.lastIndex = 0;
      if (rule.pattern.test(line)) {
        findings.push({ file, line: index + 1, hint: rule.hint });
      }
    });
  }
}

if (!findings.length) process.exit(0);

const red = (s) => `\x1b[31m${s}\x1b[0m`;
const yellow = (s) => `\x1b[33m${s}\x1b[0m`;

console.error(red("\n✖ Commit blocked — credentials found in staged files\n"));
for (const { file, line, hint } of findings) {
  console.error(`  ${yellow(`${file}:${line}`)}  ${hint}`);
}
console.error(
  [
    "",
    "  Move the value to " + yellow(".env.local") + " (gitignored) and read it",
    "  via process.env — see ftp.js for the pattern.",
    "",
    `  Bypass (only if you are certain): ${yellow("git commit --no-verify")}`,
    "",
  ].join("\n"),
);

process.exit(1);
