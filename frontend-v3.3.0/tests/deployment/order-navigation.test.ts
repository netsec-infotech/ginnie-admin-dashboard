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
  root = mkdtempSync(join(tmpdir(), "hyperlocal-order-navigation-"));
  mkdirSync(join(root, "out"));
  generateHtaccess(root);

  rules = readFileSync(join(root, "out/.htaccess"), "utf8")
    .split("\n")
    .map((line) => line.trim())
    .flatMap((line) => {
      const match = line.match(/^RewriteRule (\S+) (\S+) /);
      return match && match[2].includes("my-account/orders/[slug]")
        ? [{ pattern: new RegExp(match[1]), destination: match[2] }]
        : [];
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

describe("order detail export navigation", () => {
  it("serves the dynamic order page shell", () => {
    expect(rewrite("my-account/orders/order-123")).toBe(
      "my-account/orders/[slug]/index.html",
    );
  });

  it("serves the matching build's order data shell", () => {
    expect(
      rewrite("_next/data/build-id/my-account/orders/order-123.json"),
    ).toBe("_next/data/build-id/my-account/orders/[slug].json");
  });

  it("does not match unrelated or malformed routes", () => {
    expect(rewrite("my-account/orders/order-123/items")).toBeNull();
    expect(
      rewrite("_next/data/build-id/my-account/orders/order-123Xjson"),
    ).toBeNull();
  });
});
