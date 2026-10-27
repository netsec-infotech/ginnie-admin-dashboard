import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import dotenv from "dotenv";

/**
 * Match production env precedence without depending on Next's child process.
 * @param {string} root
 * @param {Record<string, string | undefined>} environment
 */
export function isStaticExport(root, environment = process.env) {
  const values = { ...environment };
  for (const file of [".env.production.local", ".env.local", ".env.production", ".env"]) {
    const filename = join(root, file);
    if (!existsSync(filename)) continue;
    for (const [key, value] of Object.entries(dotenv.parse(readFileSync(filename)))) {
      if (values[key] === undefined) values[key] = value;
    }
  }
  return values.NEXT_PUBLIC_SSR !== "true";
}
