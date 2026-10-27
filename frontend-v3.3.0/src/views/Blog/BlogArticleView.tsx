import { useMemo, useState } from "react";
import Link from "next/link";
import Image from "next/image";
import { ArrowLeft, ArrowRight, ChevronRight, ShoppingBag } from "lucide-react";
import { useTranslation } from "react-i18next";
import BlogCard from "@/components/Blog/BlogCard";
import BlogImage from "@/components/Blog/BlogImage";
import BlogMeta from "@/components/Blog/BlogMeta";
import BlogProductsSection from "@/components/Blog/BlogProductsSection";
import ShareButton from "@/components/Functional/ShareButton";
import { blogPlainText, prepareBlogContent } from "@/helpers/blog";
import { useSettings } from "@/contexts/SettingsContext";
import { getSiteSeo } from "@/config/siteSeo";
import type { BlogPost } from "@/types/blog";

interface BlogArticleViewProps {
  post: BlogPost;
  relatedPosts: BlogPost[];
}

function AuthorAvatar({
  initials,
  photo,
}: {
  initials: string;
  photo?: string | null;
}) {
  const [failedPhoto, setFailedPhoto] = useState<string | null>(null);
  return (
    <span className="blog-article-avatar overflow-hidden" aria-hidden="true">
      {photo && photo !== failedPhoto ? (
        <Image
          src={photo}
          alt=""
          width={42}
          height={42}
          className="size-full object-cover"
          onError={() => setFailedPhoto(photo)}
        />
      ) : (
        initials
      )}
    </span>
  );
}

export default function BlogArticleView({
  post,
  relatedPosts,
}: BlogArticleViewProps) {
  const { t } = useTranslation();
  const { webSettings } = useSettings();
  const { html } = useMemo(
    () => prepareBlogContent(post.content),
    [post.content],
  );
  const author = post.author || t("blog.editorialTeam");
  const authorInitials = author
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => Array.from(word)[0] || "")
    .join("");

  return (
    <div className="blog-article-page">
      <nav
        className="blog-article-breadcrumb"
        aria-label={t("blog.breadcrumbLabel")}
      >
        <ol>
          <li>
            <Link href="/">{t("nav.home")}</Link>
          </li>
          <li aria-hidden="true">
            <ChevronRight size={13} className="rtl:rotate-180" />
          </li>
          <li>
            <Link href="/blogs/">{t("nav.blog")}</Link>
          </li>
          <li aria-hidden="true">
            <ChevronRight size={13} className="rtl:rotate-180" />
          </li>
          <li className="blog-article-breadcrumb-current" aria-current="page">
            {post.title}
          </li>
        </ol>
      </nav>

      <article aria-labelledby="blog-article-title">
        <header className="blog-article-header">
          <div className="blog-article-eyebrow">
            {post.category.name && post.category.slug && (
              <Link
                className="blog-article-category"
                href={{
                  pathname: "/blogs/",
                  query: { category: post.category.slug },
                }}
              >
                {post.category.name}
              </Link>
            )}
          </div>
          <div className="blog-article-title-row">
            <h1 id="blog-article-title">{post.title}</h1>
          </div>
          <p className="blog-article-excerpt">{post.excerpt}</p>

          <div className="blog-article-header-bottom">
            <div className="blog-article-byline">
              <AuthorAvatar
                initials={authorInitials}
                photo={post.authorPhoto}
              />
              <BlogMeta post={post} className="blog-article-meta" />
            </div>
            <ShareButton
              title={post.title}
              description={blogPlainText(post.excerpt)}
              url={() =>
                new URL(
                  `/blogs/${encodeURIComponent(post.slug)}/`,
                  window.location.origin,
                ).toString()
              }
              fallbackText={t("blog.shareText", {
                title: post.title,
                siteName: getSiteSeo(webSettings).siteName,
              })}
              ariaLabel={t("blog.share")}
              successMessage={t("sharing.copied")}
              errorMessage={t("sharing.failed")}
            />
          </div>
        </header>

        <BlogImage
          post={post}
          priority
          className="blog-article-cover"
          sizes="(max-width: 1240px) calc(100vw - 32px), 1208px"
        />

        <div className="blog-article-reading-layout">
          <div className="blog-article-reading-column">
            <div
              className="blog-article-prose"
              dangerouslySetInnerHTML={{ __html: html }}
            />

            {post.tags.length > 0 && (
              <ul className="blog-article-tags">
                {post.tags.map((tag) => (
                  <li key={tag}>{tag}</li>
                ))}
              </ul>
            )}
            <div className="blog-article-author-card">
              <AuthorAvatar
                initials={authorInitials}
                photo={post.authorPhoto}
              />
              <div>
                <span className="blog-article-author-label">
                  {t("blog.authorLabel")}
                </span>
                <p>{author}</p>
                {post.authorBio && (
                  <p className="!mt-1 !font-normal text-default-500">
                    {post.authorBio}
                  </p>
                )}
              </div>
              <Link
                href="/blogs/"
                className="blog-article-author-back"
                aria-label={t("blog.backToBlog")}
              >
                <ArrowLeft
                  size={16}
                  className="rtl:rotate-180"
                  aria-hidden="true"
                />
                <span>{t("blog.backToBlog")}</span>
              </Link>
            </div>
          </div>
        </div>
      </article>

      <BlogProductsSection post={post} />

      {relatedPosts.length > 0 && (
        <section
          className="blog-article-related"
          aria-labelledby="blog-related-title"
        >
          <div className="blog-article-section-heading">
            <div>
              <h2 id="blog-related-title">{t("blog.relatedTitle")}</h2>
              <p>{t("blog.relatedSubtitle")}</p>
            </div>
            <Link href="/blogs/" className="blog-article-explore-link">
              {t("blog.exploreAll")}
              <ArrowRight
                size={16}
                className="rtl:rotate-180"
                aria-hidden="true"
              />
            </Link>
          </div>
          <div className="blog-article-related-grid">
            {relatedPosts.slice(0, 3).map((relatedPost) => (
              <BlogCard key={relatedPost.id} post={relatedPost} />
            ))}
          </div>
        </section>
      )}

      <section
        className="blog-article-shop-cta"
        aria-labelledby="blog-shop-cta-title"
      >
        <div className="blog-article-shop-icon" aria-hidden="true">
          <ShoppingBag size={31} strokeWidth={1.5} />
        </div>
        <div className="blog-article-shop-copy">
          <h2 id="blog-shop-cta-title">{t("blog.shopCtaTitle")}</h2>
          <p>{t("blog.shopCtaDescription")}</p>
        </div>
        <Link href="/categories/" className="blog-article-shop-link">
          {t("blog.shopCtaButton")}
          <ArrowRight size={16} className="rtl:rotate-180" aria-hidden="true" />
        </Link>
      </section>
    </div>
  );
}
