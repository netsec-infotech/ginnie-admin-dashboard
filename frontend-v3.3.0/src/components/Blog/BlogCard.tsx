import Link from "next/link";
import { ArrowUpRight, ArrowRight, Star } from "lucide-react";
import { useTranslation } from "react-i18next";
import BlogImage from "@/components/Blog/BlogImage";
import BlogMeta from "@/components/Blog/BlogMeta";
import type { BlogPost } from "@/types/blog";

export default function BlogCard({
  post,
  featured = false,
  editorPick = false,
}: {
  post: BlogPost;
  featured?: boolean;
  editorPick?: boolean;
}) {
  const { t } = useTranslation();

  if (featured) {
    return (
      <article className="flex w-full min-w-0">
        <Link
          href={`/blogs/${encodeURIComponent(post.slug)}/`}
          className="group grid w-full grid-rows-[auto_1fr] overflow-hidden rounded-[1.75rem] border border-default-200/70 bg-[#f5f4ef] outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-4 dark:bg-content1 min-[900px]:grid-cols-[0.85fr_1.15fr] min-[900px]:grid-rows-1"
        >
          <div className="order-2 flex flex-col justify-center p-6 sm:p-8 min-[900px]:order-1 min-[1100px]:p-10">
            <div className="mb-5 flex flex-wrap items-center gap-2 text-xs">
              <span className="rounded-full bg-foreground px-3 py-1.5 text-background">
                {t("blog.featured")}
              </span>
              {post.category.name && (
                <span className="rounded-full border border-default-300 px-3 py-1.5 text-default-600">
                  {post.category.name}
                </span>
              )}
            </div>
            <h2 className="max-w-xl text-2xl font-semibold leading-[1.2] tracking-tight transition-colors group-hover:text-primary sm:text-3xl min-[1100px]:text-[2.25rem]">
              {post.title}
            </h2>
            <p className="mt-4 max-w-lg text-sm leading-7 text-default-600 min-[1100px]:text-base">
              {post.excerpt}
            </p>
            <BlogMeta post={post} className="mt-6" />
            <div className="mt-7 flex items-center gap-2 text-sm font-medium text-foreground">
              <span>{t("blog.readArticle")}</span>
              <span className="flex size-9 items-center justify-center rounded-full bg-foreground text-background transition-colors group-hover:bg-primary group-hover:text-white">
                <ArrowRight
                  size={17}
                  className="rtl:rotate-180"
                  aria-hidden="true"
                />
              </span>
            </div>
          </div>
          <BlogImage
            post={post}
            priority
            sizes="(max-width: 899px) 100vw, 60vw"
            className="order-1 aspect-[16/10] min-[900px]:order-2 min-[900px]:aspect-auto min-[900px]:min-h-[440px]"
          />
        </Link>
      </article>
    );
  }

  return (
    <article className="min-w-0">
      <Link
        href={`/blogs/${encodeURIComponent(post.slug)}/`}
        className="group flex h-full flex-col rounded-2xl outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-4"
      >
        <div className="relative">
          <BlogImage post={post} className="aspect-[16/11] w-full rounded-2xl" />
          {editorPick && (
            <span className="absolute end-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-black/75 px-2.5 py-1.5 text-xs font-medium text-white backdrop-blur-sm">
              <Star size={13} className="fill-amber-400 text-amber-400" aria-hidden="true" />
              {t("blog.featured")}
            </span>
          )}
        </div>
        <div className="flex grow flex-col px-1 pt-5">
          {post.category.name && (
            <span className="mb-3 text-xs font-medium text-primary">
              {post.category.name}
            </span>
          )}
          <div className="flex items-start justify-between gap-4">
            <h3 className="text-xl font-semibold leading-snug tracking-tight transition-colors group-hover:text-primary">
              {post.title}
            </h3>
            <ArrowUpRight
              size={20}
              className="mt-1 shrink-0 text-default-400 transition-colors group-hover:text-primary rtl:-rotate-90"
              aria-hidden="true"
            />
          </div>
          <p className="mb-5 mt-3 line-clamp-2 text-sm leading-6 text-default-500">
            {post.excerpt}
          </p>
          <BlogMeta
            post={post}
            className="mt-auto border-t border-default-200/70 pt-4"
          />
        </div>
      </Link>
    </article>
  );
}
