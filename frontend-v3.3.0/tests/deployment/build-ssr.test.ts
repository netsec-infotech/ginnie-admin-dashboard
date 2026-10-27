import { afterEach, describe, expect, it } from "vitest";
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";
import { spawnSync } from "node:child_process";

const runner = resolve("scripts/build-ssr.mjs");
const defaultRunner = resolve("scripts/build.mjs");
const fixtures: string[] = [];
const scripts = [
  "node_modules/eslint/bin/eslint.js",
  "scripts/update-manifest.mjs",
  "scripts/update-robots.mjs",
  "scripts/generate-sitemap.mjs",
  "node_modules/next/dist/bin/next",
  "scripts/generate-static-data-routes.mjs",
  "create-htaccess.js",
  "scripts/audit-seo.mjs",
];

afterEach(() => {
  for (const fixture of fixtures.splice(0)) rmSync(fixture, { recursive: true, force: true });
});

function fixture(failingStep = -1) {
  const root = mkdtempSync(join(tmpdir(), "hyperlocal-ssr-test-"));
  fixtures.push(root);
  scripts.forEach((script, index) => {
    const file = join(root, script);
    mkdirSync(dirname(file), { recursive: true });
    const loadFs = script.endsWith(".mjs") ? "import fs from 'node:fs';" : "const fs = require('node:fs');";
    writeFileSync(file, `${loadFs} fs.appendFileSync('steps.jsonl', JSON.stringify({step: ${index}, ssr: process.env.NEXT_PUBLIC_SSR, site: process.env.NEXT_PUBLIC_SITE_URL, args: process.argv.slice(2)}) + '\\n'); process.exit(${index === failingStep ? 7 : 0});`);
  });
  writeFileSync(join(root, ".env"), "NEXT_PUBLIC_SSR=false\nNEXT_PUBLIC_ADMIN_PANEL_URL=https://api.example.com\nNEXT_PUBLIC_SITE_URL=https://fallback.example.com\n");
  writeFileSync(join(root, ".env.production.local"), "NEXT_PUBLIC_SITE_URL=https://production.example.com\n");
  return root;
}

function run(root: string, overrides: Partial<NodeJS.ProcessEnv> = {}, command = runner) {
  const env = { ...process.env };
  delete env.NEXT_PUBLIC_SITE_URL;
  delete env.NEXT_PUBLIC_ADMIN_PANEL_URL;
  delete env.NEXT_PUBLIC_SSR;
  return spawnSync(process.execPath, [command], {
    cwd: root, env: { ...env, ...overrides }, encoding: "utf8",
  });
}

describe("SSR build orchestration", () => {
  it.each(["false", "true"])("preserves mode %s through the default build and post-build steps", (mode) => {
    const root = fixture();
    expect(run(root, { NEXT_PUBLIC_SSR: mode }, defaultRunner).status).toBe(0);
    const steps = readFileSync(join(root, "steps.jsonl"), "utf8").trim().split("\n").map((line) => JSON.parse(line));
    expect(steps).toHaveLength(8);
    expect(steps.every((step) => step.ssr === mode && step.site === "https://production.example.com")).toBe(true);
    expect(steps[0].args).toEqual(["."]);
    expect(steps[4].args).toEqual(["build", "--webpack"]);
  });
  it("stops the default pipeline before export when code generation fails", () => {
    const root = fixture(2);
    expect(run(root, {}, defaultRunner).status).toBe(7);
    const steps = readFileSync(join(root, "steps.jsonl"), "utf8").trim().split("\n").map((line) => JSON.parse(line));
    expect(steps.map((step) => step.step)).toEqual([0, 1, 2]);
  });
  it("shares production-local settings with all steps and forces SSR", () => {
    const root = fixture();
    expect(run(root).status).toBe(0);
    const steps = readFileSync(join(root, "steps.jsonl"), "utf8").trim().split("\n").map((line) => JSON.parse(line));
    expect(steps).toHaveLength(5);
    expect(steps.every((step) => step.ssr === "true" && step.site === "https://production.example.com")).toBe(true);
    expect(steps[0].args).toEqual(["."]);
    expect(steps[4].args).toEqual(["build", "--webpack"]);
  });

  it("preserves shell overrides and stops before Next when codegen fails", () => {
    const root = fixture(2);
    expect(run(root, { NEXT_PUBLIC_SITE_URL: "https://shell.example.com" }).status).toBe(7);
    const steps = readFileSync(join(root, "steps.jsonl"), "utf8").trim().split("\n").map((line) => JSON.parse(line));
    expect(steps.map((step) => step.step)).toEqual([0, 1, 2]);
    expect(steps.every((step) => step.site === "https://shell.example.com")).toBe(true);
  });

  it("rejects invalid deployment URLs before running any build command", () => {
    const root = fixture();
    const result = run(root, { NEXT_PUBLIC_SITE_URL: "not-a-url" });
    expect(result.status).toBe(1);
    expect(result.stderr).toContain("NEXT_PUBLIC_SITE_URL must be an absolute HTTP(S) URL");
  });
});
