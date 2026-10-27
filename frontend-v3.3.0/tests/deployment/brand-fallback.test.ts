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
  root = mkdtempSync(join(tmpdir(), "hyperlocal-brand-rewrites-"));
  mkdirSync(join(root, "out"));
  generateHtaccess(root);
  const htaccess = readFileSync(join(root, "out/.htaccess"), "utf8");
  const lines = htaccess.split("\n").map((line) => line.trim());
  rules = [];
  lines.forEach((line, index) => {
    const match = line.match(/^RewriteRule (\S+) (\S+) /);
    if (!match || !match[2].includes("brand-fallback")) return;
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

describe("new brand export fallbacks", () => {
  it.each(["brands/amul", "brands/amul/", "brands/new-brand", "brands/new-brand/"])(
    "serves the brand shell for %s",
    (route) => {
      expect(rewrite(route)).toBe("brand-fallback/index.html");
    },
  );

  it.each([
    "_next/data/build-id/brands/amul.json",
    "_next/data/build-id/brands/new-brand.json",
  ])("serves the matching build's fallback data for %s", (route) => {
    expect(rewrite(route)).toBe("_next/data/build-id/brand-fallback.json");
  });

  it.each([
    "categories/grocery",
    "brands/new/extra",
    "_next/data/build-id/brands/newXjson",
    "_next/data/build-id/brands/new.json/extra",
  ])("does not apply a brand fallback to %s", (route) => {
    expect(rewrite(route)).toBeNull();
  });
});

