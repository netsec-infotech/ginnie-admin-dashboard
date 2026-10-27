import {
  act,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { createInstance } from "i18next";
import { I18nextProvider } from "react-i18next";
import { SWRConfig } from "swr";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import BlogListingView, {
  getBlogPaginationItems,
  type BlogListingViewProps,
} from "@/views/Blog/BlogListingView";
import type {
  ApiBlogCategory,
  ApiBlogPost,
  BlogListResponse,
} from "@/types/blog";
import type { ApiResponse } from "@/types/ApiResponse";
import type { getBlogs } from "@/routes/api";
import en from "../../public/locales/en.json";
import hi from "../../public/locales/hi.json";
import ar from "../../public/locales/ar.json";

const router = vi.hoisted(() => ({
  query: {} as Record<string, string>,
  isReady: true,
  push: vi.fn(),
  replace: vi.fn(),
}));
const api = vi.hoisted(() => ({
  getBlogs: vi.fn(),
  getBlogEditorsPick: vi.fn(),
  getBlogCategories: vi.fn(),
}));
vi.mock("next/router", () => ({ useRouter: () => router }));
vi.mock("@/routes/api", () => api);

const categories: ApiBlogCategory[] = [
  {
    id: 1,
    name: "Food",
    slug: "food",
    description: "Food related blogs",
    blogs_count: 6,
  },
  { id: 2, name: "Shopping", slug: "shopping", blogs_count: 10 },
];
const posts: ApiBlogPost[] = Array.from({ length: 6 }, (_, index) => ({
  id: index + 1,
  is_editors_pick: index === 0,
  title: `Published story ${index + 1}`,
  slug: `published-story-${index + 1}`,
  short_description: `Admin excerpt ${index + 1}`,
  image: "https://hypermart.eshopweb.store/storage/6226/cheese-nachos.jpg",
  published_at: "2026-10-05T12:49:00+05:30",
  category: categories[0],
  tags: [],
  author: { id: 1, username: "hyperlocal", bio: "Official Hyperlocal profile" },
}));
type BlogParams = Parameters<typeof getBlogs>[0];
function pageResponse(
  page = 1,
  data = posts.slice((page - 1) * 4, page * 4),
  total = 6,
  lastPage = 2,
  perPage = 4,
): BlogListResponse {
  return {
    success: true,
    message: "Blogs retrieved successfully.",
    data: {
      current_page: page,
      editors_pick: page === 1 ? data.filter((post) => post.is_editors_pick) : [],
      data,
      total,
      last_page: lastPage,
      per_page: perPage,
    },
  };
}

const animationFrames = new Map<number, FrameRequestCallback>();
let nextFrame = 0;
function flushAnimationFrames() {
  const frames = [...animationFrames.values()];
  animationFrames.clear();
  frames.forEach((callback) => callback(0));
}
async function renderListing(
  language = "en",
  waitLoaded = true,
  props: BlogListingViewProps = {},
) {
  const i18n = createInstance();
  await i18n.init({
    lng: language,
    fallbackLng: "en",
    resources: {
      en: { translation: en },
      hi: { translation: hi },
      ar: { translation: ar },
    },
    interpolation: { escapeValue: false },
  });
  const cache = new Map();
  const view = () => (
    <SWRConfig value={{ provider: () => cache, dedupingInterval: 0 }}>
      <I18nextProvider i18n={i18n}>
        <BlogListingView {...props} />
      </I18nextProvider>
    </SWRConfig>
  );
  const result = render(view());
  const t = i18n.t.bind(i18n);
  if (waitLoaded)
    await waitFor(() =>
      expect(screen.queryAllByLabelText(t("blog.loading"))).toHaveLength(0),
    );
  return { ...result, rerenderListing: () => result.rerender(view()), t, i18n };
}

beforeEach(() => {
  api.getBlogEditorsPick.mockReset().mockResolvedValue(pageResponse(1, [posts[0]], 1, 1, 1));
  router.query = {};
  router.isReady = true;
  router.push.mockReset();
  router.replace.mockReset();
  api.getBlogs
    .mockReset()
    .mockImplementation(({ page = 1 }: BlogParams = {}) =>
      Promise.resolve(pageResponse(Number(page))),
    );
  api.getBlogCategories.mockReset().mockResolvedValue({
    success: true,
    message: "Categories retrieved successfully.",
    data: categories,
  });
  animationFrames.clear();
  nextFrame = 0;
  vi.stubGlobal("requestAnimationFrame", (callback: FrameRequestCallback) => {
    const frame = ++nextFrame;
    animationFrames.set(frame, callback);
    return frame;
  });
  vi.stubGlobal("cancelAnimationFrame", (frame: number) => {
    animationFrames.delete(frame);
  });
});
afterEach(() => vi.unstubAllGlobals());

describe("live blog discovery", () => {
  it.each(["en", "ar"])("slides through editors_pick and keeps the selected slide across pagination (%s)", async (language) => {
    const selected = [posts[2], posts[4]].map((post) => ({ ...post, is_editors_pick: false }));
    const pickResponse = pageResponse(1, [posts[0]], 6, 2, 6);
    pickResponse.data!.editors_pick = selected;
    api.getBlogEditorsPick.mockResolvedValue(pickResponse);
    const { container, rerenderListing, t } = await renderListing(language);
    await screen.findByRole("heading", { level: 2, name: selected[0].title });
    expect(screen.getByRole("heading", { level: 2, name: selected[1].title })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { level: 2, name: posts[0].title })).not.toBeInTheDocument();
    expect(screen.getAllByText(t("blog.featured"))).toHaveLength(3);
    expect(screen.getByRole("region", { name: t("blog.slider.label") }).querySelectorAll(".swiper-slide")).toHaveLength(2);
    expect(container.querySelector(".swiper-pagination")).toBeInTheDocument();
    expect(container.querySelector(".swiper")).toHaveAttribute("dir", language === "ar" ? "rtl" : "ltr");
    fireEvent.click(screen.getByRole("button", { name: t("blog.slider.goToSlide", { index: 2 }) }));
    act(() => flushAnimationFrames());
    await waitFor(() => expect(container.querySelector(".swiper-slide-active")).toHaveTextContent(selected[1].title));
    expect(screen.getByRole("heading", { level: 3, name: selected[0].title }).closest("article")).toHaveTextContent(t("blog.featured"));
    expect(screen.getByRole("heading", { level: 3, name: posts[0].title }).closest("article")).not.toHaveTextContent(t("blog.featured"));
    router.query = { page: "2" };
    rerenderListing();
    await screen.findByRole("heading", { name: posts[5].title });
    expect(screen.getByRole("heading", { level: 2, name: selected[0].title })).toBeInTheDocument();
    expect(screen.getByRole("heading", { level: 2, name: selected[1].title })).toBeInTheDocument();
    expect(api.getBlogEditorsPick).toHaveBeenCalledTimes(1);
    expect(screen.getByRole("region", { name: t("blog.slider.label") }).querySelectorAll(".swiper-slide")).toHaveLength(2);
    expect(container.querySelector(".swiper-slide-active")).toHaveTextContent(selected[1].title);
  });

  it("does not infer picks from regular articles when editors_pick is empty", async () => {
    const response = pageResponse(1, posts, 6, 1, 6);
    response.data!.editors_pick = [];
    api.getBlogEditorsPick.mockResolvedValue(response);
    const { t } = await renderListing();
    expect(screen.queryByText(t("blog.featured"))).not.toBeInTheDocument();
  });
  it("uses the live editor selection instead of the first article or an outdated initial pick", async () => {
    const livePosts = posts.map((post, index) => ({ ...post, is_editors_pick: index === 2 }));
    api.getBlogs.mockResolvedValue(pageResponse(1, livePosts, 6, 1, 6));
    api.getBlogEditorsPick.mockResolvedValue(pageResponse(1, [livePosts[2]], 1, 1, 1));
    const { t } = await renderListing("en", true, {
      initialBlogs: pageResponse(1, posts, 6, 1, 6),
    });
    await act(async () => { flushAnimationFrames(); });
    await waitFor(() => expect(screen.getByRole("heading", { level: 2, name: "Published story 3" })).toBeInTheDocument());
    expect(screen.queryByRole("heading", { level: 2, name: "Published story 1" })).not.toBeInTheDocument();
    expect(screen.getAllByText(t("blog.featured"))).toHaveLength(2);
    expect(screen.queryByRole("region", { name: t("blog.slider.label") })).not.toBeInTheDocument();
    expect(screen.getAllByRole("heading", { name: "Published story 3" })).toHaveLength(2);
  });

  it("keeps the unique editor pick unchanged when the article page changes", async () => {
    const { rerenderListing } = await renderListing();
    api.getBlogs.mockResolvedValue(pageResponse(2, [
      { ...posts[4], is_editors_pick: true },
      { ...posts[5], is_editors_pick: false },
    ]));
    router.query = { page: "2" };
    rerenderListing();
    await screen.findByRole("heading", { level: 3, name: "Published story 5" });
    expect(screen.getByRole("heading", { level: 2, name: "Published story 1" })).toBeInTheDocument();
    expect(api.getBlogEditorsPick).toHaveBeenCalledTimes(1);
    expect(api.getBlogs).toHaveBeenLastCalledWith({ page: 2, per_page: 6 });
  });

  it("does not invent an editor pick when no article is marked", async () => {
    api.getBlogEditorsPick.mockResolvedValue(pageResponse(1, [], 0, 1, 1));
    api.getBlogs.mockResolvedValue(pageResponse(1, posts.map((post) => ({ ...post, is_editors_pick: false })), 6, 1, 6));
    const { container, t } = await renderListing();
    expect(screen.queryByText(t("blog.featured"))).not.toBeInTheDocument();
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] article')).toHaveLength(6);
  });
  it("preserves six server records per page and shows each story exactly once", async () => {
    const allPosts = Array.from({ length: 17 }, (_, index) => ({
      ...posts[0],
      id: index + 1,
      slug: `grid-story-${index + 1}`,
      title: `Grid story ${index + 1}`,
    }));
    api.getBlogEditorsPick.mockResolvedValue(pageResponse(1, [allPosts[0]], 1, 1, 1));
    api.getBlogs.mockImplementation(
      ({ page = 1, per_page = 6 }: BlogParams = {}) => {
        const currentPage = Number(page);
        const perPage = Number(per_page);
        return Promise.resolve(
          pageResponse(
            currentPage,
            allPosts.slice((currentPage - 1) * perPage, currentPage * perPage),
            allPosts.length,
            Math.ceil(allPosts.length / perPage),
            perPage,
          ),
        );
      },
    );
    const { container, rerenderListing, t } = await renderListing();
    const seen = Array.from(
      container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]'),
      (link) => link.getAttribute("href"),
    );
    expect(seen).toHaveLength(6);
    expect(
      container.querySelectorAll(
        'section[aria-labelledby="blog-results-heading"] article',
      ),
    ).toHaveLength(6);
    expect(screen.getByRole("status")).toHaveTextContent(
      t("blog.pagination.summary", { from: 1, to: 6, total: 17 }),
    );
    router.query = { page: "2" };
    rerenderListing();
    await screen.findByRole("heading", { name: "Grid story 7" });
    const secondPage = Array.from(
      container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]'),
      (link) => link.getAttribute("href"),
    );
    expect(secondPage).toHaveLength(6);
    seen.push(...secondPage);
    expect(screen.getByRole("status")).toHaveTextContent(
      t("blog.pagination.summary", { from: 7, to: 12, total: 17 }),
    );
    router.query = { page: "3" };
    rerenderListing();
    await screen.findByRole("heading", { name: "Grid story 13" });
    seen.push(
      ...Array.from(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]'), (link) =>
        link.getAttribute("href"),
      ),
    );
    expect(seen).toHaveLength(17);
    expect(new Set(seen).size).toBe(17);
    expect(
      screen.getByRole("button", { name: t("blog.pagination.next") }),
    ).toBeDisabled();
  });

  it("refetches articles and categories after an in-place locale change without English fallback leakage", async () => {
    let resolveHindiBlogs!: (response: BlogListResponse) => void;
    let resolveHindiCategories!: (
      response: ApiResponse<ApiBlogCategory[]>,
    ) => void;
    const english = pageResponse(1, [posts[0]], 1, 1);
    api.getBlogEditorsPick.mockResolvedValueOnce(english).mockReturnValue(new Promise(() => {}));
    api.getBlogs.mockResolvedValueOnce(english).mockReturnValue(
      new Promise<BlogListResponse>((resolve) => {
        resolveHindiBlogs = resolve;
      }),
    );
    api.getBlogCategories
      .mockResolvedValueOnce({ success: true, data: categories })
      .mockReturnValue(
        new Promise<ApiResponse<ApiBlogCategory[]>>((resolve) => {
          resolveHindiCategories = resolve;
        }),
      );
    const { i18n } = await renderListing("en", true, {
      initialBlogs: english,
      initialCategories: categories,
    });
    act(() => flushAnimationFrames());
    await waitFor(() => {
      expect(api.getBlogs).toHaveBeenCalledTimes(1);
      expect(api.getBlogCategories).toHaveBeenCalledTimes(1);
    });
    expect(
      screen.getByRole("heading", { level: 3, name: posts[0].title }),
    ).toBeInTheDocument();

    await act(async () => {
      await i18n.changeLanguage("hi");
    });
    await waitFor(() => {
      expect(api.getBlogs).toHaveBeenCalledTimes(2);
      expect(api.getBlogCategories).toHaveBeenCalledTimes(2);
    });
    expect(
      screen.queryByRole("heading", { level: 3, name: posts[0].title }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Food" }),
    ).not.toBeInTheDocument();

    const hindiCategory = { ...categories[0], name: "भोजन" };
    await act(async () => {
      resolveHindiBlogs(
        pageResponse(
          1,
          [{ ...posts[0], title: "हिंदी कहानी", category: hindiCategory }],
          1,
          1,
        ),
      );
      resolveHindiCategories({
        success: true,
        message: "",
        data: [hindiCategory],
      });
    });
    expect(
      await screen.findByRole("heading", { name: "हिंदी कहानी" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "भोजन" })).toBeInTheDocument();
  });

  it("renders SSR content immediately and never reuses it for another request key", async () => {
    api.getBlogs.mockReturnValue(new Promise(() => {}));
    api.getBlogCategories.mockReturnValue(new Promise(() => {}));
    const { container, rerenderListing } = await renderListing("en", false, {
      initialBlogs: pageResponse(),
      initialCategories: categories,
    });
    expect(
      screen.getByRole("heading", { level: 3, name: "Published story 1" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Food" })).toBeInTheDocument();
    router.query = { page: "2" };
    rerenderListing();
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    expect(container.querySelector('[aria-busy="true"]')).toBeInTheDocument();
  });

  it("does not seed a filtered URL with the unfiltered static index", async () => {
    router.query = { search: "specific food" };
    api.getBlogs.mockReturnValue(new Promise(() => {}));
    const { container } = await renderListing("en", false, {
      initialBlogs: pageResponse(),
      initialCategories: categories,
      initialQueryKey: JSON.stringify(["", "", "", "", ""]),
    });
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    expect(screen.queryByRole("heading", { level: 3, name: "Published story 1" })).not.toBeInTheDocument();
    expect(api.getBlogs).toHaveBeenCalledWith({ page: 1, per_page: 6, search: "specific food" });
  });

  it("renders API summaries and independently fetched topics without preview articles", async () => {
    const { container, t } = await renderListing();
    expect(api.getBlogs).toHaveBeenCalledWith({ page: 1, per_page: 6 });
    expect(api.getBlogCategories).toHaveBeenCalledTimes(1);
    expect(
      screen.getByRole("heading", { level: 3, name: "Published story 1" }),
    ).toBeInTheDocument();
    expect(screen.getAllByText("Admin excerpt 1")).toHaveLength(2);
    expect(
      screen.getByRole("button", { name: "Shopping" }),
    ).toBeInTheDocument();
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(4);
    expect(container.textContent).not.toContain(t("blog.previewLabel"));
    expect(container.textContent).not.toContain(
      "Fresh produce, fewer forgotten ingredients",
    );
  });

  it("fetches only the requested server page and does not repeat the featured article", async () => {
    const { container, rerenderListing, t } = await renderListing();
    expect(
      screen.getByRole("button", { name: t("blog.pagination.previous") }),
    ).toBeDisabled();
    fireEvent.click(
      screen.getByRole("button", { name: t("blog.pagination.next") }),
    );
    expect(router.push).toHaveBeenCalledWith(
      { pathname: "/blogs/", query: { page: "2" } },
      undefined,
      { shallow: true, scroll: false },
    );
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    router.query = { page: "2" };
    rerenderListing();
    await screen.findByRole("heading", { name: "Published story 5" });
    expect(api.getBlogs).toHaveBeenLastCalledWith({ page: 2, per_page: 6 });
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(2);
    expect(
      screen.getByRole("button", {
        name: t("blog.pagination.pageLabel", { page: 2 }),
      }),
    ).toHaveAttribute("aria-current", "page");
    expect(
      screen.getByRole("button", { name: t("blog.pagination.next") }),
    ).toBeDisabled();
    expect(screen.getByRole("status")).toHaveTextContent(
      t("blog.pagination.summary", { from: 5, to: 6, total: 6 }),
    );
    fireEvent.click(
      screen.getByRole("button", { name: t("blog.pagination.previous") }),
    );
    expect(router.push).toHaveBeenLastCalledWith(
      { pathname: "/blogs/", query: {} },
      undefined,
      { shallow: true, scroll: false },
    );
  });

  it("uses total, last_page and per_page from the API rather than the visible array", async () => {
    router.query = { page: "2" };
    api.getBlogs.mockResolvedValue(pageResponse(2, [posts[0]], 35, 5, 8));
    const { container, t } = await renderListing();
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(1);
    expect(screen.getByRole("status")).toHaveTextContent(
      t("blog.pagination.summary", { from: 9, to: 9, total: 35 }),
    );
    expect(
      screen.getByRole("button", {
        name: t("blog.pagination.pageLabel", { page: 5 }),
      }),
    ).toBeInTheDocument();
  });

  it("visually selects an ID-only topic filter consistently with its pressed state", async () => {
    router.query = { category_id: "1" };
    const { t } = await renderListing();
    const selectedTopic = screen.getByRole("button", { name: "Food" });
    const allTopics = screen.getByRole("button", { name: t("blog.allTopics") });
    const otherTopic = screen.getByRole("button", { name: "Shopping" });
    expect(selectedTopic).toHaveAttribute("aria-pressed", "true");
    expect(selectedTopic).toHaveClass("bg-foreground", "text-background");
    expect(allTopics).toHaveAttribute("aria-pressed", "false");
    expect(allTopics).not.toHaveClass("bg-foreground");
    expect(otherTopic).toHaveAttribute("aria-pressed", "false");
    expect(otherTopic).not.toHaveClass("bg-foreground");
  });

  it("shows a single published featured story without an empty-collection message", async () => {
    api.getBlogs.mockResolvedValue(pageResponse(1, [posts[0]], 1, 1));
    const { container, t } = await renderListing();
    expect(
      screen.getByRole("heading", { level: 3, name: "Published story 1" }),
    ).toBeInTheDocument();
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(1);
    expect(
      screen.queryByRole("heading", { name: t("blog.emptyTitle") }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("heading", { name: t("blog.noResultsTitle") }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("navigation", { name: t("blog.pagination.label") }),
    ).not.toBeInTheDocument();
    expect(screen.getByRole("status")).toHaveTextContent(
      t("blog.pagination.summary", { from: 1, to: 1, total: 1 }),
    );
  });

  it("passes category, ID, tag and keyword filters to the API without locally filtering its response", async () => {
    router.query = {
      category: "unknown-topic",
      category_id: "3",
      tag: "vegan",
      search: "recipes",
    };
    api.getBlogs.mockResolvedValue(pageResponse(1, [posts[0]], 1, 1));
    const { t } = await renderListing();
    expect(api.getBlogs).toHaveBeenCalledWith({
      page: 1,
      per_page: 6,
      category_slug: "unknown-topic",
      category_id: "3",
      tag: "vegan",
      search: "recipes",
    });
    expect(
      screen.getByRole("heading", { level: 3, name: "Published story 1" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: t("blog.allTopics") }),
    ).toHaveAttribute("aria-pressed", "false");
  });

  it("retains the category request while the independent topics catalog is still loading", async () => {
    router.query = { category_slug: "food" };
    let resolveCategories!: (value: ApiResponse<ApiBlogCategory[]>) => void;
    api.getBlogCategories.mockReturnValue(
      new Promise((resolve) => {
        resolveCategories = resolve;
      }),
    );
    const { t } = await renderListing("en", false);
    await waitFor(() =>
      expect(api.getBlogs).toHaveBeenCalledWith({
        page: 1,
        per_page: 6,
        category_slug: "food",
      }),
    );
    expect(
      screen.getByRole("button", { name: t("blog.allTopics") }),
    ).toHaveAttribute("aria-pressed", "false");
    await act(async () =>
      resolveCategories({ success: true, message: "", data: categories }),
    );
    expect(screen.getByRole("button", { name: "Food" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
  });

  it("hides old clickable results immediately while debouncing a changed search", async () => {
    router.query = { page: "2", category: "food", tag: "vegan" };
    const { container } = await renderListing();
    fireEvent.change(screen.getByRole("searchbox"), {
      target: { value: "fresh" },
    });
    expect(screen.getByRole("searchbox")).toHaveValue("fresh");
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    expect(container.querySelector('[aria-busy="true"]')).toBeInTheDocument();
    await waitFor(() =>
      expect(router.replace).toHaveBeenCalledWith(
        {
          pathname: "/blogs/",
          query: { search: "fresh", category: "food", tag: "vegan" },
        },
        undefined,
        { shallow: true, scroll: false },
      ),
    );
  });

  it("returns to page one when selecting a topic and preserves tag filters", async () => {
    router.query = { page: "2", category_id: "1", tag: "vegan" };
    const { container } = await renderListing();
    fireEvent.click(screen.getByRole("button", { name: "Shopping" }));
    expect(router.push).toHaveBeenCalledWith(
      { pathname: "/blogs/", query: { category: "shopping", tag: "vegan" } },
      undefined,
      { shallow: true, scroll: false },
    );
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
  });

  it("preserves filters while moving between server pages", async () => {
    router.query = { category: "food", search: "recipes", tag: "vegan" };
    const { t } = await renderListing();
    fireEvent.click(
      screen.getByRole("button", { name: t("blog.pagination.next") }),
    );
    expect(router.push).toHaveBeenCalledWith(
      {
        pathname: "/blogs/",
        query: { category: "food", search: "recipes", tag: "vegan", page: "2" },
      },
      undefined,
      { shallow: true, scroll: false },
    );
  });

  it("shows a genuine loading skeleton before the first API response", async () => {
    let resolveBlogs!: (response: BlogListResponse) => void;
    api.getBlogs.mockReturnValue(
      new Promise((resolve) => {
        resolveBlogs = resolve;
      }),
    );
    const { container, t } = await renderListing("en", false);
    expect(container.querySelector('[aria-busy="true"]')).toHaveAttribute(
      "aria-label",
      t("blog.loading"),
    );
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    await act(async () => resolveBlogs(pageResponse()));
    expect(
      screen.getByRole("heading", { level: 3, name: "Published story 1" }),
    ).toBeInTheDocument();
  });

  it("shows a retryable API error without substituting sample stories", async () => {
    api.getBlogs.mockResolvedValueOnce({
      success: false,
      message: "Unavailable",
      data: null,
    });
    const { container, t } = await renderListing();
    expect(screen.getByRole("alert")).toHaveTextContent(
      t("blog.loadErrorTitle"),
    );
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    fireEvent.click(screen.getByRole("button", { name: t("blog.retry") }));
    await screen.findByRole("heading", { level: 3, name: "Published story 1" });
    expect(api.getBlogs).toHaveBeenCalledTimes(2);
  });

  it("recovers categories independently without reloading the blog page", async () => {
    api.getBlogCategories.mockResolvedValueOnce({
      success: false,
      message: "Unavailable",
      data: [],
    });
    const { t } = await renderListing();
    expect(
      screen.getByRole("heading", { level: 3, name: "Published story 1" }),
    ).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: t("blog.retry") }));
    await screen.findByRole("button", { name: "Food" });
    expect(api.getBlogs).toHaveBeenCalledTimes(1);
    expect(api.getBlogCategories).toHaveBeenCalledTimes(2);
  });

  it("renders the backend's empty published collection without invented stories", async () => {
    api.getBlogEditorsPick.mockResolvedValue(pageResponse(1, [], 0, 1, 1));
    api.getBlogs.mockResolvedValue(pageResponse(1, [], 0, 1));
    const { container, t } = await renderListing();
    expect(
      screen.getByRole("heading", { name: t("blog.emptyTitle") }),
    ).toBeInTheDocument();
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    expect(
      screen.queryByRole("button", { name: t("blog.resetFilters") }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("navigation", { name: t("blog.pagination.label") }),
    ).not.toBeInTheDocument();
  });

  it("resets every URL filter when recovering from an empty filtered result", async () => {
    router.query = { search: "missing", category: "food", tag: "vegan" };
    api.getBlogs.mockResolvedValue(pageResponse(1, [], 0, 1));
    const { t } = await renderListing();
    expect(
      screen.getByRole("heading", { name: t("blog.noResultsTitle") }),
    ).toBeInTheDocument();
    fireEvent.click(
      screen.getByRole("button", { name: t("blog.resetFilters") }),
    );
    expect(router.push).toHaveBeenCalledWith(
      { pathname: "/blogs/", query: {} },
      undefined,
      { shallow: true, scroll: false },
    );
  });

  it.each(["0", "-2", "1.5", "oops", "9007199254740993"])(
    "requests page one for an invalid page query: %s",
    async (page) => {
      router.query = { page };
      await renderListing();
      expect(api.getBlogs).toHaveBeenCalledWith({ page: 1, per_page: 6 });
    },
  );

  it("corrects an out-of-range URL using server metadata, then requests the last page", async () => {
    router.query = { page: "99", tag: "vegan" };
    const { container, rerenderListing } = await renderListing("en", false);
    await waitFor(() =>
      expect(router.replace).toHaveBeenCalledWith(
        { pathname: "/blogs/", query: { page: "2", tag: "vegan" } },
        undefined,
        { shallow: true, scroll: false },
      ),
    );
    expect(container.querySelectorAll('section[aria-labelledby="blog-results-heading"] a[href^="/blogs/"]')).toHaveLength(0);
    router.query = { page: "2", tag: "vegan" };
    rerenderListing();
    await screen.findByRole("heading", { name: "Published story 5" });
    expect(api.getBlogs).toHaveBeenLastCalledWith({
      page: 2,
      per_page: 6,
      tag: "vegan",
    });
  });

  it("preserves newer typing when an earlier clear navigation completes", async () => {
    router.query = { search: "coffee" };
    router.replace.mockReturnValue(new Promise(() => {}));
    const { rerenderListing, t } = await renderListing();
    fireEvent.click(
      screen.getByRole("button", { name: t("blog.clearSearch") }),
    );
    fireEvent.change(screen.getByRole("searchbox"), {
      target: { value: "tea" },
    });
    router.query = {};
    rerenderListing();
    expect(screen.getByRole("searchbox")).toHaveValue("tea");
  });

  it("preserves newer typing when a topic navigation completes", async () => {
    router.push.mockReturnValue(new Promise(() => {}));
    const { rerenderListing } = await renderListing();
    fireEvent.change(screen.getByRole("searchbox"), {
      target: { value: "cof" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Shopping" }));
    fireEvent.change(screen.getByRole("searchbox"), {
      target: { value: "coffee" },
    });
    router.query = { search: "cof", category: "shopping" };
    rerenderListing();
    expect(screen.getByRole("searchbox")).toHaveValue("coffee");
  });

  it("restores browser history even when a matching own update was pending", async () => {
    router.query = { search: "coffee" };
    router.replace.mockReturnValue(new Promise(() => {}));
    const { rerenderListing, t } = await renderListing();
    fireEvent.click(
      screen.getByRole("button", { name: t("blog.clearSearch") }),
    );
    fireEvent.change(screen.getByRole("searchbox"), {
      target: { value: "tea" },
    });
    act(() => window.dispatchEvent(new PopStateEvent("popstate")));
    router.query = {};
    rerenderListing();
    expect(screen.getByRole("searchbox")).toHaveValue("");
  });

  it("localizes pagination and results labels in Arabic without changing backend article content", async () => {
    router.query = { category: "food" };
    const { container, t } = await renderListing("ar");
    const page = new Intl.NumberFormat("ar").format(1);
    expect(
      screen.getByRole("button", {
        name: t("blog.pagination.pageLabel", { page }),
      }),
    ).toHaveTextContent(page);
    expect(
      screen.getByRole("heading", { name: t("blog.results", { count: 6 }) }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { level: 3, name: "Published story 1" }),
    ).toBeInTheDocument();
    expect(container.textContent).not.toContain("blog.");
  });

  it("waits for the router query before requesting a static export page", async () => {
    router.isReady = false;
    const { rerenderListing } = await renderListing("en", false);
    expect(api.getBlogs).not.toHaveBeenCalled();
    router.isReady = true;
    router.query = { page: "2" };
    rerenderListing();
    await screen.findByRole("heading", { name: "Published story 5" });
    expect(api.getBlogs).toHaveBeenCalledWith({ page: 2, per_page: 6 });
  });

  it("focuses the updated results only after a successful page navigation", async () => {
    let completeNavigation!: (completed: boolean) => void;
    router.push.mockReturnValue(
      new Promise<boolean>((resolve) => {
        completeNavigation = resolve;
      }),
    );
    const { rerenderListing, t } = await renderListing();
    const heading = screen.getByRole("heading", { name: t("blog.latest") });
    const scrollIntoView = vi.fn();
    heading.scrollIntoView = scrollIntoView;
    fireEvent.click(
      screen.getByRole("button", { name: t("blog.pagination.next") }),
    );
    router.query = { page: "2" };
    rerenderListing();
    await screen.findByRole("heading", { name: "Published story 5" });
    await act(async () => completeNavigation(true));
    act(flushAnimationFrames);
    expect(scrollIntoView).toHaveBeenCalledWith({ block: "start" });
    expect(heading).toHaveFocus();
  });

  it.each([false, "rejected"])(
    "does not move focus for a cancelled or failed navigation: %s",
    async (outcome) => {
      if (outcome === false) router.push.mockResolvedValue(false);
      else router.push.mockRejectedValue(new Error("Cancelled navigation"));
      const { t } = await renderListing();
      const heading = screen.getByRole("heading", { name: t("blog.latest") });
      fireEvent.click(
        screen.getByRole("button", { name: t("blog.pagination.next") }),
      );
      await act(async () => {});
      expect(animationFrames.size).toBe(0);
      expect(heading).not.toHaveFocus();
      expect(
        screen.getByRole("heading", { level: 3, name: "Published story 1" }),
      ).toBeInTheDocument();
    },
  );
});

describe("bounded blog pagination", () => {
  it("keeps every page visible for a short list", () =>
    expect(getBlogPaginationItems(5, 3)).toEqual([1, 2, 3, 4, 5]));
  it.each([1, 3, 4, 50, 98, 100])(
    "keeps page %i and both ends visible in a long list",
    (page) => {
      const items = getBlogPaginationItems(100, page);
      expect(items).toHaveLength(5);
      expect(items[0]).toBe(1);
      expect(items.at(-1)).toBe(100);
      expect(items).toContain(page);
      expect(new Set(items).size).toBe(items.length);
    },
  );
});
