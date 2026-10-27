// @vitest-environment node
import { afterEach, expect, it, vi } from "vitest";
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { generateHtaccess } from "../../create-htaccess.js";

let fixture: string | undefined;
afterEach(() => {
  vi.unstubAllEnvs();
  if (fixture?.startsWith(join(tmpdir(), "hyperlocal-shared-cart-"))) rmSync(fixture, { recursive: true, force: true });
  fixture = undefined;
});

it("serves future share codes from the exported shell without a crawler runtime", () => {
  vi.stubEnv("NEXT_PUBLIC_SSR", "false");
  fixture = mkdtempSync(join(tmpdir(), "hyperlocal-shared-cart-"));
  mkdirSync(join(fixture, "out"));
  writeFileSync(join(fixture, "out/cart-shared.php"), "old handler");
  generateHtaccess(fixture);

  const rules = readFileSync(join(fixture, "out/.htaccess"), "utf8");
  expect(rules).toContain("RewriteRule ^cart/shared/([A-Za-z0-9_-]+)/?$ cart/shared/__shared-cart__/index.html [L]");
  expect(rules).toContain("RewriteRule ^_next/data/([^/]+)/cart/shared/([A-Za-z0-9_-]+)\\.json$ _next/data/$1/cart/shared/__shared-cart__.json [L]");
  expect(rules).toContain("RewriteRule ^cart-shared\\.php$ - [F,L]");
  expect(rules).not.toContain("HTTP_USER_AGENT");
  expect(rules).not.toContain("[P,L]");
  expect(existsSync(join(fixture, "out/cart-shared.php"))).toBe(false);
});
