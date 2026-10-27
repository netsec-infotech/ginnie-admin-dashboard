// @vitest-environment node
import { renderToStaticMarkup } from "react-dom/server";
import { expect, it } from "vitest";
import HTMLRenderer from "@/components/Functional/HTMLRenderer";

it("sanitizes SSR output without requiring a window or document", () => {
  expect(typeof window).toBe("undefined");
  const output = renderToStaticMarkup(<HTMLRenderer html={'<strong>Fresh</strong><img src="/fruit.png" onerror="alert(1)"><script>alert(1)</script>'} />);
  expect(output).toContain("<strong>Fresh</strong>");
  expect(output).toContain('src="/fruit.png"');
  expect(output).not.toContain("onerror");
  expect(output).not.toContain("<script");
});
