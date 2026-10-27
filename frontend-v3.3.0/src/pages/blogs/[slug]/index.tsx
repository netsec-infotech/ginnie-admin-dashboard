import type { GetStaticPaths, GetStaticProps } from "next";
import { Button, Spinner } from "@heroui/react";
import Link from "next/link";
import { useRouter } from "next/router";
import { useEffect, useReducer, useSyncExternalStore } from "react";
import { useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import useSWR from "swr";
import DynamicSEO from "@/SEO/DynamicSEO";
import BlogArticleView from "@/views/Blog/BlogArticleView";
import { useSettings } from "@/contexts/SettingsContext";
import { adaptBlogPost } from "@/helpers/blog";
import { LOCATION_CHANGED_EVENT } from "@/helpers/events";
import { isSSR } from "@/helpers/getters";
import { generateBreadcrumbSchema } from "@/helpers/seo";
import { generateBlogPostingSchema } from "@/helpers/blogSeo";
import { getSiteSeo } from "@/config/siteSeo";
import { getCookie, subscribeToAuthSessionChanges } from "@/lib/cookies";
import type { RootState } from "@/lib/redux/store";
import { getBlogBySlug, getBlogs, getSettings } from "@/routes/api";
import { readBlogStaticSnapshot } from "@/services/blogStaticGenerationService";
import type { Settings } from "@/types/ApiResponse";
import type { ApiBlogPost, BlogPost } from "@/types/blog";

interface BlogArticleData {
  post: BlogPost | null;
  relatedPosts: BlogPost[];
  notFound: boolean;
}

interface BlogArticlePageProps {
  slug: string;
  initialData?: BlogArticleData;
  initialSettings?: Settings | null;
  initialError?: boolean;
  clientFallback?: boolean;
  initialLanguage?: string;
}

const subscribeToHydration = () => () => {};
const clientSnapshot = () => true;
const serverSnapshot = () => false;

const relatedArticles = (raw: ApiBlogPost, slug: string): BlogPost[] => {
  const seen = new Set([slug]);
  return (raw.related_blogs ?? [])
    .map(adaptBlogPost)
    .filter((post): post is BlogPost => {
      if (!post || seen.has(post.slug)) return false;
      seen.add(post.slug);
      return true;
    })
    .slice(0, 3);
};

const fetchArticle = async (
  slug: string,
  latitude?: string | number,
  longitude?: string | number,
): Promise<BlogArticleData> => {
  const response = await getBlogBySlug({ slug, latitude, longitude });
  if (response.notFound) {
    return { post: null, relatedPosts: [], notFound: true };
  }
  if (!response.success || !response.data) throw new Error("Blog detail is unavailable");
  return articleData(response.data, slug);
};

const articleData = (raw: ApiBlogPost, slug: string): BlogArticleData => {
  const post = adaptBlogPost(raw);
  if (!post) throw new Error("Blog detail is unavailable");
  return {
    post,
    // Cards do not render full article bodies or linked product inventories.
    relatedPosts: relatedArticles(raw, slug).map((related) => ({ ...related, content: "", linkedProducts: [] })),
    notFound: false,
  };
};

export default function BlogArticlePage({
  slug: initialSlug,
  initialData,
  initialError = false,
  clientFallback = false,
  initialLanguage = "en",
}: BlogArticlePageProps) {
  const router = useRouter();
  const { t, i18n } = useTranslation();
  const language = i18n.resolvedLanguage || i18n.language || "en";
  const { defaultLocation, webSettings } = useSettings();
  const hydrated = useSyncExternalStore(
    subscribeToHydration,
    clientSnapshot,
    serverSnapshot,
  );
  let slug = initialSlug;
  if (clientFallback) {
    slug = "";
    if (hydrated && router.isReady) {
      const match = router.asPath
        .split(/[?#]/)[0]
        .match(/^\/blogs\/([^/]+)\/?$/);
      try {
        slug = match ? decodeURIComponent(match[1]) : "";
      } catch {
        // An invalid public URL cannot identify an article.
      }
    }
  }
  const accessToken = useSelector(
    (state: RootState) => state.auth.access_token,
  );
  const [scopeVersion, refreshScope] = useReducer(
    (value: number) => value + 1,
    0,
  );

  useEffect(() => {
    window.addEventListener(LOCATION_CHANGED_EVENT, refreshScope);
    const unsubscribe = subscribeToAuthSessionChanges(refreshScope);
    return () => {
      window.removeEventListener(LOCATION_CHANGED_EVENT, refreshScope);
      unsubscribe();
    };
  }, []);

  const location = getCookie<{ lat?: string | number; lng?: string | number }>(
    "userLocation",
  );
  const latitude = location?.lat ?? defaultLocation?.lat;
  const longitude = location?.lng ?? defaultLocation?.lng;
  const session = accessToken || getCookie<string>("access_token") || "";
  const { data, error, isLoading, mutate } = useSWR<BlogArticleData>(
    slug
      ? [
          "blog-detail",
          slug,
          session,
          latitude ?? "",
          longitude ?? "",
          scopeVersion,
          language,
        ]
      : null,
    () => fetchArticle(slug, latitude, longitude),
    {
      fallbackData:
        initialData?.post?.slug === slug && language === initialLanguage
          ? initialData
          : undefined,
      revalidateOnMount: true,
      revalidateOnFocus: false,
      shouldRetryOnError: false,
    },
  );
  const post = data?.post;
  const canonical = slug ? `/blogs/${encodeURIComponent(slug)}/` : "/blogs/";

  if (!post) {
    const missing =
      data?.notFound || (clientFallback && hydrated && router.isReady && !slug);
    const failed = !missing && (error || (initialError && !isLoading));
    return (
      <>
        <DynamicSEO
          title={t(
            missing
              ? "blog.notFoundTitle"
              : failed
                ? "blog.loadErrorTitle"
                : "blog.loading",
          )}
          canonical={canonical}
          robots="noindex, follow"
        />
        <main className="container mx-auto flex min-h-80 flex-col items-center justify-center gap-4 px-4 py-16 text-center">
          {missing || failed ? (
            <>
              <h1 className="text-2xl font-semibold">
                {t(missing ? "blog.notFoundTitle" : "blog.loadErrorTitle")}
              </h1>
              <p className="max-w-md text-sm leading-6 text-default-500">
                {t(
                  missing
                    ? "blog.notFoundDescription"
                    : "blog.loadErrorDescription",
                )}
              </p>
              {missing ? (
                <Link
                  href="/blogs/"
                  className="text-sm font-medium text-primary"
                >
                  {t("blog.backToBlog")}
                </Link>
              ) : (
                <Button
                  size="sm"
                  variant="flat"
                  color="primary"
                  onPress={() => {
                    void mutate();
                  }}
                >
                  {t("blog.retry")}
                </Button>
              )}
            </>
          ) : (
            <Spinner label={t("blog.loading")} />
          )}
        </main>
      </>
    );
  }

  return (
    <>
      <DynamicSEO
        title={post.seo?.title || post.title}
        description={post.seo?.description || post.excerpt}
        keywords={post.seo?.keywords.filter(Boolean).join(", ") || undefined}
        canonical={canonical}
        ogType="article"
        ogImage={post.image || undefined}
        ogImageAlt={post.imageAlt}
        author={post.author || undefined}
        jsonLd={[
          generateBreadcrumbSchema([
            { name: t("nav.home"), url: "/" },
            { name: t("nav.blog"), url: "/blogs/" },
            { name: post.title, url: canonical },
          ]),
          generateBlogPostingSchema(post, getSiteSeo(webSettings), language),
        ]}
      />
      <BlogArticleView
        key={slug}
        post={post}
        relatedPosts={data?.relatedPosts ?? []}
      />
    </>
  );
}

export const getStaticPaths: GetStaticPaths = async () => {
  const snapshot = process.env.NODE_ENV === "production" ? await readBlogStaticSnapshot() : null;
  if (snapshot) return {
    paths: [...new Set(snapshot.map((raw) => adaptBlogPost(raw)?.slug).filter((slug): slug is string => Boolean(slug)))].map((slug) => ({ params: { slug } })),
    fallback: isSSR() ? "blocking" : false,
  };
  const slugs = new Set<string>();
  for (let page = 1; page <= 500; page += 1) {
    const response = await getBlogs({ page, per_page: 100 });
    if (
      !response.success ||
      !response.data ||
      !Array.isArray(response.data.data)
    ) {
      throw new Error(
        "Cannot generate blog routes while the blog API is unavailable",
      );
    }
    for (const raw of response.data.data) {
      const post = adaptBlogPost(raw);
      if (post) slugs.add(post.slug);
    }
    const lastPage = Math.max(
      Number(response.data.last_page) || 1,
      Math.ceil(response.data.total / (response.data.per_page || 100)),
    );
    if (page >= lastPage) {
      return {
        paths: Array.from(slugs, (slug) => ({ params: { slug } })),
        fallback: isSSR() ? "blocking" : false,
      };
    }
  }
  throw new Error("Blog route pagination exceeded the build limit");
};

export const getStaticProps: GetStaticProps<BlogArticlePageProps> = async ({
  params,
  revalidateReason,
}) => {
  const slug = params?.slug;
  if (typeof slug !== "string" || !slug.trim()) return { notFound: true };
  try {
    const snapshot = !isSSR() && process.env.NODE_ENV === "production" ? await readBlogStaticSnapshot() : null;
    const cached = snapshot?.find((post) => post.slug === slug);
    if (snapshot && !cached) return { notFound: true };
    const [initialData, settings] = await Promise.all([
      cached ? Promise.resolve(articleData(cached, slug)) : fetchArticle(slug),
      getSettings(),
    ]);
    if (initialData.notFound)
      return { notFound: true, ...(isSSR() ? { revalidate: 300 } : {}) };
    return {
      props: JSON.parse(
        JSON.stringify({
          slug,
          initialData,
          initialSettings: settings.success ? settings.data : null,
        }),
      ) as BlogArticlePageProps,
      ...(isSSR() ? { revalidate: 300 } : {}),
    };
  } catch {
    if (
      revalidateReason === "build" ||
      (revalidateReason === "stale" && process.env.NODE_ENV !== "development")
    )
      throw new Error(
        "Cannot generate an article while the blog API is unavailable",
      );
    return {
      props: { slug, initialError: true },
      ...(isSSR() ? { revalidate: 30 } : {}),
    };
  }
};
