import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, waitFor } from "@testing-library/react";

/**
 * Covers the homepage's refresh contract:
 *  - switching the category tab MUST hit /home-layout with the NEW category
 *  - the hidden #home-sections-refetch button (clicked on location change)
 *    MUST also hit the API
 *
 * CategoryTabs navigates with `shallow: true`, so getServerSideProps never
 * re-runs — the client is solely responsible for refreshing the sections.
 */

const { getHomeLayout } = vi.hoisted(() => ({ getHomeLayout: vi.fn() }));

vi.mock("@/routes/api", () => ({ getHomeLayout }));

// Mutable router stand-in so we can simulate a shallow query change.
// `isReady`/`replace`/`push` back the empty state's reset actions.
const routerMock: {
  query: Record<string, string>;
  isReady: boolean;
  pathname: string;
  replace: ReturnType<typeof vi.fn>;
  push: ReturnType<typeof vi.fn>;
} = {
  query: {},
  isReady: true,
  pathname: "/",
  replace: vi.fn(),
  push: vi.fn(),
};
vi.mock("next/router", () => ({ useRouter: () => routerMock }));

vi.mock("@/components/Functional/InfiniteScroll", () => ({
  default: ({ children }: any) => <div>{children}</div>,
}));

// Partially mock: keep the module intact (the real i18n.ts needs
// initReactI18next) and stub only the hook, so `t()` yields the key.
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

vi.mock("@/components/Skeletons/ProductCardSkeleton", () => ({
  default: () => <div data-testid="skeleton" />,
}));

vi.mock("@/views/homePage/HomeSectionRenderer", () => ({
  default: ({ section }: any) => (
    <div data-testid="section">{String(section.id)}</div>
  ),
}));

vi.mock("@/views/homePage/HomeHeroSection", () => ({
  default: ({ section }: any) => (
    <div data-testid="home-hero">{String(section.id)}</div>
  ),
  HomeHeroSkeleton: () => <div data-testid="home-hero-skeleton" />,
}));

import { SWRConfig } from "swr";
import HomeLayoutView from "@/views/homePage/HomeLayoutView";

/**
 * SWR's cache is module-global, so without an isolated provider one test's
 * cached response leaks into the next and silently overwrites initialData.
 */
const renderView = (initialLayout: any) => {
  const value = { provider: () => new Map() };
  const wrapper = ({ children }: any) => (
    <SWRConfig value={value}>{children}</SWRConfig>
  );
  const view = render(<HomeLayoutView initialLayout={initialLayout} />, {
    wrapper,
  });
  return {
    ...view,
    // re-render through the same wrapper so the cache persists within a test
    switchTo: (layout: any) =>
      view.rerender(<HomeLayoutView initialLayout={layout} />),
  };
};

const layoutResponse = (ids: number[] = []) => ({
  success: true,
  message: "ok",
  data: {
    scope_type: "global",
    scope_id: null,
    current_page: 1,
    last_page: 1,
    per_page: 5,
    total: ids.length,
    sections: ids.map((id) => ({ id, type: "products", content: {} })),
  },
});

const setUrl = (search: string) =>
  window.history.pushState({}, "", `/${search}`);

/** The last category_slug the API was called with. */
const lastCategory = () => {
  const calls = getHomeLayout.mock.calls;
  return calls[calls.length - 1]?.[0]?.category_slug;
};

beforeEach(() => {
  getHomeLayout.mockReset();
  getHomeLayout.mockResolvedValue(layoutResponse([]));
  routerMock.query = {};
  routerMock.isReady = true;
  routerMock.replace.mockReset();
  routerMock.push.mockReset();
  setUrl("");
  // Mirror production: SSR mode means no fetch on mount (SSR seeds the data).
  process.env.NEXT_PUBLIC_SSR = "true";
  document.cookie =
    "homeCategory=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
});

const initial = () => layoutResponse([41]).data;

