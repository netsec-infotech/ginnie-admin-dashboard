// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import {
  existsSync,
  mkdirSync,
  mkdtempSync,
  readFileSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

import { generateStaticDataRoutes } from "../../scripts/generate-static-data-routes.mjs";

let root: string;

const write = (filename: string, contents: string) => {
  const target = join(root, filename);
  mkdirSync(join(target, ".."), { recursive: true });
  writeFileSync(target, contents);
};

const html = (page: string, pageProps: Record<string, unknown> = {}) =>
  `<html><body><script id="__NEXT_DATA__" type="application/json">${JSON.stringify({
    props: { pageProps },
    page,
    buildId: "test-build",
  })}</script></body></html>`;

beforeEach(() => {
  root = mkdtempSync(join(tmpdir(), "hyperlocal-static-data-"));
  write(
    ".next/build-manifest.json",
    JSON.stringify({
      pages: {
        "/": ["static/chunks/pages/index.js"],
        "/play": ["static/chunks/pages/play.js"],
        "/about-us": ["static/chunks/pages/about-us.js"],
        "/my-account/orders/[slug]": [
          "static/chunks/pages/my-account/orders/[slug].js",
        ],
      },
    }),
  );
  write(".next/static/chunks/pages/index.js", "exports.__N_SSP=true;");
  write(".next/static/chunks/pages/play.js", "exports.__N_SSP=true;");
  write(".next/static/chunks/pages/about-us.js", "exports.default=Page;");
  write(
    ".next/static/chunks/pages/my-account/orders/[slug].js",
    "exports.__N_SSP=true;",
  );
  write("out/index.html", html("/", { initialLayout: null }));
  write("out/play/index.html", html("/play"));
  write("out/about-us/index.html", html("/about-us"));
  write(
    "out/my-account/orders/[slug]/index.html",
    html("/my-account/orders/[slug]"),
  );
});

afterEach(() => {
  rmSync(root, { recursive: true, force: true });
});

describe("static navigation data routes", () => {
  it("creates data JSON for auto-exported pages compiled as server-data pages", () => {
    expect(generateStaticDataRoutes(root)).toEqual([
      "out/_next/data/test-build/index.json",
      "out/_next/data/test-build/my-account/orders/[slug].json",
      "out/_next/data/test-build/play.json",
    ]);

    expect(
      JSON.parse(
        readFileSync(
          join(root, "out/_next/data/test-build/index.json"),
          "utf8",
        ),
      ),
    ).toEqual({ pageProps: { initialLayout: null }, __N_SSP: true });
    expect(
      JSON.parse(
        readFileSync(
          join(root, "out/_next/data/test-build/play.json"),
          "utf8",
        ),
      ),
    ).toEqual({ pageProps: {}, __N_SSP: true });
    expect(
      existsSync(join(root, "out/_next/data/test-build/about-us.json")),
    ).toBe(false);
  });

  it("does not overwrite an existing generated data route", () => {
    write(
      "out/_next/data/test-build/play.json",
      JSON.stringify({ pageProps: { preserved: true } }),
    );

    generateStaticDataRoutes(root);

    expect(
      JSON.parse(
        readFileSync(
          join(root, "out/_next/data/test-build/play.json"),
          "utf8",
        ),
      ),
    ).toEqual({ pageProps: { preserved: true } });
  });
});
