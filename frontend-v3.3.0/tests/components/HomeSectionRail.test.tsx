import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, act } from "@testing-library/react";

/**
 * Covers the per-rail paging contract on the homepage.
 *
 * The /home-layout payload inlines only `config.limit` products per section, so
 * a rail that filled its limit is a truncated view of a longer list. Swiping
 * toward the end of such a rail MUST pull the next page from
 * /home-layout/sections/{id}; a rail that did NOT fill its limit already holds
 * everything and must never issue a request.
 */

const { getHomeLayoutSection } = vi.hoisted(() => ({
  getHomeLayoutSection: vi.fn(),
}));

vi.mock("@/routes/api", () => ({ getHomeLayoutSection }));

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

vi.mock("next/link", () => ({
  default: ({ children, ...rest }: any) => <a {...rest}>{children}</a>,
}));

/**
 * Swiper stand-in that captures `onSlideChange` so a test can simulate the user
 * swiping to the end of the rail — that gesture is the only paging trigger.
 */
let fireSlideChange: ((swiper: unknown) => void) | undefined;

vi.mock("swiper/react", () => ({
  Swiper: ({ children, onSlideChange }: any) => {
    fireSlideChange = onSlideChange;
    return <div data-testid="swiper">{children}</div>;
  },
  SwiperSlide: ({ children }: any) => <div>{children}</div>,
}));

vi.mock("swiper/modules", () => ({ Navigation: {}, Autoplay: {} }));
vi.mock("@/components/SwiperNavigation", () => ({ default: () => null }));
vi.mock("@/components/SectionHeading", () => ({
  default: ({ title }: any) => <h2>{title}</h2>,
}));
vi.mock("@/components/Cards/ProductCard", () => ({
  default: ({ product }: any) => (
    <div data-testid="product-card">{product.title}</div>
  ),
}));
vi.mock("@/components/Skeletons/ProductCardSkeleton", () => ({
  default: () => <div data-testid="product-skeleton" />,
}));

import HomeSectionRenderer from "@/views/homePage/HomeSectionRenderer";
import { HomeLayoutSection } from "@/types/ApiResponse";
import homeLayoutResponse from "../fixtures/homeLayoutResponse.json";

/** A real home-layout product (variants and all) to clone into test pages. */
const template = (homeLayoutResponse as any).data.sections[0].content
  .products[0];

const product = (id: number) => ({
  ...JSON.parse(JSON.stringify(template)),
  id,
  title: `Product ${id}`,
});

const section = (
  limit: number | undefined,
  productIds: number[],
): HomeLayoutSection =>
  ({
    id: 67,
    type: "products",
    title: "Daily Snacks",
    style: "without_background",
    config: {
      source: "newly_added",
      limit,
      background_type: "none",
      background_color: null,
    },
    background_image: null,
    hero_media: null,
    card_background_image: null,
    content: { products: productIds.map(product) },
  }) as unknown as HomeLayoutSection;

const feedPage = (productIds: number[], total: number) => ({
  success: true,
  message: "ok",
  data: {
    current_page: 2,
    last_page: 2,
    per_page: 3,
    total,
    data: productIds.map(product),
  },
});

/** Simulate a swipe that lands at the end of the rail. */
const swipeToEnd = async () => {
  await act(async () => {
    fireSlideChange?.({
      params: { slidesPerView: 5 },
      activeIndex: 50,
      slides: { length: 50 },
    });
  });
};

const cardTitles = () =>
  screen.queryAllByTestId("product-card").map((el) => el.textContent);

beforeEach(() => {
  getHomeLayoutSection.mockReset();
  fireSlideChange = undefined;
});

afterEach(() => {
  document.cookie = "userLocation=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
});

