import { describe, expect, it } from "vitest";
import { blogPlainText, prepareBlogContent } from "@/helpers/blog";

describe("blog article contents", () => {
  it("sanitizes content and builds unique anchors from repeated headings", () => {
    const result = prepareBlogContent(
      '<h2 id="duplicate">Fresh &amp; local</h2><script>alert(1)</script><h2 id="duplicate">Fresh &amp; local</h2><img src="https://example.com/photo.jpg" onerror="alert(2)">',
    );
    expect(result.headings).toEqual([
      { id: "article-section-1", title: "Fresh & local", level: 2 },
      { id: "article-section-2", title: "Fresh & local", level: 2 },
    ]);
    expect(result.html).toContain('id="article-section-1"');
    expect(result.html).not.toMatch(/script|onerror|duplicate/);
  });

  it("keeps heading levels and Unicode text and ignores empty headings", () => {
    const result = prepareBlogContent(
      "<h2></h2><h3><strong>स्थानीय खरीदारी</strong></h3><h2>التسوق المحلي</h2>",
    );
    expect(
      result.headings.map(({ title, level }) => ({ title, level })),
    ).toEqual([
      { title: "स्थानीय खरीदारी", level: 3 },
      { title: "التسوق المحلي", level: 2 },
    ]);
  });

  it("does not create unsafe links in article HTML", () => {
    const result = prepareBlogContent(
      '<p><a href="javascript:alert(1)">link</a></p><iframe src="https://example.com"></iframe>',
    );
    expect(result.html).not.toMatch(/javascript:|iframe/);
  });

  it("decodes entities safely for the visible contents labels", () => {
    expect(
      blogPlainText("A &amp; B &#x1f33f; &#39;local&#39; &#9999999999;"),
    ).toBe("A & B 🌿 'local'");
  });
});
