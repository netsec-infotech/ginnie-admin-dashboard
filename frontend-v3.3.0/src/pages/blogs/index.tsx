import { useTranslation } from "react-i18next";
import DynamicSEO from "@/SEO/DynamicSEO";
import BlogListingView, { POSTS_PER_PAGE } from "@/views/Blog/BlogListingView";
import type { BlogListingViewProps } from "@/views/Blog/BlogListingView";
import type { GetServerSideProps } from "next";
import { getBlogCategories, getSettings } from "@/routes/api";
import { getBlogListingPage } from "@/services/blogListingService";
import { isSSR } from "@/helpers/getters";
import { loadTranslations } from "../../../i18n";
import { generateBreadcrumbSchema } from "@/helpers/seo";

export default function BlogsPage(props: BlogListingViewProps) {
  const { t } = useTranslation();
  return (
    <>
      <DynamicSEO
        title={t("nav.blog")}
        description={t("blog.subtitle")}
        canonical="/blogs/"
        jsonLd={generateBreadcrumbSchema([
          { name: t("nav.home"), url: "/" },
          { name: t("nav.blog"), url: "/blogs/" },
        ])}
      />
      <BlogListingView {...props} />
    </>
  );
}

// Next rejects named getStaticProps and getServerSideProps in one module,
// even when one evaluates to undefined. This export-only hook prerenders the
// listing while retaining query-aware getServerSideProps in SSR mode.
BlogsPage.getInitialProps = !isSSR()
  ? async () => {
      const [blogsResult, categoriesResult, settingsResult] = await Promise.allSettled([
        getBlogListingPage({ page: 1, per_page: POSTS_PER_PAGE }),
        getBlogCategories(),
        getSettings(),
      ]);
      const blogs = blogsResult.status === "fulfilled" ? blogsResult.value : null;
      const hasBlogs = blogs?.success && Array.isArray(blogs.data?.data);
      if (!hasBlogs) {
        const reason = blogsResult.status === "rejected"
          ? String(blogsResult.reason?.message || blogsResult.reason)
          : blogs?.message || "invalid blog API response";
        console.warn(`Skipping initial blog data: ${reason}`);
      }
      return JSON.parse(
        JSON.stringify({
          initialBlogs: hasBlogs ? blogs : null,
          initialCategories: categoriesResult.status === "fulfilled" && categoriesResult.value.success
            ? categoriesResult.value.data ?? null : null,
          initialSettings: settingsResult.status === "fulfilled" && settingsResult.value.success
            ? settingsResult.value.data ?? null : null,
          initialQueryKey: JSON.stringify(["", "", "", "", ""]),
        }),
      ) as BlogListingViewProps;
    }
  : undefined;

export const getServerSideProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      await loadTranslations(context);
      const queryString = (key: string) =>
        typeof context.query[key] === "string" ? context.query[key] : "";
      const requestedPage = /^\d+$/.test(queryString("page"))
        ? Number(queryString("page"))
        : 1;
      const [blogsResult, categoriesResult, settingsResult] =
        await Promise.allSettled([
          getBlogListingPage({
            page:
              Number.isSafeInteger(requestedPage) && requestedPage > 0
                ? requestedPage
                : 1,
            per_page: POSTS_PER_PAGE,
            ...(queryString("search") ? { search: queryString("search") } : {}),
            ...(queryString("category") || queryString("category_slug")
              ? {
                  category_slug:
                    queryString("category") || queryString("category_slug"),
                }
              : {}),
            ...(queryString("category_id")
              ? { category_id: queryString("category_id") }
              : {}),
            ...(queryString("tag") ? { tag: queryString("tag") } : {}),
          }),
          getBlogCategories(),
          getSettings(),
        ]);
      return {
        props: {
          initialBlogs:
            blogsResult.status === "fulfilled" && blogsResult.value.success
              ? blogsResult.value
              : null,
          initialCategories:
            categoriesResult.status === "fulfilled" &&
            categoriesResult.value.success
              ? (categoriesResult.value.data ?? null)
              : null,
          initialSettings:
            settingsResult.status === "fulfilled" &&
            settingsResult.value.success
              ? (settingsResult.value.data ?? null)
              : null,
        },
      };
    }
  : undefined;
