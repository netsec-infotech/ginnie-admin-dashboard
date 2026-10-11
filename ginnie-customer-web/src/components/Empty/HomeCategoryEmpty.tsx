import { FC } from "react";
import { useRouter } from "next/router";
import { Button } from "@heroui/react";
import { ArrowRight, PackageOpen } from "lucide-react";
import { useTranslation } from "react-i18next";
import NoProductsFound from "@/components/NoProductsFound";

interface HomeCategoryEmptyProps {
  /**
   * Display title of the active category. Defaults to a title derived from the
   * slug in the URL; pass this when the real title is already to hand.
   */
  categoryTitle?: string;
}

/**
 * Best-effort display name from a slug ("home-decor-12" -> "Home Decor").
 * The category list lives in the navbar, outside this tree, so fetching the real
 * title here would mean a second request purely for a heading. The trailing
 * numeric id backend slugs carry is dropped.
 */
const titleFromSlug = (slug: string) =>
  slug
    .replace(/-\d+$/, "")
    .split("-")
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");

/**
 * Shown on the homepage when the active category tab has no content at the
 * user's location. Every homepage rail hides itself when empty, so without this
 * an empty category renders a blank page with no way back.
 *
 * The primary action clears the category scope rather than linking to "/", so it
 * works identically on both homepage variants and routes through the same
 * `?category=all` reset the header logo uses.
 */
const HomeCategoryEmpty: FC<HomeCategoryEmptyProps> = ({ categoryTitle }) => {
  const { t } = useTranslation();
  const router = useRouter();

  const activeSlug = (router.query.category as string) || "";
  const label =
    categoryTitle ||
    (activeSlug && activeSlug !== "all" ? titleFromSlug(activeSlug) : "");

  const showAll = () => {
    router.replace(
      { pathname: router.pathname, query: {} },
      undefined,
      { shallow: true }
    );
  };

  return (
    <NoProductsFound
      icon={PackageOpen}
      title={t("home.empty.title")}
      description={
        label
          ? t("home.empty.description", { category: label })
          : t("home.empty.description_generic")
      }
      customActions={
        <div className="flex w-full justify-center items-center gap-3">
          <Button
            color="primary"
            className="h-8"
            variant="solid"
            onPress={showAll}
            endContent={<ArrowRight size={16} />}
          >
            {t("home.empty.view_all")}
          </Button>
          <Button
            className="h-8"
            variant="bordered"
            onPress={() => router.push("/categories")}
          >
            {t("home.empty.browse_all")}
          </Button>
        </div>
      }
    />
  );
};

export default HomeCategoryEmpty;
