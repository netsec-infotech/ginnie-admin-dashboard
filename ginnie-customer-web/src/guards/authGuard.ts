// src/guards/authGuard.ts
import { GetServerSidePropsContext } from "next";
import { getAccessTokenFromContext } from "@/helpers/auth";

export const PROTECTED_ROUTES = [
  "/cart",
  "/my-account",
  "/my-account/orders",
  "/my-account/addresses",
  "/my-account/wallet",
  "/my-account/loyalty-points",
  "/my-account/transactions",
  "/my-account/refer-and-earn",
];

/**
 * Route prefixes that must never appear in search results.
 *
 * Private, transactional or per-user pages — nothing here has search value, and
 * some would leak account context. Deliberately a SUPERSET of
 * `PROTECTED_ROUTES`: `/shopping-list` and `/forgot-password` aren't
 * auth-gated (a logged-out visitor can reach both) but still shouldn't rank.
 *
 * robots.txt already disallows crawling most of these, but that only blocks
 * crawling — a URL linked from elsewhere can still be indexed without ever
 * being fetched. Only a `noindex` tag keeps it out.
 */
export const NOINDEX_ROUTES = [
  "/cart",
  "/checkout",
  "/my-account",
  "/shopping-list",
  "/forgot-password",
  "/404",
];

/** Prefix match, so `/my-account/orders/123` is covered by `/my-account`. */
export const isNoIndexRoute = (path: string): boolean => {
  if (!path) return false;
  // Compare without a trailing slash: `trailingSlash: true` means pathnames
  // arrive as "/cart/" in some contexts and "/cart" in others.
  const clean = path.length > 1 ? path.replace(/\/$/, "") : path;
  return NOINDEX_ROUTES.some(
    (route) => clean === route || clean.startsWith(`${route}/`),
  );
};

export const isProtectedRoute = (path: string) => {
  return PROTECTED_ROUTES.some((route) => {
    if (route.endsWith("/*")) {
      const baseRoute = route.slice(0, -2);
      return path.startsWith(baseRoute);
    }
    return path === route;
  });
};

export const serverSideAuthGuard = async (
  context: GetServerSidePropsContext
) => {
  try {
    const access_token = await getAccessTokenFromContext(context);

    if (!access_token) {
      return {
        redirect: {
          destination: "/?auth=required",
          permanent: false,
        },
      };
    }

    return null;
  } catch (error) {
    console.error("Auth guard error:", error);
    return {
      redirect: {
        destination: "/?auth=error",
        permanent: false,
      },
    };
  }
};
