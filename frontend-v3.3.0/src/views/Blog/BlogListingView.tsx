import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/router";
import Link from "next/link";
import {
  ArrowRight,
  BookOpen,
  ChevronLeft,
  ChevronRight,
  Search,
  SearchX,
  X,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import BlogCard from "@/components/Blog/BlogCard";
import { Swiper, SwiperSlide } from "swiper/react";
import { A11y, Keyboard, Pagination } from "swiper/modules";
import useSWR from "swr";
import { getBlogCategories, getBlogEditorsPick } from "@/routes/api";
import {
  getBlogListingPage,
  POSTS_PER_PAGE,
} from "@/services/blogListingService";
import { adaptBlogPost, adaptBlogCategory } from "@/helpers/blog";
import type {
  ApiBlogCategory,
  BlogCategory,
  BlogListResponse,
} from "@/types/blog";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";

export { POSTS_PER_PAGE } from "@/services/blogListingService";

export function getBlogPaginationItems(pageCount: number, currentPage: number) {
  if (pageCount <= 5) {
    return Array.from({ length: pageCount }, (_, index) => index + 1);
  }
  if (currentPage <= 3) return [1, 2, 3, "end-gap", pageCount] as const;
  if (currentPage >= pageCount - 2) {
    return [1, "start-gap", pageCount - 2, pageCount - 1, pageCount] as const;
  }
  return [1, "start-gap", currentPage, "end-gap", pageCount] as const;
}

export interface BlogListingViewProps {
  initialBlogs?: BlogListResponse | null;
  initialCategories?: ApiBlogCategory[] | null;
  initialLanguage?: string;
  initialQueryKey?: string;
}

export default function BlogListingView({
  initialBlogs,
  initialCategories,
  initialLanguage = "en",
  initialQueryKey: suppliedInitialQueryKey,
}: BlogListingViewProps = {}) {
  const { t, i18n } = useTranslation();
  const language = i18n.resolvedLanguage || i18n.language || "en";
  const router = useRouter();
  const querySearch =
    typeof router.query.search === "string" ? router.query.search : "";
  const categoryQuery =
    typeof router.query.category === "string"
      ? router.query.category
      : typeof router.query.category_slug === "string"
        ? router.query.category_slug
        : "";
  const categoryIdQuery =
    typeof router.query.category_id === "string"
      ? router.query.category_id
      : "";
  const tagQuery = typeof router.query.tag === "string" ? router.query.tag : "";
  const pageQuery =
    typeof router.query.page === "string" ? router.query.page : "";
  const queryKey = JSON.stringify([
    querySearch,
    categoryQuery,
    pageQuery,
    categoryIdQuery,
    tagQuery,
  ]);
  const [initialQueryKey] = useState(suppliedInitialQueryKey ?? queryKey);
  const navigationSequence = useRef(0);
  const resultsHeadingRef = useRef<HTMLHeadingElement>(null);
  const observedQueryRef = useRef(queryKey);
  useEffect(() => {
    observedQueryRef.current = queryKey;
  }, [queryKey]);
  const focusGeneration = useRef(0);
  const focusFrame = useRef<number | null>(null);
  const cancelPageFocus = useCallback(() => {
    focusGeneration.current += 1;
    if (focusFrame.current !== null) {
      window.cancelAnimationFrame(focusFrame.current);
      focusFrame.current = null;
    }
  }, []);
  const [searchDraft, setSearchDraft] = useState({
    query: queryKey,
    value: querySearch,
    pending: [] as { id: number; key: string }[],
  });
  const search = searchDraft.value;
  const debouncedSearch = useDebouncedValue(search, 300);
  // Own URL updates must not replace newer typing. Browser history restores
  // the URL's search when it was not initiated by the current draft.
  if (searchDraft.query !== queryKey) {
    const ownNavigation = searchDraft.pending.find(
      (entry) => entry.key === queryKey,
    );
    setSearchDraft({
      query: queryKey,
      value: ownNavigation ? searchDraft.value : querySearch,
      pending: ownNavigation
        ? searchDraft.pending.filter((entry) => entry.id > ownNavigation.id)
        : [],
    });
  }
  const category = categoryQuery;
  const allTopicsSelected = !category && !categoryIdQuery;
  const isCategorySelected = (item: BlogCategory) =>
    category === item.slug || (!category && categoryIdQuery === item.id);
  const hasFilters = Boolean(
    search.trim() || category || categoryIdQuery || tagQuery,
  );
  const parsedPage = /^\d+$/.test(pageQuery) ? Number(pageQuery) : 1;
  const requestedPage =
    Number.isSafeInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1;
  const {
    data: blogsResponse,
    error: blogsError,
    isLoading: blogsLoading,
    isValidating: blogsValidating,
    mutate: retryBlogs,
  } = useSWR(
    router.isReady === false
      ? null
      : ([
          "blogs",
          requestedPage,
          querySearch,
          category,
          categoryIdQuery,
          tagQuery,
          language,
        ] as const),
    async ([, page, keyword, slug, categoryId, tag]) => {
      const response = await getBlogListingPage({
        page,
        per_page: POSTS_PER_PAGE,
        ...(keyword ? { search: keyword } : {}),
        ...(slug ? { category_slug: slug } : {}),
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(tag ? { tag } : {}),
      });
      if (!response.success || !response.data)
        throw new Error("Blog request failed");
      return response;
    },
    {
      fallbackData:
        initialQueryKey === queryKey &&
        language === initialLanguage &&
        initialBlogs?.success
          ? initialBlogs
          : undefined,
      keepPreviousData: false,
      revalidateOnFocus: true,
      shouldRetryOnError: false,
    },
  );
  const {
    data: categoriesResponse,
    error: categoriesError,
    isLoading: categoriesLoading,
    isValidating: categoriesValidating,
    mutate: retryCategories,
  } = useSWR(
    ["blog-categories", language],
    async () => {
      const response = await getBlogCategories();
      if (!response.success || !Array.isArray(response.data)) {
        throw new Error("Blog category request failed");
      }
      return response.data;
    },
    {
      fallbackData:
        language === initialLanguage
          ? (initialCategories ?? undefined)
          : undefined,
      revalidateOnFocus: false,
      shouldRetryOnError: false,
    },
  );
  const categories = useMemo(
    () =>
      (categoriesResponse ?? [])
        .map(adaptBlogCategory)
        .filter((item) => item !== null),
    [categoriesResponse],
  );
  const { data: editorPickResponse } = useSWR(
    router.isReady === false ? null : ["blog-editor-pick", language],
    async () => {
      const response = await getBlogEditorsPick();
      if (!response.success || !response.data) throw new Error("Editor pick request failed");
      return response;
    },
    { revalidateOnFocus: true, shouldRetryOnError: false },
  );
  const editorPicks = useMemo(() =>
    (editorPickResponse?.data?.editors_pick ?? [])
      .map(adaptBlogPost)
      .filter((post) => post !== null),
    [editorPickResponse],
  );
  const pagination = blogsResponse?.data;
  const pageCount = Math.max(pagination?.last_page ?? 1, 1);
  const needsPageCorrection = Boolean(pagination && requestedPage > pageCount);
  const currentPage = pagination?.current_page ?? requestedPage;
  const changingFilters =
    search !== querySearch ||
    searchDraft.pending.some((entry) => entry.key !== queryKey);
  const isLoading =
    changingFilters ||
    needsPageCorrection ||
    (blogsLoading && !pagination) ||
    router.isReady === false ||
    (!blogsResponse && !blogsError);
  const posts = useMemo(
    () =>
      (pagination?.data ?? [])
        .map(adaptBlogPost)
        .filter((post) => post !== null),
    [pagination?.data],
  );
  const featuredPosts = !hasFilters ? editorPicks : [];
  const featuredSlugs = new Set(editorPicks.map((post) => post.slug));
  const total = pagination?.total ?? 0;
  const from = pagination?.from ?? (currentPage - 1) * (pagination?.per_page ?? POSTS_PER_PAGE) + 1;
  const to = pagination?.to ?? Math.min((currentPage - 1) * (pagination?.per_page ?? POSTS_PER_PAGE) + (pagination?.data.length ?? 0), total);
  const numberFormat = useMemo(
    () => new Intl.NumberFormat(i18n.resolvedLanguage || i18n.language),
    [i18n.resolvedLanguage, i18n.language],
  );

  const updateFilters = useCallback(
    (
      nextSearch: string,
      nextCategory: string,
      replace = false,
      nextPage = 1,
      nextCategoryId = categoryIdQuery,
      nextTag = tagQuery,
    ) => {
      cancelPageFocus();
      const query: Record<string, string> = {};
      if (nextSearch) query.search = nextSearch;
      if (nextCategory) query.category = nextCategory;
      if (nextCategoryId) query.category_id = nextCategoryId;
      if (nextTag) query.tag = nextTag;
      if (nextPage > 1) query.page = String(nextPage);
      const url = { pathname: "/blogs/", query };
      const id = ++navigationSequence.current;
      const key = JSON.stringify([
        nextSearch,
        nextCategory,
        nextPage > 1 ? String(nextPage) : "",
        nextCategoryId,
        nextTag,
      ]);
      setSearchDraft((current) => ({
        ...current,
        pending: [...current.pending, { id, key }],
      }));
      const navigation = replace
        ? router.replace(url, undefined, { shallow: true, scroll: false })
        : router.push(url, undefined, { shallow: true, scroll: false });
      // Retain ownership until React observes the new query; cancelled and
      // same-URL navigations do not need to wait for a query change.
      return Promise.resolve(navigation).then(
        (completed) => {
          setSearchDraft((current) =>
            completed === false || current.query === key
              ? {
                  ...current,
                  pending: current.pending.filter((entry) => entry.id !== id),
                }
              : current,
          );
          return completed !== false;
        },
        () => {
          setSearchDraft((current) => ({
            ...current,
            pending: current.pending.filter((entry) => entry.id !== id),
          }));
          return false;
        },
      );
    },
    [router, cancelPageFocus, categoryIdQuery, tagQuery],
  );

  useEffect(() => {
    if (needsPageCorrection && !changingFilters) {
      void updateFilters(querySearch, category, true, pageCount);
    }
  }, [
    needsPageCorrection,
    changingFilters,
    querySearch,
    category,
    pageCount,
    updateFilters,
  ]);

  useEffect(() => {
    if (debouncedSearch !== search || debouncedSearch === querySearch) return;
    updateFilters(debouncedSearch, category, true);
  }, [debouncedSearch, search, querySearch, category, updateFilters]);

  useEffect(() => {
    const restoreHistory = () => {
      cancelPageFocus();
      setSearchDraft((current) => ({ ...current, pending: [] }));
    };
    window.addEventListener("popstate", restoreHistory);
    return () => {
      window.removeEventListener("popstate", restoreHistory);
      cancelPageFocus();
    };
  }, [cancelPageFocus]);

  const clearSearch = () => {
    setSearchDraft((current) => ({ ...current, value: "" }));
    updateFilters("", category, true);
  };

  const resetFilters = () => {
    setSearchDraft((current) => ({ ...current, value: "" }));
    updateFilters("", "", false, 1, "", "");
  };

  const changePage = async (page: number) => {
    if (page === currentPage || page < 1 || page > pageCount) return;
    const sequence = navigationSequence.current + 1;
    const navigation = updateFilters(search, category, false, page);
    const generation = focusGeneration.current;
    const expectedQuery = JSON.stringify([
      search,
      category,
      page > 1 ? String(page) : "",
      categoryIdQuery,
      tagQuery,
    ]);
    const completed = await navigation;
    if (
      !completed ||
      sequence !== navigationSequence.current ||
      generation !== focusGeneration.current
    )
      return;
    focusFrame.current = window.requestAnimationFrame(() => {
      focusFrame.current = null;
      if (
        sequence !== navigationSequence.current ||
        generation !== focusGeneration.current ||
        observedQueryRef.current !== expectedQuery
      )
        return;
      const heading = resultsHeadingRef.current;
      heading?.scrollIntoView({ block: "start" });
      heading?.focus({ preventScroll: true });
    });
  };

  return (
    <div className="mx-auto max-w-[1320px] px-2 pb-14 pt-5 sm:px-3 sm:pt-8 min-[1024px]:px-6">
      <nav
        aria-label={t("blog.breadcrumbLabel")}
        className="mb-6 flex items-center gap-2 text-xs text-default-500 sm:mb-8"
      >
        <Link
          href="/"
          className="rounded-sm hover:text-primary focus-visible:outline-primary"
        >
          {t("nav.home")}
        </Link>
        <ChevronRight size={13} aria-hidden="true" className="rtl:rotate-180" />
        <span aria-current="page" className="text-default-700">
          {t("nav.blog")}
        </span>
      </nav>

      <header className="mb-6 max-w-2xl sm:mb-8">
        <h1 className="text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
          {t("nav.blog")}
        </h1>
        <p className="mt-3 text-sm leading-7 text-default-500 sm:text-base">
          {t("blog.subtitle")}
        </p>
      </header>

      <section
        aria-label={t("blog.filtersLabel")}
        className="mb-6 flex flex-col gap-4 border-y border-default-200/70 py-4 sm:mb-8 min-[1024px]:flex-row min-[1024px]:items-start min-[1024px]:justify-between"
      >
        <div
          role="group"
          aria-label={t("blog.topicsLabel")}
          className="flex flex-wrap gap-2"
        >
          <button
            type="button"
            onClick={() => updateFilters(search, "", false, 1, "")}
            aria-pressed={allTopicsSelected}
            className={`min-h-10 cursor-pointer rounded-full px-4 py-2 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${allTopicsSelected ? "bg-foreground text-background" : "bg-content2/60 text-default-600 hover:bg-default-200"}`}
          >
            {t("blog.allTopics")}
          </button>
          {categoriesLoading && !categoriesResponse && (
            <div
              aria-label={t("blog.loading")}
              className="flex gap-2"
              role="status"
            >
              {[0, 1, 2].map((item) => (
                <span
                  key={item}
                  className="h-10 w-20 animate-pulse rounded-full bg-default-200/60"
                  aria-hidden="true"
                />
              ))}
            </div>
          )}
          {categoriesError && (
            <div
              role="alert"
              className="flex items-center gap-2 text-xs text-default-500"
            >
              <span>{t("blog.categoriesLoadError")}</span>
              <button
                type="button"
                onClick={() => void retryCategories()}
                disabled={categoriesValidating}
                className="px-2 py-2 text-primary underline underline-offset-4 disabled:opacity-50"
              >
                {t("blog.retry")}
              </button>
            </div>
          )}
          {categories.map((item) => (
            <button
              key={item.slug}
              type="button"
              onClick={() => updateFilters(search, item.slug, false, 1, "")}
              aria-pressed={isCategorySelected(item)}
              className={`min-h-10 cursor-pointer rounded-full px-4 py-2 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${isCategorySelected(item) ? "bg-foreground text-background" : "bg-content2/60 text-default-600 hover:bg-default-200"}`}
            >
              {item.name}
            </button>
          ))}
        </div>
        <div className="relative min-[1024px]:w-72 min-[1024px]:shrink-0">
          <Search
            size={17}
            aria-hidden="true"
            className="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-default-400"
          />
          <input
            type="search"
            aria-label={t("blog.searchLabel")}
            placeholder={t("blog.searchPlaceholder")}
            value={search}
            onChange={(event) => {
              const value = event.currentTarget.value;
              cancelPageFocus();
              setSearchDraft((current) => ({
                ...current,
                value,
              }));
            }}
            className="h-11 w-full rounded-full border border-default-200 bg-content1 pe-11 ps-11 text-sm outline-none placeholder:text-default-400 focus:border-primary focus:ring-2 focus:ring-primary/15 [&::-webkit-search-cancel-button]:hidden"
          />
          {search && (
            <button
              type="button"
              aria-label={t("blog.clearSearch")}
              onClick={clearSearch}
              className="absolute end-1.5 top-1/2 flex size-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full text-default-500 hover:bg-default-100 focus-visible:outline-primary"
            >
              <X size={16} aria-hidden="true" />
            </button>
          )}
        </div>
      </section>

      {featuredPosts.length > 0 && (
        featuredPosts.length === 1 ? <BlogCard post={featuredPosts[0]} featured /> : (
          <section aria-label={t("blog.slider.label")}>
            <Swiper
              key={`${i18n.dir()}-${featuredPosts.map((post) => post.id).join("-")}`}
              dir={i18n.dir()}
              modules={[Pagination, Keyboard, A11y]}
              slidesPerView={1}
              spaceBetween={24}
              loop
              keyboard={{ enabled: true, onlyInViewport: true }}
              pagination={{ clickable: true, dynamicBullets: featuredPosts.length > 5 }}
              a11y={{
                prevSlideMessage: t("blog.slider.previous"),
                nextSlideMessage: t("blog.slider.next"),
                slideLabelMessage: t("blog.slider.slide", { index: "{{index}}", slidesLength: "{{slidesLength}}" }),
                paginationBulletMessage: t("blog.slider.goToSlide", { index: "{{index}}" }),
              }}
              className="!pb-9 [&_.swiper-wrapper]:items-stretch [&_.swiper-pagination-bullet]:bg-primary [&_.swiper-pagination-bullet]:opacity-40 [&_.swiper-pagination-bullet-active]:opacity-100"
            >
              {featuredPosts.map((post) => (
                <SwiperSlide key={post.slug} className="!flex !h-auto">
                  <BlogCard post={post} featured />
                </SwiperSlide>
              ))}
            </Swiper>
          </section>
        )
      )}

      <section
        className={featuredPosts.length ? "mt-12 sm:mt-16" : "mt-8"}
        aria-labelledby="blog-results-heading"
      >
        <div className="mb-7 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2
              id="blog-results-heading"
              ref={resultsHeadingRef}
              tabIndex={-1}
              className="scroll-mt-32 text-2xl font-semibold tracking-tight focus:outline-none sm:text-3xl"
            >
              {hasFilters
                ? t("blog.results", { count: total })
                : t("blog.latest")}
            </h2>
            {!hasFilters && (
              <p className="mt-2 text-sm text-default-500">
                {t("blog.latestSubtitle")}
              </p>
            )}
          </div>
          <p
            role="status"
            aria-live="polite"
            aria-atomic="true"
            className="text-xs text-default-500"
          >
            {isLoading
              ? t("blog.loading")
              : blogsError
                ? t("blog.loadErrorTitle")
                : total
                  ? t("blog.pagination.summary", {
                      from: numberFormat.format(from),
                      to: numberFormat.format(to),
                      total: numberFormat.format(total),
                    })
                  : t("blog.results", { count: 0 })}
          </p>
        </div>

        {isLoading ? (
          <div
            aria-busy="true"
            aria-label={t("blog.loading")}
            className="grid grid-cols-1 gap-x-7 gap-y-10 min-[600px]:grid-cols-2 min-[1100px]:grid-cols-3"
          >
            {[0, 1, 2].map((index) => (
              <div key={index} aria-hidden="true" className="animate-pulse">
                <div className="aspect-[16/11] rounded-2xl bg-default-200/60" />
                <div className="mt-5 h-3 w-20 rounded bg-default-200/60" />
                <div className="mt-4 h-6 w-5/6 rounded bg-default-200/60" />
                <div className="mt-3 h-4 rounded bg-default-200/60" />
                <div className="mt-2 h-4 w-2/3 rounded bg-default-200/60" />
              </div>
            ))}
          </div>
        ) : blogsError ? (
          <div
            className="rounded-3xl bg-content2/40 px-6 py-16 text-center"
            role="alert"
          >
            <h3 className="text-xl font-semibold tracking-tight">
              {t("blog.loadErrorTitle")}
            </h3>
            <p className="mt-3 text-sm leading-6 text-default-500">
              {t("blog.loadErrorDescription")}
            </p>
            <button
              type="button"
              disabled={blogsValidating}
              onClick={() => void retryBlogs()}
              className="mt-6 rounded-full bg-foreground px-5 py-2.5 text-sm text-background disabled:opacity-50 focus-visible:outline-primary"
            >
              {t("blog.retry")}
            </button>
          </div>
        ) : posts.length ? (
          <div className="grid grid-cols-1 gap-x-7 gap-y-10 min-[600px]:grid-cols-2 min-[1100px]:grid-cols-3">
            {posts.map((post) => (
              <BlogCard key={post.slug} post={post} editorPick={featuredSlugs.has(post.slug)} />
            ))}
          </div>
        ) : (
          <div className="flex flex-col items-center rounded-3xl border border-dashed border-default-300 bg-content2/30 px-6 py-16 text-center">
            <span className="mb-5 flex size-14 items-center justify-center rounded-full bg-content2">
              <SearchX
                size={24}
                className="text-default-500"
                aria-hidden="true"
              />
            </span>
            <h3 className="text-xl font-semibold tracking-tight">
              {t(hasFilters ? "blog.noResultsTitle" : "blog.emptyTitle")}
            </h3>
            <p className="mb-6 mt-3 max-w-sm text-sm leading-6 text-default-500">
              {t(
                hasFilters
                  ? "blog.noResultsDescription"
                  : "blog.emptyDescription",
              )}
            </p>
            {hasFilters && (
              <button
                type="button"
                onClick={resetFilters}
                className="min-h-11 cursor-pointer rounded-full bg-foreground px-6 py-2.5 text-sm text-background hover:bg-primary hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary"
              >
                {t("blog.resetFilters")}
              </button>
            )}
          </div>
        )}

        {!isLoading && !blogsError && pageCount > 1 && (
          <nav
            aria-label={t("blog.pagination.label")}
            className="mt-9 flex justify-center"
          >
            <div className="inline-flex items-center gap-0.5 sm:gap-1">
              <button
                type="button"
                aria-label={t("blog.pagination.previous")}
                disabled={currentPage === 1}
                onClick={() => changePage(currentPage - 1)}
                className="inline-flex h-8 shrink-0 cursor-pointer items-center justify-center gap-1 rounded-sm pe-2 text-default-500 transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-default disabled:opacity-35 disabled:hover:text-default-500 sm:pe-3"
              >
                <ChevronLeft
                  size={18}
                  aria-hidden="true"
                  className="rtl:rotate-180"
                />
                <span>{t("blog.pagination.previousShort")}</span>
              </button>
              {getBlogPaginationItems(pageCount, currentPage).map((page) =>
                typeof page === "number" ? (
                  <button
                    key={page}
                    type="button"
                    aria-label={t("blog.pagination.pageLabel", {
                      page: numberFormat.format(page),
                    })}
                    aria-current={page === currentPage ? "page" : undefined}
                    onClick={() => changePage(page)}
                    className={`flex h-8 min-w-7 shrink-0 cursor-pointer items-center justify-center rounded-sm px-1 tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${page === currentPage ? "font-semibold text-primary underline decoration-2 underline-offset-[5px]" : "text-default-500 hover:text-foreground"}`}
                  >
                    {numberFormat.format(page)}
                  </button>
                ) : (
                  <span
                    key={page}
                    aria-hidden="true"
                    className="flex h-8 min-w-5 shrink-0 items-center justify-center text-default-400"
                  >
                    …
                  </span>
                ),
              )}
              <button
                type="button"
                aria-label={t("blog.pagination.next")}
                disabled={currentPage === pageCount}
                onClick={() => changePage(currentPage + 1)}
                className="inline-flex h-8 shrink-0 cursor-pointer items-center justify-center gap-1 rounded-sm ps-2 text-default-500 transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-default disabled:opacity-35 disabled:hover:text-default-500 sm:ps-3"
              >
                <span>{t("blog.pagination.nextShort")}</span>
                <ChevronRight
                  size={18}
                  aria-hidden="true"
                  className="rtl:rotate-180"
                />
              </button>
            </div>
          </nav>
        )}
      </section>

      <aside className="mt-14 flex flex-col items-start justify-between gap-6 rounded-3xl border border-primary/10 bg-primary/5 p-6 sm:p-9 min-[900px]:flex-row min-[900px]:items-center">
        <div className="flex items-start gap-4">
          <span className="hidden size-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary sm:flex">
            <BookOpen size={22} strokeWidth={1.5} aria-hidden="true" />
          </span>
          <div>
            <h2 className="text-xl font-semibold tracking-tight">
              {t("blog.shopCtaTitle")}
            </h2>
            <p className="mt-2 max-w-xl text-sm leading-6 text-default-500">
              {t("blog.shopCtaDescription")}
            </p>
          </div>
        </div>
        <Link
          href="/categories/"
          className="inline-flex min-h-11 shrink-0 items-center gap-3 rounded-full bg-foreground px-5 py-3 text-sm text-background transition-colors hover:bg-primary hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary"
        >
          {t("blog.shopCtaButton")}
          <ArrowRight size={16} className="rtl:rotate-180" aria-hidden="true" />
        </Link>
      </aside>
    </div>
  );
}