describe("HomeLayoutView — category tab switching", () => {
  it("does NOT refetch on mount (SSR already supplied the sections)", async () => {
    const { getAllByTestId } = renderView(initial());

    expect(getAllByTestId("section")).toHaveLength(1);
    await new Promise((r) => setTimeout(r, 50));
    expect(getHomeLayout).not.toHaveBeenCalled();
  });

  it("promotes the live `type: hero` section above the ordinary rails", () => {
    const layout = {
      ...layoutResponse([41]).data,
      sections: [
        { id: 41, type: "products", content: {} },
        {
          id: 128,
          type: "hero",
          style: "full",
          config: { media_type: "image", layout: "items" },
          hero_media: "https://example.test/hero.jpg",
          content: { items: [] },
        },
      ],
    };

    const { getByTestId, getAllByTestId } = renderView(layout);

    expect(getByTestId("home-hero")).toHaveTextContent("128");
    expect(getAllByTestId("section")).toHaveLength(1);
  });

  it("calls the API with the new category on every tab switch", async () => {
    const { switchTo } = renderView(initial());
    expect(getHomeLayout).not.toHaveBeenCalled();

    // Tab -> Beauty (shallow nav: query + URL change, no new props)
    routerMock.query = { category: "beauty-1" };
    setUrl("?category=beauty-1");
    switchTo(initial());

    await waitFor(() => expect(getHomeLayout).toHaveBeenCalledTimes(1));
    expect(lastCategory()).toBe("beauty-1");

    // Tab -> Grocery: must call again with the NEW scope (the reported bug was
    // it staying pinned to the first category).
    routerMock.query = { category: "grocery-1" };
    setUrl("?category=grocery-1");
    switchTo(initial());

    await waitFor(() => expect(lastCategory()).toBe("grocery-1"));

    // Tab -> All: category_slug is dropped entirely
    routerMock.query = {};
    setUrl("");
    switchTo(initial());

    await waitFor(() => expect(lastCategory()).toBeUndefined());
  });

  it("does not refetch when re-rendering with the same category", async () => {
    routerMock.query = { category: "beauty-1" };
    setUrl("?category=beauty-1");

    const { switchTo } = renderView(initial());
    await new Promise((r) => setTimeout(r, 50));
    const callsAfterMount = getHomeLayout.mock.calls.length;

    switchTo(initial());
    await new Promise((r) => setTimeout(r, 50));

    expect(getHomeLayout.mock.calls.length).toBe(callsAfterMount);
  });

  it("keeps the previous sections stable while a category switch is in flight", async () => {
    // Hold the request open so we can observe the loading state.
    let release: (v: any) => void = () => {};
    getHomeLayout.mockImplementation(
      () => new Promise((res) => (release = res)),
    );

    const { switchTo, queryAllByTestId, getAllByTestId } = renderView(initial());

    // Settled: previous category's section is on screen, no skeletons.
    expect(getAllByTestId("section")).toHaveLength(1);
    expect(queryAllByTestId("home-section-skeleton")).toHaveLength(0);

    // Switch tab -> in-flight: retain the existing layout so the hero cannot
    // collapse before the replacement category payload arrives.
    routerMock.query = { category: "grocery-1" };
    setUrl("?category=grocery-1");
    switchTo(initial());

    await waitFor(() => expect(getHomeLayout).toHaveBeenCalledTimes(1));
    expect(queryAllByTestId("home-section-skeleton")).toHaveLength(0);
    expect(getAllByTestId("section")).toHaveLength(1);

    // Resolve -> the old section swaps for the new one without an intermediate
    // zero-height/skeleton state.
    release(layoutResponse([99]));
    await waitFor(() =>
      expect(queryAllByTestId("home-section-skeleton")).toHaveLength(0),
    );
    expect(getAllByTestId("section")).toHaveLength(1);
  });

  it("does not replace an existing rail with a placeholder during refresh", async () => {
    getHomeLayout.mockImplementation(() => new Promise(() => {}));

    const { switchTo, getAllByTestId, queryAllByTestId } = renderView(initial());

    routerMock.query = { category: "grocery-1" };
    setUrl("?category=grocery-1");
    switchTo(initial());

    await waitFor(() => expect(getHomeLayout).toHaveBeenCalledTimes(1));
    expect(getAllByTestId("section")).toHaveLength(1);
    expect(queryAllByTestId("home-section-skeleton")).toHaveLength(0);
  });

  it("shows an actionable empty state when a category has no sections", async () => {
    routerMock.query = { category: "beauty-1" };
    setUrl("?category=beauty-1");

    const { getByText, queryAllByTestId } = renderView(layoutResponse([]).data);

    // Nothing to render, so the user must get a message rather than a blank page.
    await waitFor(() => getByText("home.empty.title"));
    expect(queryAllByTestId("section")).toHaveLength(0);

    // ...and a way out: clearing the category scope back to "All".
    getByText("home.empty.view_all").click();
    expect(routerMock.replace).toHaveBeenCalledWith(
      { pathname: "/", query: {} },
      undefined,
      { shallow: true },
    );
  });

  it("does not show the empty state while sections are still loading", async () => {
    // Hold the request open: mid-flight is not the same as empty.
    getHomeLayout.mockImplementation(() => new Promise(() => {}));

    routerMock.query = { category: "grocery-1" };
    setUrl("?category=grocery-1");

    const { switchTo, queryByText, getAllByTestId, queryAllByTestId } =
      renderView(initial());

    routerMock.query = { category: "kids-2" };
    setUrl("?category=kids-2");
    switchTo(initial());

    await waitFor(() => expect(getHomeLayout).toHaveBeenCalledTimes(1));
    expect(queryByText("home.empty.title")).toBeNull();
    expect(getAllByTestId("section")).toHaveLength(1);
    expect(queryAllByTestId("home-section-skeleton")).toHaveLength(0);
  });

  it("does not show the empty state when the category has sections", async () => {
    const { queryByText, getAllByTestId } = renderView(initial());

    expect(getAllByTestId("section")).toHaveLength(1);
    expect(queryByText("home.empty.title")).toBeNull();
  });

  it("the hidden #home-sections-refetch button also hits the API (location change)", async () => {
    setUrl("?category=beauty-1");
    renderView(initial());

    // This is what helpers/events.ts does on a location change.
    document.getElementById("home-sections-refetch")?.click();

    await waitFor(() => expect(getHomeLayout).toHaveBeenCalled());
    expect(lastCategory()).toBe("beauty-1");
  });

  it("treats All as explicit even when the old category remains in the cookie", async () => {
    document.cookie = `homeCategory=${encodeURIComponent(JSON.stringify("beauty-1"))}; path=/`;
    setUrl("");
    renderView(initial());

    document.getElementById("home-sections-refetch")?.click();

    await waitFor(() => expect(getHomeLayout).toHaveBeenCalled());
    expect(lastCategory()).toBeUndefined();
  });
});

