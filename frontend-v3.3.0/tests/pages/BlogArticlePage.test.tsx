import {
  act,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { createInstance } from "i18next";
import type { GetStaticPathsContext } from "next";
import { I18nextProvider } from "react-i18next";
import { SWRConfig } from "swr";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import BlogArticlePage, {
  getStaticPaths,
  getStaticProps,
} from "@/pages/blogs/[slug]";
import { getStaticProps as getFallbackProps } from "@/pages/blog-fallback";
import { adaptBlogPost } from "@/helpers/blog";
import type { ApiBlogPost, BlogPost } from "@/types/blog";
import en from "../../public/locales/en.json";
import hi from "../../public/locales/hi.json";

const mocks = vi.hoisted(() => ({
  detail: vi.fn(),
  list: vi.fn(),
  settings: vi.fn(),
  snapshot: vi.fn(),
  cookie: vi.fn(),
  token: "",
  ssr: false,
  unsubscribe: vi.fn(),
  article: vi.fn(),
  seo: vi.fn(),
  router: { isReady: true, asPath: "/blogs/food-blog/" },
}));
vi.mock("next/router", () => ({ useRouter: () => mocks.router }));
vi.mock("@/services/staticGenerationService", () => ({
  getStaticGenerationContext: vi.fn(async () => ({ settings: [] })),
}));
vi.mock("@/services/blogStaticGenerationService", () => ({ readBlogStaticSnapshot: mocks.snapshot }));
vi.mock("@/routes/api", () => ({
  getBlogBySlug: mocks.detail,
  getBlogs: mocks.list,
  getSettings: mocks.settings,
}));
vi.mock("@/helpers/getters", () => ({ isSSR: () => mocks.ssr }));
vi.mock("@/helpers/events", () => ({
  LOCATION_CHANGED_EVENT: "hyperlocal:location-changed",
}));
vi.mock("@/helpers/seo", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/helpers/seo")>(),
  generateBreadcrumbSchema: vi.fn(() => ({})),
}));
vi.mock("@/lib/cookies", () => ({
  getCookie: mocks.cookie,
  subscribeToAuthSessionChanges: () => mocks.unsubscribe,
}));
vi.mock("react-redux", () => ({ useSelector: () => mocks.token }));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ defaultLocation: { lat: 20, lng: 70 } }),
}));
vi.mock("@/SEO/DynamicSEO", () => ({ default: mocks.seo }));
vi.mock("@/views/Blog/BlogArticleView", () => ({ default: mocks.article }));

const raw: ApiBlogPost = {
  id: 2,
  slug: "food-blog",
  title: "Food from the admin",
  short_description: "A published introduction",
  content: "<h2>Food</h2><p>Published article body</p>",
  published_at: "2026-10-06T12:00:00Z",
  author: {
    id: 1,
    username: "Admin",
    bio: "Food writer",
    photo_url: "/writer.jpg",
  },
  seo: {
    title: "Food SEO",
    description: "Food description",
    keywords: [null, "food", ""],
  },
  linked_products: [
    {
      id: 11,
      title: "Almond Milk",
      slug: "almond-milk",
      short_description: "Milk",
      main_image: "/milk.jpg",
      category_name: "Food",
      brand_name: "Milk brand",
      price: 40,
      special_price: 32,
      is_available: true,
      is_deliverable: null,
    },
  ],
};

async function renderPage(props: Parameters<typeof BlogArticlePage>[0]) {
  const i18n = createInstance();
  await i18n.init({
    lng: "en",
    resources: { en: { translation: en }, hi: { translation: hi } },
    interpolation: { escapeValue: false },
  });
  const result = render(
    <I18nextProvider i18n={i18n}>
      <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>
        <BlogArticlePage {...props} />
      </SWRConfig>
    </I18nextProvider>,
  );
  return { ...result, i18n };
}

