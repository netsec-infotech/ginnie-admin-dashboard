import { Html, Head, Main, NextScript } from "next/document";
import clsx from "clsx";
import { devInspectorHookScript } from "next-dev-inspector/hook";
import { fontSans } from "@/config/fonts";

export default function Document() {
  return (
    <Html lang="en" prefix="og: https://ogp.me/ns#">
      <Head>
        {/* DevInspector early hook: enables true re-render flashes with
            component names. Must run before React loads; dev only. */}
        {process.env.NODE_ENV === "development" &&
          process.env.NEXT_PUBLIC_DEV_INSPECTOR === "true" && (
            <script
              dangerouslySetInnerHTML={{ __html: devInspectorHookScript }}
            />
          )}
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#000000" />

        <link rel="apple-touch-icon" href="/icons/icon-192x192.png" />
        <meta
          name="google-site-verification"
          content="myEMkqRat5aCxpIq0mD1HfuiWYhtSUOYILkM_fothqo"
        />

        {/* Preconnect to Google Fonts for better performance */}
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
          rel="preconnect"
          href="https://fonts.gstatic.com"
          crossOrigin="anonymous"
        />
      </Head>
      <body
        className={clsx(
          "min-h-screen bg-background font-sans antialiased",
          fontSans.variable
        )}
      >
        <Main />
        <NextScript />
      </body>
    </Html>
  );
}
