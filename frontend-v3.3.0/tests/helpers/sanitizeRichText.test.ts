import { describe, expect, it } from "vitest";
import { sanitizeRichText } from "@/helpers/sanitizeRichText";

const rasterSource = "data:image/png;base64,iVBORw0KGgo=";

describe("rich text image policy", () => {
  it("preserves editor raster images only when the article policy is enabled", () => {
    const html = `<p>Article text.</p><img src="${rasterSource}" alt="Illustration" onerror="alert(1)">`;
    const standard = sanitizeRichText(html);
    const article = sanitizeRichText(html, { allowRasterDataImages: true });

    expect(standard).not.toContain(rasterSource);
    expect(article).toContain(`src="${rasterSource}"`);
    expect(article).toContain('alt="Illustration"');
    expect(article).not.toContain("onerror");
    expect(article).toContain("Article text.");
  });

  it.each(["png", "jpeg", "gif", "webp"])(
    "allows the supported %s raster format",
    (format) => {
      const source = `data:image/${format};base64,aGVsbG8=`;
      expect(
        sanitizeRichText(`<img src="${source}">`, {
          allowRasterDataImages: true,
        }),
      ).toContain(`src="${source}"`);
    },
  );

  it.each([
    "data:image/svg+xml;base64,PHN2Zz4=",
    "data:text/html;base64,PHNjcmlwdD4=",
    " data:image/svg+xml;base64,PHN2Zz4= ",
    "da&#9;ta:image/svg+xml;base64,PHN2Zz4=",
    "data:image/png,not-base64",
    "data:image/png;base64,invalid<script>",
    "javascript:alert(1)",
  ])("rejects unsafe or unsupported image sources: %s", (source) => {
    const result = sanitizeRichText(
      `<img src="${source}" alt="Illustration">`,
      {
        allowRasterDataImages: true,
      },
    );
    expect(result).not.toContain("src=");
    expect(result).toContain('alt="Illustration"');
  });

  it("does not allow srcset or anchor URLs to bypass article image validation", () => {
    const result = sanitizeRichText(
      `<img src="https://example.com/photo.jpg" srcset="data:image/svg+xml;base64,PHN2Zz4= 2x"><a href="${rasterSource}">Link</a><script>alert(1)</script>`,
      { allowRasterDataImages: true },
    );
    expect(result).toContain('src="https://example.com/photo.jpg"');
    expect(result).not.toContain("srcset");
    expect(result).toContain("<a>Link</a>");
    expect(result).not.toContain("<script");
  });
});
