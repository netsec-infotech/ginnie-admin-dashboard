// @vitest-environment node
import { afterEach, beforeEach, expect, it } from "vitest";
import { mkdtempSync, readFileSync, readdirSync, rmSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { externalizeBlogMedia } from "../../scripts/blog-media.mjs";
let root: string;
beforeEach(() => {
  root = mkdtempSync(join(tmpdir(), "hyperlocal-blog-media-"));
});
afterEach(() => {
  rmSync(root, { recursive: true, force: true });
});

it("preserves embedded raster images as reusable assets without bloating page props", () => {
  const bytes = Buffer.from("a large image repeated in the page HTML and JSON");
  const content = `<p>Article</p><img alt="Food" src="data:image/png;base64,${bytes.toString("base64")}">`;
  const source = { content, related_blogs: [{ content }] };
  const result = externalizeBlogMedia(source, root);
  expect(result.content).toContain(
    '<p>Article</p><img alt="Food" src="/blog-media/',
  );
  expect(result.content).not.toContain("base64");
  expect(result.related_blogs[0].content).toBe(result.content);
  const files = readdirSync(join(root, "blog-media"));
  expect(files).toHaveLength(1);
  expect(readFileSync(join(root, "blog-media", files[0]))).toEqual(bytes);
  expect(source.content).toBe(content);
});

it("does not turn SVG or executable data URLs into public assets", () => {
  const content =
    '<img src="data:image/svg+xml;base64,PHN2Zz4="><img src="data:text/html;base64,PHNjcmlwdD4=">';
  expect(externalizeBlogMedia({ content }, root).content).toBe(content);
  expect(readdirSync(root)).toEqual([]);
});
