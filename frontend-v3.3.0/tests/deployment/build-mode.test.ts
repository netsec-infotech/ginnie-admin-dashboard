// @vitest-environment node
import { afterEach, describe, expect, it } from "vitest";
import { mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { spawnSync } from "node:child_process";
import { isStaticExport } from "../../scripts/build-mode.mjs";

const directories: string[] = [];
const fixture = () => {
  const root = mkdtempSync(join(tmpdir(), "hyperlocal-build-mode-"));
  directories.push(root);
  return root;
};
afterEach(() => {
  for (const root of directories.splice(0)) rmSync(root, { recursive: true, force: true });
});

describe("static post-build steps", () => {
  it("defaults to static export and reads SSR from .env", () => {
    const root = fixture();
    expect(isStaticExport(root, {})).toBe(true);
    writeFileSync(join(root, ".env"), "NEXT_PUBLIC_SSR=true\n");
    expect(isStaticExport(root, {})).toBe(false);
  });
  it("respects production overrides and shell variables", () => {
    const root = fixture();
    writeFileSync(join(root, ".env"), "NEXT_PUBLIC_SSR=false\n");
    writeFileSync(join(root, ".env.production"), "NEXT_PUBLIC_SSR=true\n");
    writeFileSync(join(root, ".env.local"), "NEXT_PUBLIC_SSR=false\n");
    expect(isStaticExport(root, {})).toBe(true);
    writeFileSync(join(root, ".env.production.local"), "NEXT_PUBLIC_SSR=true\n");
    expect(isStaticExport(root, {})).toBe(false);
    expect(isStaticExport(root, { NEXT_PUBLIC_SSR: "false" })).toBe(true);
  });
  it.each(["create-htaccess.js", "scripts/audit-seo.mjs"])(
    "skips %s in SSR mode without requiring static output", (script) => {
      const result = spawnSync(process.execPath, [resolve(script)], {
        cwd: fixture(), env: { ...process.env, NEXT_PUBLIC_SSR: "true" }, encoding: "utf8",
      });
      expect(result.status, result.stderr).toBe(0);
      expect(result.stdout).toContain("SSR build: skipping");
    },
  );
});