/**
 * Paging used to hang off a bottom-of-document scroll check, so the next page
 * was only requested once the user had scrolled past every section and reached
 * the footer. The marker now sits two sections from the end.
 */
describe("HomeLayoutView — when the next page is requested", () => {
  /** A page of `ids` out of a longer list, so hasMore stays true. */
  const partialLayout = (ids: number[], total: number) => ({
    ...layoutResponse(ids).data,
    total,
  });

  const renderedOrder = (container: HTMLElement) =>
    Array.from(
      container.querySelectorAll(
        '[data-testid="section"], [data-testid="load-more-sentinel"]',
      ),
    ).map((node) =>
      node.getAttribute("data-testid") === "load-more-sentinel"
        ? "marker"
        : node.textContent,
    );

  it("puts the marker two sections from the end", () => {
    const { container } = renderView(partialLayout([1, 2, 3, 4, 5], 15));

    expect(renderedOrder(container)).toEqual([
      "1",
      "2",
      "3",
      "marker",
      "4",
      "5",
    ]);
  });

  it("keeps the marker on screen when the page is shorter than the runway", () => {
    const { container } = renderView(partialLayout([1], 15));
    expect(renderedOrder(container)).toEqual(["marker", "1"]);
  });

  it("requests the next page when the marker scrolls into view", async () => {
    const observers: { fire: () => void; options?: IntersectionObserverInit }[] =
      [];

    vi.stubGlobal(
      "IntersectionObserver",
      class {
        targets: Element[] = [];
        constructor(
          private cb: IntersectionObserverCallback,
          options?: IntersectionObserverInit,
        ) {
          observers.push({
            options,
            fire: () =>
              this.cb(
                this.targets.map((target) => ({
                  target,
                  isIntersecting: true,
                })) as never,
                this as never,
              ),
          });
        }
        observe(target: Element) {
          this.targets.push(target);
        }
        unobserve() {}
        disconnect() {
          this.targets = [];
        }
        takeRecords() {
          return [];
        }
      },
    );

    try {
      renderView(partialLayout([1, 2, 3, 4, 5], 15));
      expect(getHomeLayout).not.toHaveBeenCalled();

      const observer = observers[observers.length - 1];

      // Extra runway on top of the two-section head start, so the page is
      // usually already in place by the time those sections are reached.
      expect(observer.options?.rootMargin).toBe("800px 0px");

      observer.fire();

      await waitFor(() => expect(getHomeLayout).toHaveBeenCalledTimes(1));
      expect(getHomeLayout.mock.calls[0][0]).toMatchObject({
        page: 2,
        per_page: 5,
      });
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it("stops observing once every section has been loaded", () => {
    const observed: Element[] = [];

    vi.stubGlobal(
      "IntersectionObserver",
      class {
        observe(target: Element) {
          observed.push(target);
        }
        unobserve() {}
        disconnect() {}
        takeRecords() {
          return [];
        }
      },
    );

    try {
      // total === the sections on screen: there is no further page to fetch.
      renderView(partialLayout([1, 2, 3], 3));
      expect(observed).toHaveLength(0);
    } finally {
      vi.unstubAllGlobals();
    }
  });
});
