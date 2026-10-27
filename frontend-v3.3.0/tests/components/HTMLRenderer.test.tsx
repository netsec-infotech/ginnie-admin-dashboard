import { render } from "@testing-library/react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import HTMLRenderer from "@/components/Functional/HTMLRenderer";

describe("HTMLRenderer security", () => {
  it.each([
    '<img src="/missing.png" onerror="alert(1)">',
    '<a href="java&#x73;cript:alert(1)">link</a>',
    '<svg onload="alert(1)"><a href="javascript:alert(1)">x</a></svg>',
    '<iframe srcdoc="<script>alert(1)</script>"></iframe>',
    '<script>alert(1)</script><form action="/checkout"><input name="amount"></form>',
    '<a href="java&#10;script:alert(1)">link</a><img src="data:image/svg+xml,<svg onload=alert(1)>">',
  ])("removes executable markup on client and server: %s", (html) => {
    const { container } = render(<HTMLRenderer html={html} />);
    const server = document.createElement("div");
    server.innerHTML = renderToStaticMarkup(<HTMLRenderer html={html} />);
    for (const root of [container, server]) {
      expect(root.querySelector("script,svg,iframe,form,input")).toBeNull();
      for (const element of root.querySelectorAll("*")) {
        for (const attribute of element.attributes) {
          expect(attribute.name).not.toMatch(/^on/i);
          if (["href", "src"].includes(attribute.name)) {
            expect(attribute.value.replace(/\s/g, "")).not.toMatch(/^(javascript|data):/i);
          }
        }
      }
    }
  });

  it("keeps editor text styles while removing positioning and CSS URLs", () => {
    const { container } = render(<HTMLRenderer html={'<p style="color:red;text-align:center;font-weight:700;position:fixed;background-image:url(https://example.com/track)">Text</p>'} />);
    const paragraph = container.querySelector("p")!;
    expect(paragraph.style.color).toBe("red");
    expect(paragraph.style.textAlign).toBe("center");
    expect(paragraph.style.fontWeight).toBe("700");
    expect(paragraph.style.position).toBe("");
    expect(paragraph.style.backgroundImage).toBe("");
  });

  it("preserves rich text, images, safe links and tables after updates", () => {
    const html = '<h2>Details</h2><p><strong>Fresh</strong> &amp; local</p><ul><li>Fruit</li></ul><a href="/terms">Terms</a><img src="https://example.com/product.png" alt="Product"><table><tbody><tr><td colspan="2">Info</td></tr></tbody></table>';
    const { container, rerender } = render(<HTMLRenderer html="Old" className="custom" />);
    rerender(<HTMLRenderer html={html} className="custom" />);
    expect(container.querySelector(".html-content.custom strong")).toHaveTextContent("Fresh");
    expect(container.querySelector("li")).toHaveTextContent("Fruit");
    expect(container.querySelector("a")).toHaveAttribute("href", "/terms");
    expect(container.querySelector("img")).toHaveAttribute("alt", "Product");
    expect(container.querySelector("td")).toHaveAttribute("colspan", "2");
    expect(container).not.toHaveTextContent("Old");
  });
});
