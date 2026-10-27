import { afterEach, describe, expect, it, vi } from "vitest";
import { render, fireEvent, screen, act } from "@testing-library/react";
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

vi.mock("yet-another-react-lightbox", () => ({
  default: ({ open, index }: { open: boolean; index: number }) =>
    open ? <div role="dialog" data-index={index} /> : null,
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

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

// A square product image in a 400px frame, positioned away from the viewport origin.
const prepareImage = (container: HTMLElement) => {
  const frame = container.querySelector("button.cursor-zoom-in")!;
  const image = frame.querySelector("img")!;
  Object.defineProperties(image, {
    naturalWidth: { configurable: true, value: 800 },
    naturalHeight: { configurable: true, value: 800 },
  });
  vi.spyOn(image, "getBoundingClientRect").mockReturnValue({
    x: 50, y: 100, left: 50, top: 100, right: 450, bottom: 500,
    width: 400, height: 400, toJSON: () => ({}),
  });
  return frame;
};

describe("ProductImgSection zoom area", () => {
  const sampleImages = [
    "https://example.com/img1.jpg",
    "https://example.com/img2.jpg",
  ];

  it("applies LTR positioning classes when language is English", () => {
    mockLanguage = "en";
    const { container } = render(
      <ProductImgSection
        allImages={sampleImages}
        isVertical={false}
        isLoading={false}
      />,
    );

    // Trigger mouse enter and mouse move to show zoom preview window
    const zoomContainer = prepareImage(container);
    expect(zoomContainer).not.toBeNull();
    if (zoomContainer) {
      fireEvent.mouseEnter(zoomContainer, { clientX: 250, clientY: 300 });
      fireEvent.mouseMove(zoomContainer, { clientX: 250, clientY: 300 });
    }

    const zoomPreview = container.querySelector(
      "[data-product-zoom-preview]",
    );
    expect(zoomPreview).not.toBeNull();
    expect(zoomPreview?.className).toContain("-right-[10vw]");
    expect(zoomPreview?.className).toContain("md:-right-[20vw]");
    expect(zoomPreview?.className).toContain("pointer-events-none");
    expect(zoomPreview?.className).toContain("z-30");
  });

  it("applies RTL positioning classes when language is Arabic", () => {
    mockLanguage = "ar";
    const { container } = render(
      <ProductImgSection
        allImages={sampleImages}
        isVertical={false}
        isLoading={false}
      />,
    );

    const zoomContainer = prepareImage(container);
    expect(zoomContainer).not.toBeNull();
    if (zoomContainer) {
      fireEvent.mouseEnter(zoomContainer, { clientX: 250, clientY: 300 });
      fireEvent.mouseMove(zoomContainer, { clientX: 250, clientY: 300 });
    }

    const zoomPreview = container.querySelector(
      "[data-product-zoom-preview]",
    );
    expect(zoomPreview).not.toBeNull();
    expect(zoomPreview?.className).toContain("-left-[10vw]");
    expect(zoomPreview?.className).toContain("md:-left-[20vw]");
  });

  it("dismisses the zoom preview when the page scrolls", () => {
    mockLanguage = "en";
    const { container } = render(
      <ProductImgSection
        allImages={sampleImages}
        isVertical={false}
        isLoading={false}
      />,
    );

    const zoomContainer = prepareImage(container);
    expect(zoomContainer).not.toBeNull();

    if (zoomContainer) fireEvent.mouseEnter(zoomContainer, { clientX: 250, clientY: 300 });
    expect(
      container.querySelector("[data-product-zoom-preview]"),
    ).not.toBeNull();

    fireEvent.scroll(window);

    expect(
      container.querySelector("[data-product-zoom-preview]"),
    ).toBeNull();
  });

  it("renders all images and thumbnails as native img with eager loading", () => {
    mockLanguage = "en";
    const { container } = render(
      <ProductImgSection
        allImages={sampleImages}
        isVertical={false}
        isLoading={false}
      />,
    );

    // Verify main images
    const mainImages = container.querySelectorAll("button.cursor-zoom-in img");
    expect(mainImages.length).toBe(2);

    // Verify thumbnails
    const thumbnails = container.querySelectorAll("img[alt='productGallery.thumbnail']");
    expect(thumbnails.length).toBe(2);

    // Verify thumbnails are eager loaded and not hidden behind opacity-0
    thumbnails.forEach((thumb, idx) => {
      const img = thumb as HTMLImageElement;
      expect(img.getAttribute("loading")).toBe("eager");
      expect(img.getAttribute("decoding")).toBe("async");
      expect(img.src).toContain(sampleImages[idx]);
      expect(img.className).not.toContain("opacity-0");
    });
  });

  it("marks the precise area being magnified and clears it when leaving", () => {
    const { container } = render(
      <ProductImgSection allImages={sampleImages} isVertical={false} isLoading={false} />,
    );
    const frame = prepareImage(container);
    fireEvent.mouseMove(frame, { clientX: 250, clientY: 300 });
    const lens = container.querySelector<HTMLElement>("[data-product-zoom-lens]")!;
    const previewImage = container.querySelector<HTMLImageElement>("[data-product-zoom-preview] img")!;

    expect(lens).not.toBeNull();
    expect(lens.className).toContain("pointer-events-none");
    // Each lens edge maps to the corresponding edge in the enlarged image.
    expect(parseFloat(previewImage.style.width)).toBe(800);
    expect(parseFloat(previewImage.style.left)).toBe(-2 * parseFloat(lens.style.left));
    expect(parseFloat(previewImage.style.top)).toBe(-2 * parseFloat(lens.style.top));

    fireEvent.mouseLeave(frame);
    expect(container.querySelector("[data-product-zoom-lens]")).toBeNull();
    expect(container.querySelector("[data-product-zoom-preview]")).toBeNull();
  });

  it("opens the selected image from its labeled button", () => {
    render(
      <ProductImgSection allImages={sampleImages} isVertical={false} isLoading={false} />,
    );
    const images = screen.getAllByRole("button", { name: "productGallery.openImage" });
    fireEvent.click(images[1]);
    expect(screen.getByRole("dialog")).toHaveAttribute("data-index", "1");
  });

  it("keeps tap-to-enlarge available without hover zoom on touch devices", () => {
    vi.stubGlobal("matchMedia", () => ({ matches: false }));
    const { container } = render(
      <ProductImgSection allImages={sampleImages} isVertical={false} isLoading={false} />,
    );
    const frame = prepareImage(container);
    fireEvent.mouseMove(frame, { clientX: 250, clientY: 300 });
    expect(container.querySelector("[data-product-zoom-lens]")).toBeNull();
    fireEvent.click(frame);
    expect(screen.getByRole("dialog")).toHaveAttribute("data-index", "0");
  });

  it("collapses the hint to its icon after two seconds while retaining the accessible action", () => {
    vi.useFakeTimers();
    const { container } = render(
      <ProductImgSection allImages={sampleImages} isVertical={false} isLoading={false} />,
    );
    expect(screen.getAllByText("productGallery.enlargeImage")).toHaveLength(2);
    const hints = container.querySelectorAll("[data-product-enlarge-hint]");
    hints.forEach((hint) => expect(hint).toHaveAttribute("data-expanded", "true"));
    act(() => vi.advanceTimersByTime(1999));
    hints.forEach((hint) => expect(hint).toHaveAttribute("data-expanded", "true"));
    act(() => vi.advanceTimersByTime(1));
    hints.forEach((hint) => expect(hint).toHaveAttribute("data-expanded", "false"));
    // Keep the same label mounted so CSS can animate and reverse the collapse.
    expect(screen.getAllByText("productGallery.enlargeImage")).toHaveLength(2);
    expect(container.querySelectorAll("[data-product-enlarge-hint] svg")).toHaveLength(2);
    expect(screen.getAllByRole("button", { name: "productGallery.openImage" })).toHaveLength(2);
    fireEvent.click(hints[0].querySelector("svg")!);
    expect(screen.getByRole("dialog")).toHaveAttribute("data-index", "0");
  });

  it("starts the hint timer after loading and restarts it for another product", () => {
    vi.useFakeTimers();
    const { rerender, container } = render(
      <ProductImgSection allImages={sampleImages} isVertical={false} isLoading />,
    );
    act(() => vi.advanceTimersByTime(5000));
    rerender(<ProductImgSection allImages={sampleImages} isVertical={false} isLoading={false} />);
    expect(screen.getAllByText("productGallery.enlargeImage")).toHaveLength(2);
    act(() => vi.advanceTimersByTime(3000));
    expect(container.querySelector("[data-product-enlarge-hint]")).toHaveAttribute("data-expanded", "false");
    rerender(<ProductImgSection allImages={["https://example.com/other-product.jpg"]} isVertical={false} isLoading={false} />);
    expect(screen.getByText("productGallery.enlargeImage")).toBeInTheDocument();
    expect(container.querySelector("[data-product-enlarge-hint]")).toHaveAttribute("data-expanded", "true");
    act(() => vi.advanceTimersByTime(3000));
    expect(container.querySelector("[data-product-enlarge-hint]")).toHaveAttribute("data-expanded", "false");
  });
});
