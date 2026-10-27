import Document, {
  Html,
  Head,
  Main,
  NextScript,
  type DocumentContext,
  type DocumentInitialProps,
} from "next/document";
import clsx from "clsx";
import { devInspectorHookScript } from "next-dev-inspector/hook";
import { fontSans } from "@/config/fonts";
import { getRequestLocalization, type ServerLocalization } from "@/lib/localization/server";

type Props = DocumentInitialProps & { localization?: ServerLocalization };

export default class CustomDocument extends Document {
  static async getInitialProps(ctx: DocumentContext): Promise<Props> {
    const props = await Document.getInitialProps(ctx);
    return { ...props, localization: getRequestLocalization(ctx.req) };
  }

  render() {
    const localization = (this.props as Props).localization;
    return (
    <Html
      lang={localization?.snapshot.locale || "en"}
      dir={localization?.snapshot.direction || "ltr"}
      prefix="og: https://ogp.me/ns#"
      suppressHydrationWarning
    >
      <Head>
        {!localization && <>
        {/* Static HTML is English. Hide it for a saved non-English locale until
            the bundled local dictionary has replaced it after hydration. */}
        <script
          dangerouslySetInnerHTML={{
            __html: `(function(){try{var m=document.cookie.match(/(?:^|;\\s*)i18nextLng=([^;]*)/);if(!m)return;var l=JSON.parse(decodeURIComponent(m[1]));if(typeof l==='string'&&l&&l!=='en'){var h=document.documentElement;h.setAttribute('data-locale-pending','');h.setAttribute('lang',l);h.setAttribute('dir',l==='ar'?'rtl':'ltr');setTimeout(function(){if(h.hasAttribute('data-locale-pending')){h.setAttribute('lang','en');h.setAttribute('dir','ltr');h.removeAttribute('data-locale-pending')}},8000)}}catch(e){}})();`,
          }}
        />
        <style>{`html[data-locale-pending] #__next { visibility: hidden; }`}</style>
        </>}
        {/* Synchronous theme initialization to prevent theme jolt / flash on initial load */}
        <script
          dangerouslySetInnerHTML={{
            __html: `(function(){try{var d=document.documentElement,c=d.classList,e=localStorage.getItem('theme'),m=e==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches;if(e==='dark'||m){c.add('dark');d.style.colorScheme='dark';}else{c.remove('dark');d.style.colorScheme='light';}}catch(t){}})();`,
          }}
        />
        {/* DevInspector early hook: enables true re-render flashes with
            component names. Must run before React loads; dev only. */}
        {process.env.NODE_ENV === "development" &&
          process.env.NEXT_PUBLIC_DEV_INSPECTOR === "true" && (
            <script
              dangerouslySetInnerHTML={{ __html: devInspectorHookScript }}
            />
          )}
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#ffffff" />

        <link rel="apple-touch-icon" href="/logo-192.png" />
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
          "min-h-screen bg-background text-foreground font-sans antialiased",
          fontSans.variable
        )}
      >
        <Main />
        <NextScript />
      </body>
    </Html>
    );
  }
}
