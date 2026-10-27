// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mkdtempSync, mkdirSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { generateHtaccess } from "../../create-htaccess.js";

let root: string;
let rules: Array<{ pattern: RegExp; destination: string }>;

beforeEach(() => {
  vi.stubEnv("NEXT_PUBLIC_SSR", "false");
  root = mkdtempSync(join(tmpdir(), "hyperlocal-store-rewrites-"));
  mkdirSync(join(root, "out"));
  generateHtaccess(root);
  const lines = readFileSync(join(root, "out/.htaccess"), "utf8")
    .split("\n").map((line) => line.trim());
  rules = [];
  lines.forEach((line, index) => {
    const match = line.match(/^RewriteRule (\S+) (\S+) /);
    if (!match || !match[2].includes("store-fallback")) return;
    const conditions = lines.slice(Math.max(0, index - 2), index).join("\n");
    expect(conditions).toContain("RewriteCond %{REQUEST_FILENAME} !-f");
    if (!match[1].includes("_next/data")) {
      expect(conditions).toContain("RewriteCond %{REQUEST_FILENAME} !-d");
    }
    rules.push({ pattern: new RegExp(match[1]), destination: match[2] });
  });
});

afterEach(() => {
  vi.unstubAllEnvs();
  rmSync(root, { recursive: true, force: true });
});

const rewrite = (route: string) => {
  const rule = rules.find(({ pattern }) => pattern.test(route));
  return rule ? route.replace(rule.pattern, rule.destination) : null;
};

describe("store export fallback", () => {
  it.each([
    "stores/grover-departmental-store",
    "stores/grover-departmental-store/",
  ])("serves the store shell for %s", (route) => {
    expect(rewrite(route)).toBe("store-fallback/index.html");
  });

  it("serves the store shell data during client navigation", () => {
    expect(rewrite("_next/data/build-id/stores/grover-departmental-store.json"))
      .toBe("_next/data/build-id/store-fallback.json");
  });

  it.each([
    "stores/",
    "stores/grover/extra",
    "_next/data/build-id/stores/groverXjson",
  ])("does not catch another route: %s", (route) => {
    expect(rewrite(route)).toBeNull();
  });
});
