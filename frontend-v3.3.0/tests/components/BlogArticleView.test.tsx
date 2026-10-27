import {
  fireEvent,
  render,
  screen,
  waitFor,
  within,
} from "@testing-library/react";
import { createInstance } from "i18next";
import { I18nextProvider } from "react-i18next";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import BlogArticleView from "@/views/Blog/BlogArticleView";
import type { BlogPost } from "@/types/blog";
import en from "../../public/locales/en.json";
import hi from "../../public/locales/hi.json";
import ar from "../../public/locales/ar.json";

const { addToast } = vi.hoisted(() => ({ addToast: vi.fn() }));
vi.mock("@heroui/react", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@heroui/react")>()),
  addToast,
}));
vi.mock("@/components/Blog/BlogProductsSection", () => ({
  default: ({ post }: { post: BlogPost }) => (
    <section aria-label="Associated products">{post.title}</section>
  ),
}));

const post: BlogPost = {
  id: "test-story",
  slug: "test-story",
  title: "An everyday story",
  excerpt: "A few ideas for your home.",
  content: "<h2>A first idea</h2><p>A useful place to start.</p>",
  image: null,
  imageAlt: "",
  category: { id: "home", slug: "everyday-living", name: "Everyday living" },
  publishedAt: null,
  author: "The editorial team",
  readTime: 3,
  tags: [],
};

const originalClipboard = Object.getOwnPropertyDescriptor(
  navigator,
  "clipboard",
);
const originalShare = Object.getOwnPropertyDescriptor(navigator, "share");
const originalExecCommand = Object.getOwnPropertyDescriptor(
  document,
  "execCommand",
);
const originalUrl = window.location.href;
const writeText = vi.fn<(url: string) => Promise<void>>();
const execCommand = vi.fn<(command: string) => boolean>();

function restoreProperty(
  target: object,
  key: string,
  descriptor: PropertyDescriptor | undefined,
) {
  if (descriptor) Object.defineProperty(target, key, descriptor);
  else Reflect.deleteProperty(target, key);
}

async function renderArticle(article = post, language = "en") {
  const i18n = createInstance();
  await i18n.init({
    lng: language,
    fallbackLng: "en",
    resources: { en: { translation: en }, hi: { translation: hi }, ar: { translation: ar } },
    interpolation: { escapeValue: false },
  });
  return {
    ...render(
      <I18nextProvider i18n={i18n}>
        <BlogArticleView post={article} relatedPosts={[]} />
      </I18nextProvider>,
    ),
    t: i18n.t.bind(i18n),
  };
}

