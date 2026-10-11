import { ReactNode, useEffect } from "react";
import { useRouter } from "next/router";
import { useTranslation } from "react-i18next";

import OfflinePage from "@/components/OfflinePage";
import PageHead from "@/SEO/PageHead";
import { NextPageWithLayout } from "@/types";

/**
 * The standalone /offline route.
 *
 * There are two separate "you're offline" paths, and this is the second one:
 *
 * 1. The app is already loaded and the connection drops — `DefaultLayout`
 *    watches `navigator.onLine` and swaps in the same <OfflinePage /> inline.
 * 2. A COLD load while offline (bookmark, refresh, shared link). The app shell
 *    never boots, so nothing in React can run; the browser shows its own error
 *    page. This route is precached and registered as the service worker's
 *    document fallback (see `fallbacks` in next.config.ts) so the user gets
 *    this page instead. That's what this file exists for.
 */
const Offline: NextPageWithLayout = () => {
  const { t } = useTranslation();
  const router = useRouter();

  // Case 2 has no layout watching the connection, so recover here.
  useEffect(() => {
    const handleOnline = () => {
      // As a fallback the service worker serves this page *under the URL the
      // user actually asked for*, so a reload retries that URL. Only someone
      // who navigated to /offline itself needs sending somewhere useful.
      if (window.location.pathname.replace(/\/+$/, "") === "/offline") {
        router.replace("/");
      } else {
        window.location.reload();
      }
    };

    window.addEventListener("online", handleOnline);
    return () => window.removeEventListener("online", handleOnline);
  }, [router]);

  return (
    <>
      <PageHead pageTitle={t("pageTitle.offline")} noindex />
      <OfflinePage />
    </>
  );
};

// No DefaultLayout: its header/footer are driven by settings fetched over the
// network, so the chrome would render as an empty shell on the one page that
// exists precisely because there is no network.
Offline.getLayout = (page: ReactNode) => page;

export default Offline;
