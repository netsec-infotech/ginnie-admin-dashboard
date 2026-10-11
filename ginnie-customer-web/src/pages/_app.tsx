import type { AppProps } from "next/app";
import { HeroUIProvider } from "@heroui/system";
import { ThemeProvider as NextThemesProvider } from "next-themes";
import { useRouter } from "next/router";
import dynamic from "next/dynamic";
import { useEffect } from "react";
import ReduxProvider from "@/lib/redux/ReduxProvider";
import DefaultLayout from "@/layouts/default";
import { NextPageWithLayout } from "@/types";
import { fontSans, fontMono } from "@/config/fonts";
import { trackPageView } from "@/lib/analytics";
import "@/styles/index.css";
import { CircleX } from "lucide-react";
import i18n from "../../i18n";

const ToastProvider = dynamic(
  () => import("@heroui/react").then((mod) => mod.ToastProvider),
  { ssr: false }
);

const ProgressBar = dynamic(() => import("@/components/ProgressBar"), {
  ssr: false,
});

// Dev-only element-source inspector. The NODE_ENV check makes the whole
// import dead code in production builds regardless of the env flag.
const DevInspector =
  process.env.NODE_ENV === "development" &&
  process.env.NEXT_PUBLIC_DEV_INSPECTOR === "true"
    ? dynamic(() => import("next-dev-inspector"), { ssr: false })
    : null;

type AppPropsWithLayout = AppProps & {
  Component: NextPageWithLayout;
};

function App({ Component, pageProps }: AppPropsWithLayout) {
  const router = useRouter();

  // // Set initial RTL direction based on language
  useEffect(() => {
    const currentLang = i18n.language;
    if (currentLang === "ar") {
      document.documentElement.setAttribute("dir", "rtl");
      document.documentElement.setAttribute("lang", "ar");
    } else {
      document.documentElement.setAttribute("dir", "ltr");
      document.documentElement.setAttribute("lang", currentLang);
    }
  }, []);

  // Track page views on route changes
  useEffect(() => {
    const handleRouteChange = (url: string) => {
      // Get the page title from document or use the URL as fallback
      const pageTitle = document.title || url;
      trackPageView(url, pageTitle);
    };

    // Track initial page view
    handleRouteChange(router.pathname);

    // Listen to route changes
    router.events.on("routeChangeComplete", handleRouteChange);

    // Cleanup listener on unmount
    return () => {
      router.events.off("routeChangeComplete", handleRouteChange);
    };
  }, [router.events, router.pathname]);

  // ✅ Use custom layout if defined, else wrap in DefaultLayout
  const getLayout =
    Component.getLayout ??
    ((page) => (
      <DefaultLayout initialSettings={pageProps?.initialSettings}>
        {page}
      </DefaultLayout>
    ));

  return (
    <HeroUIProvider navigate={router.push}>
      <NextThemesProvider
        defaultTheme="system"
        attribute="class"
        disableTransitionOnChange
      >
        <ProgressBar />
        {DevInspector && (
          <DevInspector
            getI18nData={() => ({
              data: i18n.store.data as unknown as Record<string, unknown>,
              language: i18n.language,
            })}
            getStateSnapshot={async () =>
              (await import("@/lib/redux/store")).store.getState()
            }
            stateLabel="Redux store"
          />
        )}
        <ToastProvider
          placement="top-right"
          toastOffset={10}
          toastProps={{
            classNames: {
              base: "max-w-[calc(100vw-40px)] sm:max-w-md pr-6",
              description: "whitespace-normal line-clamp-none overflow-visible text-ellipsis-none",
            },

            timeout: 4000,
            closeIcon: (
              <CircleX
                size={34}
                strokeWidth={2.5}
                className="text-foreground/25"
              />
            ),
          }}
        />
        <ReduxProvider>{getLayout(<Component {...pageProps} />)}</ReduxProvider>
      </NextThemesProvider>
    </HeroUIProvider>
  );
}

export default App;

export const fonts = {
  sans: fontSans.style.fontFamily,
  mono: fontMono.style.fontFamily,
};