describe("blog article reading and sharing", () => {
  it.each([[0, "0 views"], [1, "1 view"], [1452, "1,452 views"]] as const)("shows the API count in the article metadata (%s)", async (count, label) => {
    await renderArticle({ ...post, viewsCount: count });
    expect(within(document.querySelector(".blog-article-header-bottom") as HTMLElement).getByText(label)).toBeInTheDocument();
  });

  it.each(["hi", "ar"])("formats and translates view counts in %s", async (language) => {
    await renderArticle({ ...post, viewsCount: 1452 }, language);
    const formatted = new Intl.NumberFormat(language).format(1452);
    expect(screen.getByText(language === "hi" ? `${formatted} बार देखा गया` : `${formatted} مشاهدة`)).toBeInTheDocument();
  });

  it("does not substitute zero when the API count is missing", async () => {
    await renderArticle(post);
    expect(screen.queryByText("0 views")).not.toBeInTheDocument();
  });
  beforeEach(() => {
    addToast.mockReset();
    writeText.mockReset().mockResolvedValue(undefined);
    execCommand.mockReset().mockReturnValue(false);
    Object.defineProperty(navigator, "clipboard", {
      configurable: true,
      value: { writeText },
    });
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: undefined,
    });
    Object.defineProperty(document, "execCommand", {
      configurable: true,
      value: execCommand,
    });
    window.history.replaceState(
      {},
      "",
      "/blogs/test-story/?preview=true#article-section-2",
    );
  });

  afterEach(() => {
    restoreProperty(navigator, "clipboard", originalClipboard);
    restoreProperty(navigator, "share", originalShare);
    restoreProperty(document, "execCommand", originalExecCommand);
    window.history.replaceState({}, "", originalUrl);
  });

  it("renders sanitized article content without generating story navigation", async () => {
    await renderArticle({
      ...post,
      content: `
        <h2 id="duplicate" onclick="alert('bad')">A repeated idea</h2>
        <p>Useful article text.</p>
        <h3 id="duplicate">A repeated idea</h3>
        <script>alert('bad')</script>
        <a href="javascript:alert('bad')">Unsafe link</a>
        <img src="/safe-image.webp" onerror="alert('bad')" alt="A safe illustration" />
      `,
    });

    expect(
      screen.getByRole("heading", { level: 1, name: post.title }),
    ).toBeInTheDocument();
    const article = screen.getByRole("article", { name: post.title });
    const headings = within(article).getAllByRole("heading", {
      name: "A repeated idea",
    });
    expect(new Set(headings.map((heading) => heading.id)).size).toBe(2);
    expect(headings.every((heading) => heading.id)).toBe(true);

    expect(article.querySelectorAll('a[href^="#"]')).toHaveLength(0);
    expect(article.querySelector("script, [onclick], [onerror]")).toBeNull();
    expect(within(article).getByText("Unsafe link")).not.toHaveAttribute(
      "href",
    );
    expect(
      within(article).getByText("Useful article text."),
    ).toBeInTheDocument();
  });

  it("places one accessible share icon with the article metadata", async () => {
    const { t } = await renderArticle();
    const shareButton = screen.getByRole("button", { name: t("blog.share") });

    expect(screen.getAllByRole("button")).toHaveLength(1);
    expect(
      document.querySelector(".blog-article-header-bottom"),
    ).toContainElement(shareButton);
    expect(
      screen.queryByRole("button", { name: t("blog.copyLink") }),
    ).not.toBeInTheDocument();
  });

  it("omits story contents and reading progress while retaining blog navigation", async () => {
    const { t } = await renderArticle();

    expect(screen.queryByRole("progressbar")).not.toBeInTheDocument();
    expect(
      screen.queryByText(t("blog.readingProgress")),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("navigation", { name: t("blog.onThisPage") }),
    ).not.toBeInTheDocument();
    expect(screen.queryByText(t("blog.onThisPage"))).not.toBeInTheDocument();
    expect(screen.queryByText(t("blog.articleLabel"))).not.toBeInTheDocument();
    const story = screen.getByRole("article", { name: post.title });
    expect(story.querySelectorAll('a[href^="#"]')).toHaveLength(0);
    expect(
      screen.getByRole("link", { name: t("blog.backToBlog") }),
    ).toHaveAttribute("href", expect.stringMatching(/^\/blogs\/?$/));
    expect(screen.getByRole("link", { name: t("blog.backToBlog") })).toHaveTextContent(t("blog.backToBlog"));
  });

  it("provides the current story to its associated products section", async () => {
    await renderArticle();

    expect(
      screen.getByRole("region", { name: "Associated products" }),
    ).toHaveTextContent(post.title);
  });

  it("renders admin author metadata without preview copy or an invented reading time", async () => {
    const { container, t } = await renderArticle({
      ...post,
      author: "hyperlocal",
      authorBio: "Official Hyperlocal profile",
      authorPhoto: "https://example.com/author.png",
      publishedAt: "2026-10-05T12:49:00+05:30",
      readTime: null,
    });

    expect(screen.getByText("Official Hyperlocal profile")).toBeInTheDocument();
    expect(screen.getAllByText("hyperlocal")).toHaveLength(2);
    expect(screen.queryByText(t("blog.minuteRead", { count: 3 }))).toBeNull();
    expect(screen.queryByText(t("blog.previewLabel"))).toBeNull();
    expect(container.querySelector("time")).toHaveAttribute(
      "dateTime",
      "2026-10-05T07:19:00.000Z",
    );
    const avatars = container.querySelectorAll(".blog-article-avatar img");
    expect(avatars).toHaveLength(2);
    fireEvent.error(avatars[0]);
    expect(container.querySelector(".blog-article-avatar")).toHaveTextContent(
      "h",
    );
  });

  it("omits an empty category link when the admin post has no category", async () => {
    const { container } = await renderArticle({
      ...post,
      category: { id: "", name: "", slug: "" },
    });
    expect(container.querySelector(".blog-article-category")).toBeNull();
    for (const link of screen.getAllByRole("link")) {
      expect(link).toHaveAccessibleName();
    }
  });

  it("shares the title and excerpt with a clean canonical article URL", async () => {
    const nativeShare = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: nativeShare,
    });
    const { t } = await renderArticle();
    const shareButton = screen.getByRole("button", { name: t("blog.share") });
    fireEvent.click(shareButton);

    await waitFor(() => expect(shareButton).toBeEnabled());
    expect(nativeShare).toHaveBeenCalledWith({
      title: post.title,
      text: `${post.title} - ${post.excerpt}`,
      url: `${window.location.origin}/blogs/test-story/`,
    });
    expect(writeText).not.toHaveBeenCalled();
    expect(addToast).not.toHaveBeenCalled();
  });

  it("avoids repeating the title when the native share excerpt already includes it", async () => {
    const nativeShare = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: nativeShare,
    });
    const excerpt = `${post.title}: a few ideas for your home.`;
    const { t } = await renderArticle({ ...post, excerpt });
    fireEvent.click(screen.getByRole("button", { name: t("blog.share") }));

    await waitFor(() =>
      expect(nativeShare).toHaveBeenCalledWith({
        title: post.title,
        text: excerpt,
        url: `${window.location.origin}/blogs/test-story/`,
      }),
    );
  });

  it("copies title, plain excerpt and clean URL with a success toast when native share is unavailable", async () => {
    const { t } = await renderArticle({
      ...post,
      excerpt: "<strong>A few ideas</strong> for your home.",
    });
    fireEvent.click(screen.getByRole("button", { name: t("blog.share") }));

    await waitFor(() =>
      expect(addToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: t("sharing.copied"),
          color: "success",
        }),
      ),
    );
    expect(writeText).toHaveBeenCalledWith(
      `${post.title}\n${post.excerpt}\n${window.location.origin}/blogs/test-story/`,
    );
    expect(execCommand).not.toHaveBeenCalled();
  });

  it("copies just the article URL when there is no excerpt", async () => {
    const { t } = await renderArticle({ ...post, excerpt: "" });
    fireEvent.click(screen.getByRole("button", { name: t("blog.share") }));

    await waitFor(() =>
      expect(writeText).toHaveBeenCalledWith(
        `${window.location.origin}/blogs/test-story/`,
      ),
    );
  });

  it("falls back to browser copy when clipboard permission is denied", async () => {
    writeText.mockRejectedValue(
      new DOMException("Permission denied", "NotAllowedError"),
    );
    execCommand.mockReturnValue(true);
    const { t } = await renderArticle();
    fireEvent.click(screen.getByRole("button", { name: t("blog.share") }));

    await waitFor(() =>
      expect(addToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: t("sharing.copied"),
          color: "success",
        }),
      ),
    );
    expect(execCommand).toHaveBeenCalledWith("copy");
    expect(document.querySelector("textarea")).toBeNull();
  });

  it("shows recoverable feedback when both clipboard and browser copy fail", async () => {
    writeText.mockRejectedValue(
      new DOMException("Permission denied", "NotAllowedError"),
    );
    const { t } = await renderArticle();
    const shareButton = screen.getByRole("button", { name: t("blog.share") });
    fireEvent.click(shareButton);

    await waitFor(() =>
      expect(addToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: t("sharing.failed"),
          color: "danger",
        }),
      ),
    );
    expect(shareButton).toBeEnabled();
    expect(execCommand).toHaveBeenCalledWith("copy");
    expect(document.querySelector("textarea")).toBeNull();
  });

  it("shows a retry toast when native sharing fails", async () => {
    const nativeShare = vi
      .fn()
      .mockRejectedValue(new DOMException("Cannot share", "NotAllowedError"));
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: nativeShare,
    });
    const { t } = await renderArticle();
    const shareButton = screen.getByRole("button", { name: t("blog.share") });
    fireEvent.click(shareButton);

    await waitFor(() =>
      expect(addToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: t("sharing.failed"),
          color: "danger",
        }),
      ),
    );
    expect(shareButton).toBeEnabled();
    expect(writeText).not.toHaveBeenCalled();
  });

  it("leaves no toast or copied content when native sharing is cancelled", async () => {
    const nativeShare = vi
      .fn()
      .mockRejectedValue(new DOMException("Cancelled", "AbortError"));
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: nativeShare,
    });
    const { t } = await renderArticle();
    const shareButton = screen.getByRole("button", { name: t("blog.share") });
    fireEvent.click(shareButton);

    await waitFor(() => expect(shareButton).toBeEnabled());
    expect(nativeShare).toHaveBeenCalledWith({
      title: post.title,
      text: `${post.title} - ${post.excerpt}`,
      url: `${window.location.origin}/blogs/test-story/`,
    });
    expect(addToast).not.toHaveBeenCalled();
    expect(writeText).not.toHaveBeenCalled();
    expect(execCommand).not.toHaveBeenCalled();
  });
});
