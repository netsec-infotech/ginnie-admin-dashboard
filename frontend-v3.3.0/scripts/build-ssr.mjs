import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import dotenv from "dotenv";

// Match Next's production precedence; pass the resolved values to codegen too.
process.env.NODE_ENV = "production";
for (const file of [".env.production.local", ".env.local", ".env.production", ".env"]) {
  if (existsSync(file)) dotenv.config({ path: file, quiet: true });
}
process.env.NEXT_PUBLIC_SSR = "true";

for (const name of ["NEXT_PUBLIC_ADMIN_PANEL_URL", "NEXT_PUBLIC_SITE_URL"]) {
  try {
    const url = new URL(process.env[name]);
    if (!["http:", "https:"].includes(url.protocol)) throw new Error();
  } catch {
    console.error(`${name} must be an absolute HTTP(S) URL.`);
    process.exit(1);
  }
}

const steps = [
  ["node_modules/eslint/bin/eslint.js", "."],
  ["scripts/update-manifest.mjs"],
  ["scripts/update-robots.mjs"],
  ["scripts/generate-sitemap.mjs"],
  ["node_modules/next/dist/bin/next", "build", "--webpack"],
];

for (const [script, ...args] of steps) {
  const result = spawnSync(process.execPath, [resolve(script), ...args], {
    stdio: "inherit",
    env: process.env,
  });
  if (result.error) console.error(result.error.message);
  if (result.status !== 0) process.exit(result.status || 1);
}
