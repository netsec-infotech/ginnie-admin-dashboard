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
  root = mkdtempSync(join(tmpdir(), "hyperlocal-category-rewrites-"));
  mkdirSync(join(root, "out"));
  generateHtaccess(root);
  const htaccess = readFileSync(join(root, "out/.htaccess"), "utf8");
  const lines = htaccess.split("\n").map((line) => line.trim());
  rules = [];
  lines.forEach((line, index) => {
    const match = line.match(/^RewriteRule (\S+) (\S+) /);
    if (!match || !match[2].includes("category-fallback")) return;
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

describe("category export fallbacks", () => {
  it.each([
    "categories/vegetables-fruits-1",
    "categories/vegetables-fruits-1/",
    "categories/new-category",
    "categories/new-category/",
  ])("serves the category shell for %s", (route) => {
    expect(rewrite(route)).toBe("category-fallback/index.html");
  });

  it.each([
    "_next/data/build-id/categories/vegetables-fruits-1.json",
    "_next/data/build-id/categories/new-category.json",
  ])("serves the matching build's fallback data for %s", (route) => {
    expect(rewrite(route)).toBe("_next/data/build-id/category-fallback.json");
  });

  it.each([
    "brands/amul",
    "categories/new/extra",
    "_next/data/build-id/categories/newXjson",
    "_next/data/build-id/categories/new.json/extra",
  ])("does not apply a category fallback to %s", (route) => {
    expect(rewrite(route)).toBeNull();
  });
});

