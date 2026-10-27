import { createHash } from "node:crypto";
import { mkdirSync, writeFileSync } from "node:fs";
import { join } from "node:path";

// Editor images should be fetched once as assets, not repeated in HTML and JSON.
export function externalizeBlogMedia(value, publicDirectory) {
  if (Array.isArray(value))
    return value.map((item) => externalizeBlogMedia(item, publicDirectory));
  if (!value || typeof value !== "object") return value;
  return Object.fromEntries(
    Object.entries(value).map(([key, field]) => [
      key,
      key === "content" && typeof field === "string"
        ? field.replace(
            /(<img\b[^>]*?\bsrc\s*=\s*)(["'])data:image\/(png|jpeg|gif|webp);base64,([a-z\d+/]+={0,2})\2/gi,
            (_match, prefix, quote, format, data) => {
              const bytes = Buffer.from(data.replace(/\s/g, ""), "base64");
              const extension = format.toLowerCase();
              const filename = `${createHash("sha256").update(bytes).digest("hex")}.${extension}`;
              const directory = join(publicDirectory, "blog-media");
              mkdirSync(directory, { recursive: true });
              writeFileSync(join(directory, filename), bytes);
              return `${prefix}${quote}/blog-media/${filename}${quote}`;
            },
          )
        : externalizeBlogMedia(field, publicDirectory),
    ]),
  );
}
