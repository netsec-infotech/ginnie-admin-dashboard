import { Clock3, Eye } from "lucide-react";
import { useTranslation } from "react-i18next";
import type { BlogPost } from "@/types/blog";

export default function BlogMeta({
  post,
  className = "",
}: {
  post: BlogPost;
  className?: string;
}) {
  const { t, i18n } = useTranslation();
  const date = post.publishedAt ? new Date(post.publishedAt) : null;
  const validDate = date && !Number.isNaN(date.getTime()) ? date : null;
  const readTime =
    typeof post.readTime === "number" &&
    Number.isFinite(post.readTime) &&
    post.readTime > 0
      ? Math.ceil(post.readTime)
      : null;
  const viewsCount = typeof post.viewsCount === "number" && Number.isSafeInteger(post.viewsCount) && post.viewsCount >= 0
    ? post.viewsCount : null;

  return (
    <div
      className={`flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-default-500 ${className}`}
    >
      {validDate && (
        <>
          <time dateTime={validDate.toISOString()}>
            {new Intl.DateTimeFormat(i18n.resolvedLanguage || "en", {
              month: "short",
              day: "numeric",
              year: "numeric",
              timeZone: "UTC",
            }).format(validDate)}
          </time>
          <span aria-hidden="true">·</span>
        </>
      )}
      {readTime !== null && (
        <>
          <span className="inline-flex items-center gap-1.5">
            <Clock3 size={14} aria-hidden="true" />
            {t("blog.minuteRead", { count: readTime })}
          </span>
          <span aria-hidden="true">·</span>
        </>
      )}
      {viewsCount !== null && (
        <>
          <span className="inline-flex items-center gap-1.5">
            <Eye size={14} aria-hidden="true" />
            {t("blog.views", {
              count: viewsCount,
              formattedCount: new Intl.NumberFormat(i18n.resolvedLanguage || i18n.language || "en").format(viewsCount),
            })}
          </span>
          <span aria-hidden="true">·</span>
        </>
      )}
      <span>{post.author || t("blog.editorialTeam")}</span>
    </div>
  );
}
