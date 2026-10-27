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
  root = mkdtempSync(join(tmpdir(), "hyperlocal-blog-rewrites-"));
  mkdirSync(join(root, "out"));
  generateHtaccess(root);
  const lines = readFileSync(join(root, "out/.htaccess"), "utf8")
    .split("\n")
    .map((line) => line.trim());
  rules = [];
  lines.forEach((line, index) => {
    const match = line.match(/^RewriteRule (\S+) (\S+) /);
    if (!match || !match[2].includes("blog-fallback")) return;
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

// This verifies rewrite patterns and preservation conditions without running Apache.
const rewrite = (route: string) => {
  const rule = rules.find(({ pattern }) => pattern.test(route));
  return rule ? route.replace(rule.pattern, rule.destination) : null;
};

describe("new article export fallback", () => {
  it.each(["blogs/new-article", "blogs/new-article/"])(
    "serves the live article shell for %s",
    (route) => {
      expect(rewrite(route)).toBe("blog-fallback/index.html");
    },
  );

  it("serves the corresponding build's article shell during Next client navigation", () => {
    expect(rewrite("_next/data/build-id/blogs/new-article.json")).toBe(
      "_next/data/build-id/blog-fallback.json",
    );
  });

  it.each([
    "blogs/",
    "blogs/new/extra",
    "products/new",
    "_next/data/build-id/blogs/newXjson",
    "_next/data/build-id/blogs/new.json/extra",
  ])("does not rewrite another route: %s", (route) => {
    expect(rewrite(route)).toBeNull();
  });
});
