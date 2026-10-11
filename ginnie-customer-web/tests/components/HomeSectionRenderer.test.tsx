import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen } from "@testing-library/react";

// Mock the heavy leaves so this stays a focused unit test of the renderer's
// decision logic (type switch / fallback / product fan-out). The ProductCard
// field contract is covered by the adapter tests.
// Partially mock: the real i18n.ts (pulled in via helpers) needs initReactI18next,
// so keep the module intact and only stub the hook.
vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string) => key,
      i18n: { language: "en", resolvedLanguage: "en" },
    }),
  };
});

// Mirrors next/link's object-href support so we can assert the resolved URL.
vi.mock("next/link", () => ({
  default: ({ href, children, ...rest }: any) => {
    const url =
      typeof href === "string"
        ? href
        : `${href.pathname}${
            href.query ? `?${new URLSearchParams(href.query).toString()}` : ""
          }`;
    return (
      <a href={url} {...rest}>
        {children}
      </a>
    );
  },
}));

vi.mock("swiper/react", () => ({
  Swiper: ({ children }: any) => <div data-testid="swiper">{children}</div>,
  SwiperSlide: ({ children }: any) => <div>{children}</div>,
}));

vi.mock("swiper/modules", () => ({ Navigation: {}, Autoplay: {} }));

vi.mock("@/components/SwiperNavigation", () => ({ default: () => null }));

vi.mock("@/components/SectionHeading", () => ({
  default: ({ title, color }: any) => <h2 style={{ color }}>{title}</h2>,
}));

vi.mock("@/components/Cards/ProductCard", () => ({
  default: ({ product }: any) => (
    <div data-testid="product-card">{product.title}</div>
  ),
}));

vi.mock("@/components/Skeletons/ProductCardSkeleton", () => ({
  default: () => <div data-testid="product-skeleton" />,
}));

vi.mock("@/components/Cards/CategoryCard", () => ({
  default: ({ category }: any) => (
    <div data-testid="category-card">{category.title}</div>
  ),
}));

vi.mock("@/components/Skeletons/CategoryCardSkeleton", () => ({
  default: () => <div data-testid="category-skeleton" />,
}));

vi.mock("@/components/Cards/BrandCard", () => ({
  default: ({ brand }: any) => <div data-testid="brand-card">{brand.title}</div>,
}));

vi.mock("@/components/Skeletons/BrandCardSkeleton", () => ({
  default: () => <div data-testid="brand-skeleton" />,
}));

vi.mock("@/components/Cards/StoreCard", () => ({
  default: ({ store }: any) => <div data-testid="store-card">{store.name}</div>,
}));

vi.mock("@/components/Skeletons/StoreCardSkeleton", () => ({
  default: () => <div data-testid="store-skeleton" />,
}));

// The rails page through this on swipe; nothing here swipes, so it only needs
// to exist. (Paging itself is covered by HomeSectionRail.test.tsx.)
vi.mock("@/routes/api", () => ({ getHomeLayoutSection: vi.fn() }));

// Mutable so the single-vendor case can flip it; reset in beforeEach.
const { settings } = vi.hoisted(() => ({ settings: { isSingleVendor: false } }));
vi.mock("@/contexts/SettingsContext", () => ({ useSettings: () => settings }));

import HomeSectionRenderer, {
  getSectionBackgroundStyle,
  getSectionPresentation,
} from "@/views/homePage/HomeSectionRenderer";
import { HomeLayoutSection } from "@/types/ApiResponse";
import homeLayoutResponse from "../fixtures/homeLayoutResponse.json";
import categoriesResponse from "../fixtures/homeLayoutCategoriesResponse.json";
import bannersResponse from "../fixtures/homeLayoutBannersResponse.json";

const liveCategoriesSection = (categoriesResponse as any).data
  .sections[0] as HomeLayoutSection;
const liveCategories = (liveCategoriesSection.content.categories as any[]) ?? [];

const liveSection = (homeLayoutResponse as any).data.sections[0] as HomeLayoutSection;
// The products fixture has a real title; `title` is nullable on the type.
const liveTitle = liveSection.title as string;
const liveProducts = (liveSection.content.products as any[]) ?? [];

