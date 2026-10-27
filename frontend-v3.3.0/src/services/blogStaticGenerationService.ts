import { readFile } from "node:fs/promises";
import { resolve } from "node:path";
import type { ApiBlogPost } from "@/types/blog";

export async function readBlogStaticSnapshot(
  filename = process.env.HYPERLOCAL_STATIC_BLOG_SNAPSHOT ||
    resolve(
      process.cwd(),
      "node_modules",
      ".cache",
      "hyperlocal-static-blogs.json",
    ),
): Promise<ApiBlogPost[] | null> {
  try {
    const snapshot = JSON.parse(await readFile(filename, "utf8"));
    if (!Array.isArray(snapshot.posts))
      throw new Error("Invalid blog build snapshot");
    return snapshot.posts;
  } catch (error) {
    if (
      error &&
      typeof error === "object" &&
      "code" in error &&
      error.code === "ENOENT"
    )
      return null;
    throw error;
  }
}
