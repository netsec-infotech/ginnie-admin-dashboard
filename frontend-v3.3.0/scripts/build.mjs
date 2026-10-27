import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import dotenv from "dotenv";

// Code generation and Next must use the same production environment.
process.env.NODE_ENV = "production";
for (const file of [
  ".env.production.local",
  ".env.local",
  ".env.production",
  ".env",
]) {
  if (existsSync(file)) dotenv.config({ path: file, quiet: true });
}
process.env.NEXT_PUBLIC_SSR ??= "false";

const steps = [
  ["node_modules/eslint/bin/eslint.js", "."],
  ["scripts/update-manifest.mjs"],
  ["scripts/update-robots.mjs"],
  ["scripts/generate-sitemap.mjs"],
  // next-pwa installs a webpack plugin; Turbopack does not generate its worker.
  ["node_modules/next/dist/bin/next", "build", "--webpack"],
  ["scripts/generate-static-data-routes.mjs"],
  ["create-htaccess.js"],
  ["scripts/audit-seo.mjs"],
];

for (const [script, ...args] of steps) {
  const result = spawnSync(process.execPath, [resolve(script), ...args], {
    stdio: "inherit",
    env: process.env,
  });
  if (result.error) console.error(result.error.message);
  if (result.status !== 0) process.exit(result.status || 1);
}
