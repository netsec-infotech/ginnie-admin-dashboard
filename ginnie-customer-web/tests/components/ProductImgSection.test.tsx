import { describe, expect, it, vi } from "vitest";
import { render, fireEvent } from "@testing-library/react";
import ProductImgSection from "@/components/Products/ProductDetailPage/ProductImgSection";

// Mock Swiper components
vi.mock("swiper/react", () => ({
  Swiper: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="swiper-mock">{children}</div>
  ),
  SwiperSlide: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="swiper-slide-mock">{children}</div>
  ),
}));

vi.mock("swiper/modules", () => ({
  Navigation: {},
  Pagination: {},
  Mousewheel: {},
}));

// Mock react-i18next
let mockLanguage = "en";
vi.mock("react-i18next", async (importOriginal) => {
  const actual = (await importOriginal()) as object;
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string) => key,
      i18n: {
        language: mockLanguage,
        resolvedLanguage: mockLanguage,
      },
    }),
  };
});

describe("ProductImgSection Zoom Lens RTL positioning", () => {
  const sampleImages = ["https://example.com/img1.jpg", "https://example.com/img2.jpg"];

  it("applies LTR positioning classes when language is English", () => {
    mockLanguage = "en";
    const { container } = render(
      <ProductImgSection
        allImages={sampleImages}
        isVertical={false}
        isLoading={false}
      />
    );

    // Trigger mouse enter and mouse move to show zoom preview window
    const zoomContainer = container.querySelector(".cursor-zoom-in");
    expect(zoomContainer).not.toBeNull();
    if (zoomContainer) {
      fireEvent.mouseEnter(zoomContainer);
      fireEvent.mouseMove(zoomContainer, { clientX: 100, clientY: 100 });
    }

    const zoomPreview = container.querySelector(".cursor-zoom-in > div.absolute");
    expect(zoomPreview).not.toBeNull();
    expect(zoomPreview?.className).toContain("-right-[10vw]");
    expect(zoomPreview?.className).toContain("md:-right-[20vw]");
  });

  it("applies RTL positioning classes when language is Arabic", () => {
    mockLanguage = "ar";
    const { container } = render(
      <ProductImgSection
        allImages={sampleImages}
        isVertical={false}
        isLoading={false}
      />
    );

    const zoomContainer = container.querySelector(".cursor-zoom-in");
    expect(zoomContainer).not.toBeNull();
    if (zoomContainer) {
      fireEvent.mouseEnter(zoomContainer);
      fireEvent.mouseMove(zoomContainer, { clientX: 100, clientY: 100 });
    }

    const zoomPreview = container.querySelector(".cursor-zoom-in > div.absolute");
    expect(zoomPreview).not.toBeNull();
    expect(zoomPreview?.className).toContain("-left-[10vw]");
    expect(zoomPreview?.className).toContain("md:-left-[20vw]");
  });
});
