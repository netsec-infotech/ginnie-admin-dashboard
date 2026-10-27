import NextApp, { type AppContext, type AppProps } from "next/app";
import { HeroUIProvider } from "@heroui/system";
import { ThemeProvider as NextThemesProvider } from "next-themes";
import { useRouter } from "next/router";
import Head from "next/head";
import dynamic from "next/dynamic";
import { useEffect } from "react";
import ReduxProvider from "@/lib/redux/ReduxProvider";
import DefaultLayout from "@/layouts/default";
import { NextPageWithLayout } from "@/types";
import { fontSans, fontMono } from "@/config/fonts";
import GoogleAnalytics from "@/components/Functional/GoogleAnalytics";
import MicrosoftClarity from "@/components/Functional/MicrosoftClarity";
import { trackPageView } from "@/lib/analytics";
import "@/styles/index.css";
import { CircleX } from "lucide-react";
import i18n from "../../i18n";
import LocalizationBootstrap from "@/components/Functional/LocalizationBootstrap";
import { I18nextProvider } from "react-i18next";
import { createServerI18n } from "@/lib/localization/serverI18n";
import { isSSR } from "@/helpers/getters";
import type { ServerLocalization } from "@/lib/localization/server";

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
  const initialLocalization = pageProps.initialLocalization as ServerLocalization | undefined;
  const translationInstance = typeof window === "undefined" && initialLocalization
    ? createServerI18n(initialLocalization.snapshot)
    : i18n;

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
    <I18nextProvider i18n={translationInstance}>
    <HeroUIProvider navigate={router.push}>
      <Head>
        <meta
          name="viewport"
          content="width=device-width, initial-scale=1, viewport-fit=cover"
          key="viewport"
        />
        <meta
          name="apple-mobile-web-app-status-bar-style"
          content="black-translucent"
          key="apple-status-bar-style"
        />
      </Head>
      <GoogleAnalytics />
      <MicrosoftClarity />
      <NextThemesProvider
        defaultTheme="light"
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
              title: "whitespace-normal overflow-visible text-clip break-words",
              description:
                "whitespace-normal overflow-visible text-clip break-words",
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
        <ReduxProvider
          initialLocalization={initialLocalization?.snapshot}
          initialLanguages={initialLocalization?.languages}
          initialDefaultLocale={initialLocalization?.defaultLocale}
        >
          <LocalizationBootstrap serverBootstrapped={Boolean(initialLocalization)} />
          {getLayout(<Component {...pageProps} />)}
        </ReduxProvider>
      </NextThemesProvider>
    </HeroUIProvider>
    </I18nextProvider>
  );
}

// The static build has no app-level data loader. SSR builds fetch one request
// snapshot before rendering any translated page, including pages without GSSP.
if (isSSR()) {
  App.getInitialProps = async (context: AppContext) => {
    const request = context.ctx.req;
    // Page data and localization are independent; start both together.
    const localizationPromise = request?.headers.host
      ? import("@/lib/localization/server").then(({ loadServerLocalization }) =>
          loadServerLocalization(request),
        )
      : null;
    const initial = await NextApp.getInitialProps(context);
    // Next also gives prerender workers a synthetic request while building
    // getStaticProps pages. A real HTTP request always has a Host header.
    if (!localizationPromise) return initial;
    context.ctx.res?.setHeader("Cache-Control", "private, no-store");
    const initialLocalization = await localizationPromise;
    return {
      ...initial,
      pageProps: { ...initial.pageProps, initialLocalization },
    };
  };
}

export default App;

export const fonts = {
  sans: fontSans.style.fontFamily,
  mono: fontMono.style.fontFamily,
};