describe("live blog detail routing", () => {
  afterEach(() => vi.unstubAllEnvs());
  beforeEach(() => {
    mocks.snapshot.mockReset().mockResolvedValue(null);
    mocks.detail.mockReset().mockResolvedValue({ success: true, data: raw });
    mocks.list.mockReset();
    mocks.settings.mockReset().mockResolvedValue({ success: true, data: [] });
    mocks.cookie.mockReset().mockReturnValue(null);
    mocks.article
      .mockReset()
      .mockImplementation(
        ({
          post,
          relatedPosts,
        }: {
          post: BlogPost;
          relatedPosts: BlogPost[];
        }) => (
          <article>
            <h1>{post.title}</h1>
            <div>{post.content}</div>
            <span>{relatedPosts.length}</span>
          </article>
        ),
      );
    mocks.seo.mockReset().mockReturnValue(null);
    mocks.token = "";
    mocks.ssr = false;
    mocks.router.isReady = true;
    mocks.router.asPath = "/blogs/food-blog/";
  });

  it("refetches localized article data without reusing English initial content after a language switch", async () => {
    let resolveHindi!: (response: {
      success: boolean;
      data: ApiBlogPost;
    }) => void;
    mocks.detail
      .mockResolvedValueOnce({ success: true, data: raw })
      .mockReturnValue(
        new Promise((resolve) => {
          resolveHindi = resolve;
        }),
      );
    const post = adaptBlogPost(raw)!;
    const { i18n } = await renderPage({
      slug: raw.slug,
      initialData: { post, relatedPosts: [], notFound: false },
    });
    await waitFor(() => expect(mocks.detail).toHaveBeenCalledTimes(1));
    expect(
      screen.getByRole("heading", { name: raw.title }),
    ).toBeInTheDocument();

    await act(async () => {
      await i18n.changeLanguage("hi");
    });
    await waitFor(() => expect(mocks.detail).toHaveBeenCalledTimes(2));
    expect(
      screen.queryByRole("heading", { name: raw.title }),
    ).not.toBeInTheDocument();
    await act(async () => {
      resolveHindi({
        success: true,
        data: { ...raw, title: "हिंदी लेख", content: "<p>हिंदी सामग्री</p>" },
      });
    });
    expect(
      await screen.findByRole("heading", { name: "हिंदी लेख" }),
    ).toBeInTheDocument();
    expect(screen.getByText("<p>हिंदी सामग्री</p>")).toBeInTheDocument();
  });

  it("generates actual published slugs across every listing page", async () => {
    mocks.list.mockResolvedValueOnce({
      success: true,
      data: {
        data: [raw],
        current_page: 1,
        per_page: 1,
        total: 2,
        last_page: 2,
      },
    });
    mocks.list.mockResolvedValueOnce({
      success: true,
      data: {
        data: [{ ...raw, id: 3, slug: "second-admin-post" }],
        current_page: 2,
        per_page: 1,
        total: 2,
        last_page: 2,
      },
    });
    expect(await getStaticPaths({} as GetStaticPathsContext)).toEqual({
      paths: [
        { params: { slug: "food-blog" } },
        { params: { slug: "second-admin-post" } },
      ],
      fallback: false,
    });
    expect(mocks.list).toHaveBeenNthCalledWith(2, { page: 2, per_page: 100 });
  });

  it("does not turn a failed build-time blog API into an empty set of routes", async () => {
    mocks.list.mockResolvedValue({ success: false });
    await expect(getStaticPaths({} as GetStaticPathsContext)).rejects.toThrow(
      "blog API is unavailable",
    );
  });

  it("uses the same build snapshot for static paths and article content without refetching details", async () => {
    vi.stubEnv("NODE_ENV", "production");
    mocks.snapshot.mockResolvedValue([raw]);
    mocks.detail.mockResolvedValue({ success: false });
    expect(await getStaticPaths({} as GetStaticPathsContext)).toEqual({ paths: [{ params: { slug: raw.slug } }], fallback: false });
    expect(await getStaticProps({ params: { slug: raw.slug }, revalidateReason: "build" })).toMatchObject({ props: { initialData: { post: { title: raw.title, content: raw.content } } } });
    expect(mocks.detail).not.toHaveBeenCalled();
    expect(mocks.list).not.toHaveBeenCalled();
  });

  it.each([false, true])("skips article prerendering from an empty snapshot in SSR mode %s", async (ssr) => {
    vi.stubEnv("NODE_ENV", "production");
    mocks.ssr = ssr;
    mocks.snapshot.mockResolvedValue([]);
    expect(await getStaticPaths({} as GetStaticPathsContext)).toEqual({
      paths: [], fallback: ssr ? "blocking" : false,
    });
    expect(mocks.list).not.toHaveBeenCalled();
    expect(mocks.detail).not.toHaveBeenCalled();
  });

  it("loads actual article content and only backend related posts into serializable static props", async () => {
    mocks.detail.mockResolvedValue({
      success: true,
      data: {
        ...raw,
        related_blogs: [
          raw,
          { ...raw, id: 3, slug: "related-admin-post", title: "Related" },
        ],
      },
    });
    const result = await getStaticProps({
      params: { slug: "food-blog" },
      revalidateReason: "build",
    });
    expect(result).toMatchObject({
      props: {
        slug: "food-blog",
        initialData: {
          post: {
            title: raw.title,
            content: raw.content,
            authorPhoto: "/writer.jpg",
            linkedProducts: [{ slug: "almond-milk" }],
          },
          relatedPosts: [{ slug: "related-admin-post", content: "", linkedProducts: [] }],
        },
      },
    });
    expect(JSON.parse(JSON.stringify(result))).toEqual(result);
  });

  it("returns a real 404 for an unknown or unpublished article", async () => {
    mocks.detail.mockResolvedValue({ success: false, notFound: true });
    expect(
      await getStaticProps({
        params: { slug: "missing" },
        revalidateReason: "build",
      }),
    ).toEqual({ notFound: true });
  });

  it("fails builds and stale ISR regeneration on transient failure while leaving initial requests recoverable", async () => {
    mocks.detail.mockResolvedValue({ success: false, notFound: false });
    await expect(
      getStaticProps({ params: { slug: raw.slug }, revalidateReason: "build" }),
    ).rejects.toThrow("blog API is unavailable");
    await expect(
      getStaticProps({
        params: { slug: raw.slug },
        revalidateReason: "stale",
      }),
    ).rejects.toThrow("blog API is unavailable");
    expect(await getStaticProps({ params: { slug: raw.slug } })).toEqual({
      props: { slug: raw.slug, initialError: true },
    });
  });

  it("exports a shared static article shell for posts published after deployment", async () => {
    expect(await getFallbackProps({})).toEqual({
      props: { slug: "", clientFallback: true, initialSettings: [] },
    });
  });

  it("resolves a new slug from the preserved public URL before fetching a static fallback article", async () => {
    mocks.router.asPath = "/blogs/newly-published/?campaign=journal";
    mocks.detail.mockResolvedValue({
      success: true,
      data: {
        ...raw,
        slug: "newly-published",
        title: "Newly published article",
      },
    });
    await renderPage({ slug: "", clientFallback: true });
    expect(
      await screen.findByRole("heading", { name: "Newly published article" }),
    ).toBeInTheDocument();
    expect(mocks.detail).toHaveBeenCalledWith({
      slug: "newly-published",
      latitude: 20,
      longitude: 70,
    });
    expect(mocks.detail.mock.calls.every(([params]) => params.slug)).toBe(true);
  });

  it("treats malformed fallback URLs as missing articles without requesting an invalid slug", async () => {
    mocks.router.asPath = "/blogs/%E0%A4%A/";
    await renderPage({ slug: "", clientFallback: true });
    expect(
      await screen.findByRole("heading", { name: en.blog.notFoundTitle }),
    ).toBeInTheDocument();
    expect(mocks.detail).not.toHaveBeenCalled();
  });

  it("keeps the known article and associated products during failed client hydration", async () => {
    mocks.detail.mockResolvedValue({ success: false, notFound: false });
    const post = adaptBlogPost(raw)!;
    await renderPage({
      slug: raw.slug,
      initialData: { post, relatedPosts: [], notFound: false },
    });
    await waitFor(() => expect(mocks.detail).toHaveBeenCalled());
    expect(
      screen.getByRole("heading", { name: raw.title }),
    ).toBeInTheDocument();
    expect(
      mocks.article.mock.calls.at(-1)?.[0].post.linkedProducts[0].slug,
    ).toBe("almond-milk");
    expect(mocks.seo.mock.calls.at(-1)?.[0]).toMatchObject({
      title: "Food SEO",
      keywords: "food",
      ogType: "article",
      jsonLd: expect.arrayContaining([
        expect.objectContaining({
          "@type": "BlogPosting",
          headline: raw.title,
          datePublished: raw.published_at,
          author: { "@type": "Person", name: "Admin" },
        }),
      ]),
    });
  });

  it("renders a retry action after transient client failure and recovers without sample content", async () => {
    mocks.detail
      .mockResolvedValueOnce({ success: false, notFound: false })
      .mockResolvedValue({ success: true, data: raw });
    await renderPage({ slug: raw.slug });
    const retry = await screen.findByRole("button", { name: en.blog.retry });
    fireEvent.click(retry);
    expect(
      await screen.findByRole("heading", { name: raw.title }),
    ).toBeInTheDocument();
  });

  it("scopes location changes so a late earlier response cannot replace current product availability", async () => {
    let resolveEarlier!: (value: {
      success: boolean;
      data: ApiBlogPost;
    }) => void;
    mocks.detail
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveEarlier = resolve;
          }),
      )
      .mockResolvedValue({
        success: true,
        data: { ...raw, title: "Current location article" },
      });
    await renderPage({ slug: raw.slug });
    await waitFor(() => expect(mocks.detail).toHaveBeenCalledTimes(1));
    mocks.cookie.mockImplementation((name: string) =>
      name === "userLocation" ? { lat: 21, lng: 71 } : null,
    );
    act(() => {
      window.dispatchEvent(new Event("hyperlocal:location-changed"));
    });
    expect(
      await screen.findByRole("heading", { name: "Current location article" }),
    ).toBeInTheDocument();
    await act(async () => {
      resolveEarlier({ success: true, data: raw });
    });
    expect(
      screen.queryByRole("heading", { name: raw.title }),
    ).not.toBeInTheDocument();
    expect(mocks.detail).toHaveBeenLastCalledWith({
      slug: raw.slug,
      latitude: 21,
      longitude: 71,
    });
  });
});