const makeSection = (over: Partial<HomeLayoutSection> = {}): HomeLayoutSection =>
  ({ ...JSON.parse(JSON.stringify(liveSection)), ...over }) as HomeLayoutSection;

const liveBannerSection = (bannersResponse as any).data
  .sections[0] as HomeLayoutSection;
const liveBanners = (liveBannerSection.content.items as any[]) ?? [];

/** A bare section of `type`, with whatever content the caller supplies. */
const sectionOf = (
  type: string,
  content: Record<string, unknown[]>,
  over: Partial<HomeLayoutSection> = {},
): HomeLayoutSection =>
  ({
    id: 99,
    type,
    title: null,
    style: null,
    config: {},
    background_image: null,
    hero_media: null,
    card_background_image: null,
    content,
    ...over,
  }) as HomeLayoutSection;

beforeEach(() => {
  settings.isSingleVendor = false;
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe("HomeSectionRenderer — products section (live fixture)", () => {
  it("renders the section title and one card per product", () => {
    render(<HomeSectionRenderer section={liveSection} />);

    expect(screen.getByText(liveTitle)).toBeInTheDocument();
    expect(screen.getAllByTestId("product-card")).toHaveLength(liveProducts.length);
  });

  it("links 'see all' to the id-based section route, carrying the title", () => {
    render(<HomeSectionRenderer section={liveSection} />);
    const expected = `/section/${liveSection.id}?${new URLSearchParams({
      title: liveTitle,
    }).toString()}`;
    expect(screen.getByRole("link", { name: "see_all" })).toHaveAttribute(
      "href",
      expected,
    );
  });

  it("omits the title query when the section has no title", () => {
    render(<HomeSectionRenderer section={makeSection({ title: "" })} />);
    expect(screen.getByRole("link", { name: "see_all" })).toHaveAttribute(
      "href",
      `/section/${liveSection.id}`,
    );
  });

  it("renders skeletons instead of cards while loading", () => {
    render(<HomeSectionRenderer section={liveSection} isLoading />);
    expect(screen.getAllByTestId("product-skeleton").length).toBeGreaterThan(0);
    expect(screen.queryByTestId("product-card")).not.toBeInTheDocument();
  });

  it("uses a grid when config.orientation is vertical", () => {
    render(
      <HomeSectionRenderer
        section={makeSection({
          config: {
            ...liveSection.config,
            orientation: "vertical",
            spacing: { item_gap: { horizontal: 10, vertical: 14 } },
          },
        })}
      />,
    );

    const grid = screen.getByTestId("home-products-grid");
    expect(grid).toHaveStyle({ columnGap: "10px", rowGap: "14px" });
    expect(screen.queryByTestId("swiper")).not.toBeInTheDocument();
  });
});

describe("HomeSectionRenderer — categories section (live fixture)", () => {
  it("renders one card per category from the live categories section", () => {
    render(<HomeSectionRenderer section={liveCategoriesSection} />);
    expect(screen.getAllByTestId("category-card")).toHaveLength(
      liveCategories.length,
    );
    expect(screen.getAllByTestId("category-card")).toHaveLength(18);
  });

  it("falls back to a default heading when the API sends title: null", () => {
    // The live categories section really does have title === null.
    expect(liveCategoriesSection.title).toBeNull();
    render(<HomeSectionRenderer section={liveCategoriesSection} />);
    expect(screen.getByText("home.categories.title")).toBeInTheDocument();
  });

  it("links categories 'see all' to /categories", () => {
    render(<HomeSectionRenderer section={liveCategoriesSection} />);
    expect(screen.getByRole("link", { name: "see_all" })).toHaveAttribute(
      "href",
      "/categories",
    );
  });

  it("renders category skeletons while loading", () => {
    render(<HomeSectionRenderer section={liveCategoriesSection} isLoading />);
    expect(screen.getAllByTestId("category-skeleton").length).toBeGreaterThan(0);
    expect(screen.queryByTestId("category-card")).not.toBeInTheDocument();
  });

  it("renders nothing when a categories section is empty", () => {
    const { container } = render(
      <HomeSectionRenderer
        section={
          { ...liveCategoriesSection, content: { categories: [] } } as any
        }
      />,
    );
    expect(container).toBeEmptyDOMElement();
  });
});

describe("HomeSectionRenderer — banners section (live fixture)", () => {
  it("renders one image per banner in the live section", () => {
    // This is the regression: the live homepage ships a `banners` section that
    // used to hit the unsupported-type branch and render nothing at all.
    render(<HomeSectionRenderer section={liveBannerSection} />);
    expect(screen.getAllByRole("img")).toHaveLength(liveBanners.length);
    expect(liveBanners.length).toBe(3);
  });

  it("links each live banner to its custom URL", () => {
    const { container } = render(
      <HomeSectionRenderer section={liveBannerSection} />,
    );
    // Queried as anchors, not by role: HeroUI's `isPressable` stamps
    // role="button" onto the <a>, so getAllByRole("link") finds nothing.
    const links = container.querySelectorAll("a[href]");
    expect(links).toHaveLength(liveBanners.length);
    expect(links[0]).toHaveAttribute("href", liveBanners[0].link_url);
  });

  it("renders an unlinked banner as an image with no link", () => {
    const { container } = render(
      <HomeSectionRenderer
        section={sectionOf("banners", {
          items: [{ id: 1, image: "a.jpg", link_type: "none" }],
        })}
      />,
    );
    expect(screen.getByRole("img")).toBeInTheDocument();
    expect(container.querySelectorAll("a")).toHaveLength(0);
  });

  it("renders a sliders section the same way", () => {
    render(
      <HomeSectionRenderer
        section={sectionOf("sliders", {
          items: [{ id: 1, image: "a.jpg", link_type: "custom", link_url: "/x" }],
        })}
      />,
    );
    expect(screen.getByRole("img")).toBeInTheDocument();
  });

  it("uses a static grid (no swiper) for style: grid", () => {
    render(
      <HomeSectionRenderer
        section={sectionOf(
          "banners",
          { items: [{ id: 1, image: "a.jpg" }] },
          { style: "grid" },
        )}
      />,
    );
    expect(screen.queryByTestId("swiper")).not.toBeInTheDocument();
    expect(screen.getByRole("img")).toBeInTheDocument();
  });

  it("shows a heading only when the section has a title", () => {
    const { unmount } = render(
      <HomeSectionRenderer section={liveBannerSection} />,
    );
    expect(liveBannerSection.title).toBeNull();
    expect(screen.queryByRole("heading")).not.toBeInTheDocument();
    unmount();

    render(
      <HomeSectionRenderer
        section={sectionOf(
          "banners",
          { items: [{ id: 1, image: "a.jpg" }] },
          { title: "Deals" },
        )}
      />,
    );
    expect(screen.getByText("Deals")).toBeInTheDocument();
  });

  it("renders nothing when a banners section has no items", () => {
    const { container } = render(
      <HomeSectionRenderer section={sectionOf("banners", { items: [] })} />,
    );
    expect(container).toBeEmptyDOMElement();
  });
});

describe("HomeSectionRenderer — brands section", () => {
  const brands = [
    { id: 1, title: "Everest", slug: "everest", logo: "e.png" },
    { id: 2, title: "MDH", slug: "mdh", logo: "m.png" },
  ];

  it("renders one card per brand and falls back to the default heading", () => {
    render(<HomeSectionRenderer section={sectionOf("brands", { brands })} />);
    expect(screen.getAllByTestId("brand-card")).toHaveLength(2);
    expect(screen.getByText("home.brands.title")).toBeInTheDocument();
  });

  it("links 'see all' to /brands", () => {
    render(<HomeSectionRenderer section={sectionOf("brands", { brands })} />);
    expect(screen.getByRole("link", { name: "see_all" })).toHaveAttribute(
      "href",
      "/brands",
    );
  });

  it("renders skeletons while loading and nothing when empty", () => {
    const { unmount } = render(
      <HomeSectionRenderer
        section={sectionOf("brands", { brands: [] })}
        isLoading
      />,
    );
    expect(screen.getAllByTestId("brand-skeleton").length).toBeGreaterThan(0);
    unmount();

    const { container } = render(
      <HomeSectionRenderer section={sectionOf("brands", { brands: [] })} />,
    );
    expect(container).toBeEmptyDOMElement();
  });
});

describe("HomeSectionRenderer — stores section", () => {
  const stores = [
    { id: 1, name: "Spice Depot", slug: "spice-depot" },
    { id: 2, name: "Curry Corner", slug: "curry-corner" },
  ];

  it("renders one card per store and links 'see all' to /stores", () => {
    render(<HomeSectionRenderer section={sectionOf("stores", { stores })} />);
    expect(screen.getAllByTestId("store-card")).toHaveLength(2);
    expect(screen.getByText("home.stores.title")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "see_all" })).toHaveAttribute(
      "href",
      "/stores",
    );
  });

  it("hides itself on a single-vendor site, as the legacy rail does", () => {
    settings.isSingleVendor = true;
    const { container } = render(
      <HomeSectionRenderer section={sectionOf("stores", { stores })} />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("renders nothing when a stores section is empty", () => {
    const { container } = render(
      <HomeSectionRenderer section={sectionOf("stores", { stores: [] })} />,
    );
    expect(container).toBeEmptyDOMElement();
  });
});

describe("HomeSectionRenderer — graceful fallbacks", () => {
  it("renders nothing for an unknown section type and warns in dev", () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const { container } = render(
      <HomeSectionRenderer section={makeSection({ type: "testimonials" })} />,
    );

    expect(container).toBeEmptyDOMElement();
    expect(warn).toHaveBeenCalledWith(
      expect.stringContaining('Unsupported section type "testimonials"'),
    );
  });

  it("renders nothing when a products section has no products", () => {
    const { container } = render(
      <HomeSectionRenderer section={makeSection({ content: { products: [] } })} />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("renders nothing when content is missing entirely", () => {
    const { container } = render(
      <HomeSectionRenderer section={makeSection({ content: {} })} />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("renders nothing for a nullish section", () => {
    const { container } = render(
      <HomeSectionRenderer section={undefined as any} />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("drops variant-less products but still renders the rest", () => {
    const section = makeSection({
      content: {
        products: [liveProducts[0], { ...liveProducts[1], variants: [] }],
      },
    });
    render(<HomeSectionRenderer section={section} />);
    expect(screen.getAllByTestId("product-card")).toHaveLength(1);
  });
});

describe("getSectionBackgroundStyle", () => {
  it("returns a background colour for background_type=color", () => {
    const section = makeSection({
      config: { source: null, orientation: null, background_type: "color", background_color: "#e71313" },
    });
    expect(getSectionBackgroundStyle(section)).toEqual({
      backgroundColor: "#e71313",
    });
  });

  it("returns scoped light and dark variables for themed background colours", () => {
    const section = makeSection({
      config: {
        source: null,
        orientation: null,
        background_type: "color",
        background_color: { light: "#FFFFFF", dark: "#1E293B" },
      },
    });

    expect(getSectionBackgroundStyle(section)).toEqual({
      "--home-section-background-light": "#FFFFFF",
      "--home-section-background-dark": "#1E293B",
    });
  });

  it("returns a background image for background_type=image", () => {
    const section = makeSection({
      config: { source: null, orientation: null, background_type: "image", background_color: null },
      background_image: "https://cdn.test/a b(1).png",
    });
    const style = getSectionBackgroundStyle(section);
    // spaces/parens are URL-encoded so the CSS url() stays valid
    expect(style.backgroundImage).toBe('url("https://cdn.test/a%20b%281%29.png")');
    expect(style.backgroundSize).toBe("cover");
  });

  it("returns no styling for the live 'none' background type", () => {
    // The live fixture uses background_type "none" with a stray colour set.
    expect(getSectionBackgroundStyle(liveSection)).toEqual({});
  });

  it("returns no styling when an image type has no image", () => {
    const section = makeSection({
      config: { source: null, orientation: null, background_type: "image", background_color: null },
      background_image: null,
      card_background_image: null,
    });
    expect(getSectionBackgroundStyle(section)).toEqual({});
  });
});

describe("getSectionPresentation", () => {
  it("normalizes the live nested spacing, gap, radius, and text config", () => {
    const section = sectionOf("products", { products: [] }, {
      config: {
        background_type: "color",
        background_color: "#f0e0d0",
        text_color: "#102030",
        spacing: {
          outer: { top: "12", bottom: 16, left: 0, right: "1rem" },
          inner: { top: 4, bottom: "6px", sides: 8 },
          item_gap: { horizontal: 12, vertical: "14" },
          border_radius: {
            top_left: 12,
            top_right: "10px",
            bottom_left: 8,
            bottom_right: 6,
          },
        },
      },
    });

    expect(getSectionPresentation(section)).toEqual({
      outerStyle: {
        marginTop: "12px",
        marginBottom: "16px",
        marginLeft: "0px",
        marginRight: "1rem",
      },
      innerStyle: {
        backgroundColor: "#f0e0d0",
        paddingTop: "4px",
        paddingBottom: "6px",
        paddingLeft: "8px",
        paddingRight: "8px",
        borderTopLeftRadius: "12px",
        borderTopRightRadius: "10px",
        borderBottomLeftRadius: "8px",
        borderBottomRightRadius: "6px",
      },
      itemGap: { horizontal: 12, vertical: 14 },
      borderRadiusStyle: {
        borderTopLeftRadius: "12px",
        borderTopRightRadius: "10px",
        borderBottomLeftRadius: "8px",
        borderBottomRightRadius: "6px",
      },
      textColor: "#102030",
    });
  });

  it("normalizes themed text colours without coloring the whole section", () => {
    const presentation = getSectionPresentation(
      sectionOf("products", { products: [] }, {
        config: {
          text_color: { light: "#1E293B", dark: "#F8FAFC" },
        },
      }),
    );

    expect(presentation.innerStyle).toEqual({
      "--home-section-text-light": "#1E293B",
      "--home-section-text-dark": "#F8FAFC",
    });
    expect(presentation.innerStyle).not.toHaveProperty("color");
    expect(presentation.textColor).toBe("var(--home-section-text-color)");
  });

  it("leaves presentation fields unset when config values are absent", () => {
    expect(
      getSectionPresentation(sectionOf("banners", { items: [] })),
    ).toEqual({
      outerStyle: {},
      innerStyle: {},
      itemGap: { horizontal: undefined, vertical: undefined },
      borderRadiusStyle: {},
      textColor: undefined,
    });
  });

  it("treats the builder's zero radius as unset but keeps a zero item gap", () => {
    const presentation = getSectionPresentation(
      sectionOf("banners", { items: [] }, {
        config: {
          spacing: {
            item_gap: 0,
            border_radius: 0,
          },
        },
      }),
    );

    expect(presentation.itemGap).toEqual({ horizontal: 0, vertical: 0 });
    expect(presentation.borderRadiusStyle).toEqual({});
  });

  it("applies meaningful outer spacing independently of a zero radius", () => {
    const presentation = getSectionPresentation(
      sectionOf("banners", { items: [] }, {
        config: {
          spacing: {
            outer: { top: 40, bottom: 6, left: 0, right: 0 },
            inner: { top: 0, bottom: 0, sides: 0 },
            border_radius: 0,
          },
        },
      }),
    );

    expect(presentation.outerStyle).toEqual({
      marginTop: "40px",
      marginBottom: "6px",
      marginLeft: "0px",
      marginRight: "0px",
    });
    expect(presentation.innerStyle).toEqual({});
    expect(presentation.borderRadiusStyle).toEqual({});
  });
});

describe("HomeSectionRenderer — themed section colors", () => {
  it("themes the background, title, and see-all control only", () => {
    render(
      <HomeSectionRenderer
        section={makeSection({
          config: {
            ...liveSection.config,
            background_type: "color",
            background_color: { light: "#FFFFFF", dark: "#1E293B" },
            text_color: { light: "#1E293B", dark: "#F8FAFC" },
          },
        })}
      />,
    );

    const inner = screen.getByTestId(`home-section-inner-${liveSection.id}`);
    expect(inner).toHaveClass(
      "bg-[var(--home-section-background-light)]",
      "dark:bg-[var(--home-section-background-dark)]",
      "[--home-section-text-color:var(--home-section-text-light)]",
      "dark:[--home-section-text-color:var(--home-section-text-dark)]",
    );
    expect(inner.style.color).toBe("");
    expect(screen.getByText(liveTitle)).toHaveStyle({
      color: "var(--home-section-text-color)",
    });
    expect(screen.getByRole("link", { name: "see_all" })).toHaveStyle({
      color: "var(--home-section-text-color)",
    });
  });
});

describe("HomeSectionRenderer — image background treatment", () => {
  it("renders the image as a shallow decorative backdrop behind the rail", () => {
    render(
      <HomeSectionRenderer
        section={makeSection({
          title: "Artwork collection",
          config: {
            ...liveSection.config,
            background_type: "image",
            background_image: "https://cdn.test/section-art.jpg",
          },
        })}
      />,
    );

    const inner = screen.getByTestId(`home-section-inner-${liveSection.id}`);
    const backdrop = screen.getByTestId(
      `home-section-backdrop-${liveSection.id}`,
    );
    const content = screen.getByTestId(
      `home-section-content-${liveSection.id}`,
    );

    expect(inner).toHaveClass("relative", "pt-5");
    expect(inner.style.backgroundImage).toBe("");
    expect(backdrop).toHaveClass(
      "absolute",
      "aspect-2/1",
      "rounded-xl",
      "md:aspect-5/1",
    );
    expect(backdrop.style.backgroundImage).toBe(
      'url("https://cdn.test/section-art.jpg")',
    );
    expect(backdrop.style.backgroundPosition).toBe("center top");
    expect(content).toHaveClass("relative", "px-1", "pt-[2%]", "pb-2", "sm:px-6");
    expect(screen.queryByText("Artwork collection")).not.toBeInTheDocument();
    expect(screen.getByRole("link", { name: "see_all" })).toHaveClass(
      "rounded-xl",
      "bg-gray-100",
    );
  });
});

describe("HomeSectionRenderer — radius fallbacks", () => {
  it("keeps the original shell when an image background is configured without an asset", () => {
    render(
      <HomeSectionRenderer
        section={makeSection({
          config: {
            ...liveSection.config,
            background_type: "image",
            background_image: null,
            background_color: null,
          },
          background_image: null,
          card_background_image: null,
        })}
      />,
    );

    const inner = screen.getByTestId(
      `home-section-inner-${liveSection.id}`,
    );
    expect(inner).toHaveClass("rounded-xl", "px-1", "py-4", "sm:px-6");
    expect(inner.style.backgroundImage).toBe("");
  });

  it("applies non-zero margin while retaining defaults for zero padding and radius", () => {
    render(
      <HomeSectionRenderer
        section={makeSection({
          config: {
            ...liveSection.config,
            background_type: "color",
            background_color: "#f4f4f4",
            spacing: {
              outer: { top: 12, bottom: 12, left: 0, right: 0 },
              inner: { top: 0, bottom: 0, sides: 0 },
              item_gap: { horizontal: 12 },
              border_radius: 0,
            },
          },
        })}
      />,
    );

    const outer = screen.getByTestId(`home-section-${liveSection.id}`);
    const inner = screen.getByTestId(
      `home-section-inner-${liveSection.id}`,
    );
    expect(outer).toHaveClass("mb-8");
    expect(outer).toHaveStyle({ marginTop: "12px", marginBottom: "12px" });
    expect(inner).toHaveClass("rounded-xl", "px-1", "py-4", "sm:px-6");
    expect(inner).not.toHaveStyle({
      paddingTop: "0px",
      paddingBottom: "0px",
      borderRadius: "0px",
    });
  });

  it("keeps the original banner-card radius when radius config is absent", () => {
    render(
      <HomeSectionRenderer
        section={sectionOf("banners", {
          items: [{ id: 1, image: "banner.jpg", link_type: "none" }],
        })}
      />,
    );

    expect(screen.getByRole("img").closest(".rounded-lg")).not.toBeNull();
  });
});
