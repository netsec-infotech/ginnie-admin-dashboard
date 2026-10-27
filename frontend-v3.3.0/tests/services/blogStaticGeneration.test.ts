// @vitest-environment node
import { afterEach, beforeEach, expect, it } from "vitest";
import { mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { readBlogStaticSnapshot } from "@/services/blogStaticGenerationService";
let root: string;
beforeEach(() => {
  root = mkdtempSync(join(tmpdir(), "hyperlocal-blog-snapshot-"));
});
afterEach(() => {
  rmSync(root, { recursive: true, force: true });
});
it("loads public article snapshots and distinguishes an absent cache", async () => {
  const filename = join(root, "snapshot.json");
  expect(await readBlogStaticSnapshot(filename)).toBeNull();
  const posts = [{ id: 1, slug: "food", title: "Food" }];
  writeFileSync(filename, JSON.stringify({ posts }));
  expect(await readBlogStaticSnapshot(filename)).toEqual(posts);
});
it("fails closed on malformed build data", async () => {
  const filename = join(root, "snapshot.json");
  writeFileSync(filename, JSON.stringify({ posts: {} }));
  await expect(readBlogStaticSnapshot(filename)).rejects.toThrow(
    "Invalid blog build snapshot",
  );
});
