import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import ImageOnlyVariant from "@/components/Cards/CategoryCard/variants/ImageOnlyVariant";
import type { Category } from "@/types/ApiResponse";

const category: Category = {
  id: 133,
  title: "Kid's Toys",
  slug: "kids-toys",
  image: "https://example.com/toys.png",
  banner: "",
  icon: "",
  active_icon: "",
  background_type: null,
  background_color: "",
  background_image: "",
  font_color: "#000000",
  parent_id: 27,
  parent_slug: "kids-1",
  description: "Kids Toys items",
  status: "active",
  requires_approval: false,
  metadata: null,
  subcategory_count: 0,
  product_count: 0,
  enabled: true,
};

describe("ImageOnlyVariant", () => {
  it("shows only the category artwork at its natural aspect ratio", () => {
    render(<ImageOnlyVariant category={category} />);

    const image = screen.getByRole("img", { name: "Kid's Toys" });
    expect(image).toHaveAttribute("src", category.image);
    expect(image).not.toHaveAttribute("width");
    expect(image).toHaveClass("h-auto", "w-auto", "max-w-full", "object-contain");
    expect(screen.getByRole("link", { name: "Kid's Toys" })).toHaveStyle({
      width: "fit-content",
      maxWidth: "min(100%, 280px)",
    });
    expect(screen.getByRole("link", { name: "Kid's Toys" })).toHaveAttribute(
      "href",
      "/categories/kids-1?subcategory=kids-toys",
    );
    expect(screen.queryByText("Shop now")).not.toBeInTheDocument();
  });

  it("prefers an explicit custom image over the category thumbnail", () => {
    render(
      <ImageOnlyVariant
        category={{
          ...category,
          custom_image: "https://example.com/custom-toys.png",
        }}
      />,
    );

    expect(screen.getByRole("img")).toHaveAttribute(
      "src",
      "https://example.com/custom-toys.png",
    );
  });
});