describe("home rail paging", () => {
  it("appends the next page when the rail is swiped to its end", async () => {
    getHomeLayoutSection.mockResolvedValue(feedPage([4, 5], 5));

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    expect(cardTitles()).toHaveLength(3);

    await swipeToEnd();

    await waitFor(() => expect(cardTitles()).toHaveLength(5));
    expect(cardTitles()).toEqual([
      "Product 1",
      "Product 2",
      "Product 3",
      "Product 4",
      "Product 5",
    ]);
  });

  it("resumes at page 2 with config.limit as the page size", async () => {
    getHomeLayoutSection.mockResolvedValue(feedPage([4], 4));

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    await swipeToEnd();

    await waitFor(() => expect(getHomeLayoutSection).toHaveBeenCalledTimes(1));
    const [id, params] = getHomeLayoutSection.mock.calls[0];
    expect(id).toBe(67);
    expect(params).toMatchObject({ page: 2, per_page: 3 });
  });

  it("sends the user's location with the request", async () => {
    document.cookie = `userLocation=${encodeURIComponent(
      JSON.stringify({ lat: "23.2", lng: "69.6" }),
    )}; path=/`;
    getHomeLayoutSection.mockResolvedValue(feedPage([4], 4));

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    await swipeToEnd();

    await waitFor(() => expect(getHomeLayoutSection).toHaveBeenCalled());
    expect(getHomeLayoutSection.mock.calls[0][1]).toMatchObject({
      latitude: "23.2",
      longitude: "69.6",
    });
  });

  it("never requests a rail that did not fill its limit", async () => {
    render(<HomeSectionRenderer section={section(12, [1, 2, 3])} />);
    await swipeToEnd();

    expect(getHomeLayoutSection).not.toHaveBeenCalled();
  });

  it("never requests a rail the backend sent no limit for", async () => {
    render(<HomeSectionRenderer section={section(undefined, [1, 2, 3])} />);
    await swipeToEnd();

    expect(getHomeLayoutSection).not.toHaveBeenCalled();
  });

  it("drops items the page repeats from what is already on screen", async () => {
    // The feed re-orders within a page, so a boundary can return a product the
    // inline payload already shipped — rendering it twice would duplicate a key.
    getHomeLayoutSection.mockResolvedValue(feedPage([3, 4], 4));

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    await swipeToEnd();

    await waitFor(() => expect(cardTitles()).toHaveLength(4));
    expect(cardTitles()).toEqual([
      "Product 1",
      "Product 2",
      "Product 3",
      "Product 4",
    ]);
  });

  it("stops paging once the rail holds the reported total", async () => {
    getHomeLayoutSection.mockResolvedValue(feedPage([4], 4));

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    await swipeToEnd();
    await waitFor(() => expect(cardTitles()).toHaveLength(4));

    await swipeToEnd();
    expect(getHomeLayoutSection).toHaveBeenCalledTimes(1);
  });

  it("stops paging when a page comes back empty", async () => {
    getHomeLayoutSection.mockResolvedValue(feedPage([], 99));

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    await swipeToEnd();
    await waitFor(() => expect(getHomeLayoutSection).toHaveBeenCalledTimes(1));

    await swipeToEnd();
    expect(getHomeLayoutSection).toHaveBeenCalledTimes(1);
  });

  it("keeps the rail intact when the request fails", async () => {
    getHomeLayoutSection.mockResolvedValue({
      success: false,
      message: "An error occurred.",
      data: { current_page: 0, data: [], per_page: 0, total: 0 },
    });

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    await swipeToEnd();

    await waitFor(() => expect(getHomeLayoutSection).toHaveBeenCalledTimes(1));
    expect(cardTitles()).toHaveLength(3);

    // A failed page ends paging rather than retrying on every further swipe.
    await swipeToEnd();
    expect(getHomeLayoutSection).toHaveBeenCalledTimes(1);
  });

  it("issues one request even if the end is reached repeatedly mid-flight", async () => {
    let release: (value: unknown) => void = () => {};
    getHomeLayoutSection.mockReturnValue(
      new Promise((resolve) => {
        release = resolve;
      }),
    );

    render(<HomeSectionRenderer section={section(3, [1, 2, 3])} />);
    await swipeToEnd();
    await swipeToEnd();
    await swipeToEnd();

    expect(getHomeLayoutSection).toHaveBeenCalledTimes(1);

    // Placeholder slides stand in for the page while it is in flight.
    expect(screen.getAllByTestId("product-skeleton").length).toBeGreaterThan(0);

    await act(async () => {
      release(feedPage([4], 4));
    });

    await waitFor(() =>
      expect(screen.queryByTestId("product-skeleton")).not.toBeInTheDocument(),
    );
    expect(cardTitles()).toHaveLength(4);
  });
});
