import React, {
  CSSProperties,
  FC,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  Navbar as HeroUINavbar,
  NavbarContent,
  NavbarMenu,
  NavbarMenuToggle,
  NavbarBrand,
  NavbarItem,
  NavbarMenuItem,
  Link,
  Image,
  useDisclosure,
  Button,
} from "@heroui/react";
import NextLink from "next/link";
import LocationSelector from "./Location/LocationSelector";
import { ThemeSwitch } from "./theme-switch";
import GlobalSearchbar from "./Functional/GlobalSearchbar";
import {
  ShoppingCart,
  Home,
  Tags,
  HelpCircle,
  Info,
  BookOpen,
  X,
} from "lucide-react";
import dynamic from "next/dynamic";
import { useSelector } from "react-redux";
import { RootState } from "@/lib/redux/store";
import { useRouter } from "next/router";
import { useSettings } from "@/contexts/SettingsContext";
import CategoryTabs from "./Functional/CategoryTabs";
import LanguageSwitcher from "./Functional/LanguageSwitcher";
import { useTranslation } from "react-i18next";
import HomeHeaderBackground from "./Functional/HomeHeaderBackground";
import { resolveHomeHeaderAppearance } from "@/helpers/homeHeaderAppearance";
import { useHeaderScroll } from "@/contexts/HeaderScrollContext";
const FallbackCartIcon = () => (
  <Link as={NextLink} href="/cart">
    <ShoppingCart size={21} className="!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))] cursor-pointer" />
  </Link>
);

const Badge = dynamic(() => import("@heroui/react").then((mod) => mod.Badge), {
  ssr: false,
  loading: () => <FallbackCartIcon />,
});

const ProfileBtn = dynamic(() => import("./ProfileBtn"), { ssr: false });
const LoginModal = dynamic(() => import("./Modals/LoginModal"), { ssr: false });
const OfflineCartDrawer = dynamic(() => import("./Cart/OfflineCartDrawer"), {
  ssr: false,
});

const MOBILE_HEADER_SCROLL_THRESHOLD = 48;
const MOBILE_HEADER_COLLAPSE_OFFSET = 56;
const MOBILE_HEADER_HIDE_DISTANCE = 12;
const MOBILE_HEADER_REVEAL_DISTANCE = 10;
const MOBILE_HEADER_TRANSITION_MS = 300;
const MOBILE_SCROLL_INTENT_WINDOW_MS = 1200;

export const Navbar: FC = () => {
  const { t } = useTranslation();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [showDemoWarning, setShowDemoWarning] = useState(true);
  const [isMobileThemeSurface, setIsMobileThemeSurface] = useState(false);
  const {
    isMobileHeaderExpanded,
    isMobileViewport,
    setIsMobileHeaderExpanded,
    setIsMobileViewport,
  } = useHeaderScroll();
  const isMobileHeaderExpandedRef = useRef(true);
  const isMobileThemeSurfaceRef = useRef(false);
  const mobileScrollAnchorY = useRef(0);
  const mobileTransitionLockUntil = useRef(0);
  const mobileScrollIntentAt = useRef(0);
  const mobileScrollIntentDirection = useRef<"up" | "down" | null>(null);
  const navbarRootRef = useRef<HTMLDivElement>(null);
  const mobileHeaderUpperRef = useRef<HTMLDivElement>(null);
  const [categoryHeaderBackgroundImage, setCategoryHeaderBackgroundImage] =
    useState<string | null>(null);
  const [
    categoryHeaderMobileBackgroundImage,
    setCategoryHeaderMobileBackgroundImage,
  ] = useState<string | null>(null);
  const [categoryHeaderBackgroundColor, setCategoryHeaderBackgroundColor] =
    useState<string | null>(null);
  const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
  const { settings, webSettings, demoMode, systemSettings, homeGeneralSettings } =
    useSettings();
  // Read the new API entry without changing the legacy settings tuple or its consumers.
  const settingsEntries: readonly { variable: string; value: unknown }[] | null =
    settings;
  const watchAndBuy = settingsEntries?.find(
    (entry) => entry.variable === "watch_and_buy",
  )?.value;
  const watchAndBuyTitle =
    watchAndBuy &&
    typeof watchAndBuy === "object" &&
    "enabled" in watchAndBuy &&
    watchAndBuy.enabled === true &&
    "title" in watchAndBuy &&
    typeof watchAndBuy.title === "string"
      ? watchAndBuy.title.trim()
      : "";
  const router = useRouter();
  const cartCount =
    useSelector((state: RootState) => (state.cart.pendingImportedCart ?? state.cart.cartData)?.items_count) || 0;

  const offLineCartCount =
    useSelector((state: RootState) => state.offlineCart.items)?.length || 0;
  const activeCartCount = isLoggedIn ? cartCount : offLineCartCount;

  const {
    isOpen: isOfflineCartOpen,
    onOpen: openOfflineCart,
    onClose: closeOfflineCart,
  } = useDisclosure();
  const {
    siteHeaderLogo = "https://placehold.co/160x40?text=Logo",
    siteHeaderDarkLogo = "https://placehold.co/160x40?text=Logo",
    siteName = "Site Logo",
  } = webSettings || {};

  const isHomePage = router.pathname === "/";
  const isBlogPage =
    router.pathname === "/blogs" || router.pathname.startsWith("/blogs/");

  useEffect(() => {
    // The navbar-height custom property is only consumed by category pages.
    // Observing it on the home page would publish every intermediate height of
    // the compact animation to :root and invalidate styles across the page.
    if (isHomePage) {
      document.documentElement.style.removeProperty("--site-navbar-height");
      return;
    }

    const navbarRoot = navbarRootRef.current;
    if (!navbarRoot) return;

    const documentRoot = document.documentElement;
    const updateNavbarHeight = () => {
      documentRoot.style.setProperty(
        "--site-navbar-height",
        `${navbarRoot.getBoundingClientRect().height}px`,
      );
    };

    updateNavbarHeight();
    const resizeObserver = new ResizeObserver(updateNavbarHeight);
    resizeObserver.observe(navbarRoot);

    return () => {
      resizeObserver.disconnect();
      documentRoot.style.removeProperty("--site-navbar-height");
    };
  }, [isHomePage]);

  const homeHeaderAppearance = useMemo(
    () =>
      resolveHomeHeaderAppearance(
        homeGeneralSettings,
        categoryHeaderBackgroundImage,
        categoryHeaderBackgroundColor,
        categoryHeaderMobileBackgroundImage,
      ),
    [
      categoryHeaderBackgroundColor,
      categoryHeaderBackgroundImage,
      categoryHeaderMobileBackgroundImage,
      homeGeneralSettings,
    ],
  );
  const handleCategoryBackgroundImageChange = useCallback(
    (
      backgroundImage: string | null,
      backgroundColor: string | null,
      mobileBackgroundImage: string | null,
    ) => {
      setCategoryHeaderBackgroundImage(backgroundImage);
      setCategoryHeaderBackgroundColor(backgroundColor);
      setCategoryHeaderMobileBackgroundImage(mobileBackgroundImage);
    },
    [],
  );
  const hasHomeHeaderMedia = homeHeaderAppearance.type !== "color";
  const headerStyle = isHomePage
    ? isMobileThemeSurface
      ? ({
          "--home-header-foreground": "hsl(var(--heroui-foreground))",
          "--home-category-foreground": "hsl(var(--heroui-foreground))",
          color: "hsl(var(--heroui-foreground))",
        } as CSSProperties)
      : homeHeaderAppearance.fontColor
        ? ({
            "--home-header-foreground": homeHeaderAppearance.fontColor,
            "--home-category-foreground": homeHeaderAppearance.fontColor,
            color: homeHeaderAppearance.fontColor,
          } as CSSProperties)
        : undefined
    : ({
        // Home category colours are document-level so the hero and tabs can
        // share them. Reset the header fallbacks on other pages so stale home
        // foreground values cannot make controls unreadable.
        "--home-header-foreground": "hsl(var(--heroui-foreground))",
        "--home-category-foreground": "hsl(var(--heroui-foreground))",
      } as CSSProperties);

  useEffect(() => {
    if (webSettings?.headerScript) {
      const temp = document.createElement("div");
      temp.innerHTML = webSettings.headerScript;

      // Append each <script> dynamically
      Array.from(temp.querySelectorAll("script")).forEach((oldScript) => {
        const newScript = document.createElement("script");
        if (oldScript.src) {
          newScript.src = oldScript.src;
        }
        if (oldScript.textContent) {
          newScript.textContent = oldScript.textContent;
        }
        document.head.appendChild(newScript);
      });
    }
  }, [webSettings?.headerScript]);

  useEffect(() => {
    if (isLoggedIn && isOfflineCartOpen) {
      closeOfflineCart();
    }
  }, [isLoggedIn, isOfflineCartOpen, closeOfflineCart]);

  useEffect(() => {
    mobileScrollAnchorY.current = Math.max(0, window.scrollY);
    const desktopMedia = window.matchMedia("(min-width: 768px)");
    const reducedMotionMedia = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    );
    const stickyHeader = navbarRootRef.current?.closest<HTMLElement>(
      "[data-home-sticky-header]",
    );
    let heroSection = document.querySelector<HTMLElement>(
      'section[data-testid^="home-hero-"]',
    );
    let mobileHeaderCollapseOffset =
      mobileHeaderUpperRef.current?.scrollHeight ||
      MOBILE_HEADER_COLLAPSE_OFFSET;
    let scrollFrame: number | null = null;
    let transitionSettleTimer: number | null = null;
    let lastTouchY: number | null = null;

    const measureMobileHeaderCollapseOffset = () => {
      if (
        desktopMedia.matches ||
        !isMobileHeaderExpandedRef.current ||
        performance.now() < mobileTransitionLockUntil.current
      ) {
        return;
      }

      mobileHeaderCollapseOffset =
        mobileHeaderUpperRef.current?.scrollHeight ||
        MOBILE_HEADER_COLLAPSE_OFFSET;
    };

    const mobileHeaderResizeObserver = new ResizeObserver(
      measureMobileHeaderCollapseOffset,
    );
    if (mobileHeaderUpperRef.current) {
      mobileHeaderResizeObserver.observe(mobileHeaderUpperRef.current);
    }

    const setMobileThemeSurfaceState = (nextThemed: boolean) => {
      if (isMobileThemeSurfaceRef.current === nextThemed) return;
      isMobileThemeSurfaceRef.current = nextThemed;
      setIsMobileThemeSurface(nextThemed);
    };

    const recordScrollIntent = (deltaY: number) => {
      if (Math.abs(deltaY) < 1) return;
      mobileScrollIntentAt.current = performance.now();
      mobileScrollIntentDirection.current = deltaY > 0 ? "down" : "up";
    };

    const handleWheel = (event: WheelEvent) => {
      recordScrollIntent(event.deltaY);
    };

    const handleTouchStart = (event: TouchEvent) => {
      lastTouchY = event.touches[0]?.clientY ?? null;
    };

    const handleTouchMove = (event: TouchEvent) => {
      const currentTouchY = event.touches[0]?.clientY;
      if (currentTouchY === undefined || lastTouchY === null) return;
      recordScrollIntent(lastTouchY - currentTouchY);
      lastTouchY = currentTouchY;
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (
        ["ArrowDown", "ArrowUp", "PageDown", "PageUp", "Home", "End"].includes(
          event.key,
        )
      ) {
        recordScrollIntent(
          ["ArrowUp", "PageUp", "Home"].includes(event.key) ? -1 : 1,
        );
      }
    };

    const setMobileHeaderExpanded = (nextExpanded: boolean) => {
      if (isMobileHeaderExpandedRef.current === nextExpanded) return;
      isMobileHeaderExpandedRef.current = nextExpanded;
      const transitionDuration = reducedMotionMedia.matches
        ? 0
        : MOBILE_HEADER_TRANSITION_MS;
      mobileTransitionLockUntil.current =
        performance.now() + transitionDuration;
      setIsMobileHeaderExpanded(nextExpanded);

      if (transitionSettleTimer !== null) {
        window.clearTimeout(transitionSettleTimer);
      }
      transitionSettleTimer = window.setTimeout(() => {
        transitionSettleTimer = null;
        measureMobileHeaderCollapseOffset();
        requestScrollUpdate();
      }, transitionDuration + 16);
    };

    const updateMobileHeader = () => {
      scrollFrame = null;
      const currentScrollY = Math.max(0, window.scrollY);
      const now = performance.now();

      if (desktopMedia.matches) {
        setMobileThemeSurfaceState(false);
        setMobileHeaderExpanded(true);
        mobileScrollAnchorY.current = currentScrollY;
        return;
      }

      // Geometry reads during the height transition would force the browser to
      // synchronously lay out every intermediate frame. The surface threshold
      // can safely wait until the coordinated 300 ms transition has finished.
      if (router.pathname === "/" && now >= mobileTransitionLockUntil.current) {
        if (!heroSection?.isConnected) {
          heroSection = document.querySelector<HTMLElement>(
            'section[data-testid^="home-hero-"]',
          );
        }
        const heroBounds = heroSection?.getBoundingClientRect();
        const stickyHeaderBottom =
          stickyHeader?.getBoundingClientRect().bottom ?? 0;
        const surfaceThreshold = heroBounds
          ? currentScrollY + heroBounds.bottom - stickyHeaderBottom
          : Math.max(MOBILE_HEADER_SCROLL_THRESHOLD, window.innerHeight * 0.25);

        setMobileThemeSurfaceState(
          isMobileThemeSurfaceRef.current
            ? currentScrollY > Math.max(0, surfaceThreshold - 24)
            : currentScrollY > surfaceThreshold,
        );
      } else if (router.pathname !== "/") {
        setMobileThemeSurfaceState(false);
      }

      if (currentScrollY <= MOBILE_HEADER_SCROLL_THRESHOLD) {
        setMobileHeaderExpanded(true);
        mobileScrollAnchorY.current = currentScrollY;
        return;
      }

      if (
        isMobileHeaderExpandedRef.current &&
        currentScrollY <=
          MOBILE_HEADER_SCROLL_THRESHOLD + mobileHeaderCollapseOffset
      ) {
        mobileScrollAnchorY.current = currentScrollY;
        return;
      }

      if (now < mobileTransitionLockUntil.current) {
        mobileScrollAnchorY.current = currentScrollY;
        return;
      }

      if (now - mobileScrollIntentAt.current > MOBILE_SCROLL_INTENT_WINDOW_MS) {
        mobileScrollAnchorY.current = currentScrollY;
        return;
      }

      if (isMobileHeaderExpandedRef.current) {
        if (mobileScrollIntentDirection.current !== "down") {
          mobileScrollAnchorY.current = currentScrollY;
          return;
        }
        if (currentScrollY < mobileScrollAnchorY.current) {
          mobileScrollAnchorY.current = currentScrollY;
        } else if (
          currentScrollY - mobileScrollAnchorY.current >=
          MOBILE_HEADER_HIDE_DISTANCE
        ) {
          setMobileHeaderExpanded(false);
          mobileScrollAnchorY.current = currentScrollY;
        }
      } else if (mobileScrollIntentDirection.current !== "up") {
        mobileScrollAnchorY.current = currentScrollY;
      } else if (currentScrollY > mobileScrollAnchorY.current) {
        mobileScrollAnchorY.current = currentScrollY;
      } else if (
        mobileScrollAnchorY.current - currentScrollY >=
        MOBILE_HEADER_REVEAL_DISTANCE
      ) {
        setMobileHeaderExpanded(true);
        mobileScrollAnchorY.current = currentScrollY;
      }
    };

    const requestScrollUpdate = () => {
      if (desktopMedia.matches) return;
      if (scrollFrame !== null) return;
      scrollFrame = window.requestAnimationFrame(updateMobileHeader);
    };

    const handleViewportChange = () => {
      mobileScrollAnchorY.current = Math.max(0, window.scrollY);
      setIsMobileViewport(!desktopMedia.matches);
      if (desktopMedia.matches) {
        setMobileThemeSurfaceState(false);
        setMobileHeaderExpanded(true);
        return;
      }

      measureMobileHeaderCollapseOffset();
      requestScrollUpdate();
    };

    handleViewportChange();
    desktopMedia.addEventListener("change", handleViewportChange);
    window.addEventListener("wheel", handleWheel, { passive: true });
    window.addEventListener("touchstart", handleTouchStart, { passive: true });
    window.addEventListener("touchmove", handleTouchMove, { passive: true });
    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("scroll", requestScrollUpdate, { passive: true });

    return () => {
      mobileHeaderResizeObserver.disconnect();
      desktopMedia.removeEventListener("change", handleViewportChange);
      window.removeEventListener("wheel", handleWheel);
      window.removeEventListener("touchstart", handleTouchStart);
      window.removeEventListener("touchmove", handleTouchMove);
      window.removeEventListener("keydown", handleKeyDown);
      window.removeEventListener("scroll", requestScrollUpdate);
      if (scrollFrame !== null) window.cancelAnimationFrame(scrollFrame);
      if (transitionSettleTimer !== null) {
        window.clearTimeout(transitionSettleTimer);
      }
    };
  }, [router.pathname, setIsMobileHeaderExpanded, setIsMobileViewport]);

  // Menu items with translation keys
  const navMenuItems = [
    { label: t("nav.home"), href: "/", icon: Home },
    { label: t("nav.brands"), href: "/brands", icon: Tags },
    { label: t("nav.blog"), href: "/blogs/", icon: BookOpen },
    { label: t("nav.faqs"), href: "/faqs", icon: HelpCircle },
    { label: t("nav.about_us"), href: "/about-us", icon: Info },
  ];
  const navbarControlClass = isHomePage
    ? isMobileThemeSurface
      ? "!text-foreground"
      : "!text-[#111827] dark:!text-[#111827]"
    : "!text-foreground";
  const mobileMenuControlClass = isHomePage
    ? isMobileThemeSurface
      ? "!text-foreground"
      : "!text-white dark:!text-white"
    : "!text-foreground";
  const loginTriggerClass = isHomePage
    ? isMobileThemeSurface
      ? "!bg-content1/80 data-[hover=true]:!bg-content2 !text-foreground"
      : "!bg-white/50 data-[hover=true]:!bg-white/60 dark:!bg-white/50 dark:data-[hover=true]:!bg-white/60 !text-[var(--home-header-foreground,var(--home-category-foreground,#111827))]"
    : "!bg-content1/80 data-[hover=true]:!bg-content2 !text-foreground";
  const cartControlClass = isHomePage
    ? isMobileThemeSurface
      ? "!text-foreground"
      : "!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))]"
    : "!text-foreground";
  const isMobileHeaderUpperVisible = isMobileHeaderExpanded || isMenuOpen;

  const renderCartControl = (title?: string) => (
    <Badge
      color="primary"
      content={activeCartCount || undefined}
      isInvisible={activeCartCount <= 0}
      variant="solid"
      classNames={{ badge: "text-xs" }}
    >
      <Link
        title={title}
        aria-label={title ?? t("cart_title")}
        className="inline-flex min-h-10 min-w-10 items-center justify-center"
        href="#"
        onClick={(event) => {
          event.preventDefault();
          if (isLoggedIn) {
            router.push("/cart");
          } else {
            openOfflineCart();
          }
        }}
      >
        <ShoppingCart size={21} className={`${cartControlClass} cursor-pointer`} />
      </Link>
    </Badge>
  );
  return (
    <>
      <div
        data-home-sticky-header={isHomePage ? "" : undefined}
        className={
          isHomePage
            ? `sticky top-0 z-[40] isolate w-full pt-[env(safe-area-inset-top,0px)] will-change-[box-shadow] ${
                isMobileThemeSurface
                  ? "shadow-[0_5px_20px_rgba(15,23,42,0.08)]"
                  : hasHomeHeaderMedia
                    ? "shadow-none"
                    : "shadow-[0_5px_20px_rgba(15,23,42,0.08)]"
              }`
            : "contents"
        }
        style={headerStyle}
      >
        {isHomePage && (
          <>
            <HomeHeaderBackground appearance={homeHeaderAppearance} />
            <div
              data-home-theme-surface
              aria-hidden="true"
              className={`pointer-events-none absolute inset-0 z-[1] bg-background transition-opacity duration-300 ease-out motion-reduce:transition-none md:hidden ${
                isMobileThemeSurface ? "opacity-100" : "opacity-0"
              }`}
            />
          </>
        )}

        <div
          ref={navbarRootRef}
          data-navbar-root
          className={`w-full flex flex-col items-start z-50 ${
            isHomePage
              ? "relative bg-transparent shadow-none"
              : "sticky top-0 border-b border-default-200/80 bg-background/90 pt-[env(safe-area-inset-top,0px)] backdrop-blur-2xl dark:border-white/10 dark:bg-[#111111]/90"
          }`}
        >
          <div
            ref={mobileHeaderUpperRef}
            data-mobile-header-upper
            aria-hidden={!isMobileHeaderUpperVisible}
            inert={!isMobileHeaderUpperVisible ? true : undefined}
            className={`grid w-full overflow-hidden transition-[grid-template-rows,opacity,transform] duration-300 ease-out motion-reduce:transition-none ${
              isMobileHeaderUpperVisible
                ? "grid-rows-[1fr] translate-y-0 opacity-100"
                : "pointer-events-none grid-rows-[0fr] -translate-y-3 opacity-0"
            } md:pointer-events-auto md:grid-rows-[1fr] md:translate-y-0 md:overflow-visible md:opacity-100`}
          >
            <div className="min-h-0 overflow-hidden md:overflow-visible">
              {demoMode && showDemoWarning && (
                <div className="w-full bg-primary-50 dark:bg-content1 text-warning-700 text-xs sm:text-sm px-3 py-1 flex items-center justify-center gap-2 relative">
                  ℹ️
                  <span className="font-medium flex items-center gap-2">
                    {systemSettings?.customerDemoModeMessage
                      ? systemSettings.customerDemoModeMessage
                      : "Currently running in Demo Mode"}
                  </span>
                  <Button
                    onPress={() => setShowDemoWarning(false)}
                    aria-label="Close demo mode warning"
                    isIconOnly
                    size="sm"
                    radius="full"
                    color="primary"
                    variant="flat"
                    className="min-w-1 w-6 h-6"
                  >
                    <X size={16} className="text-warning-700 rounded-full" />
                  </Button>
                </div>
              )}

              <HeroUINavbar
                maxWidth="2xl"
                position="static"
                disableScrollHandler
                className="p-0"
                classNames={{
                  wrapper:
                    "p-0 px-2 md:px-4 translate-z-0 [--navbar-height:3.5rem] !min-h-14 md:[--navbar-height:4.5rem] md:!min-h-18",
                  base: "!bg-transparent !backdrop-blur-none !backdrop-saturate-100 !shadow-none",
                }}
                isMenuOpen={isMenuOpen}
                onMenuOpenChange={setIsMenuOpen}
              >
                <NavbarContent className="w-full md:basis-1/4" justify="start">
                  <NavbarMenuToggle
                    className="shrink-0 md:hidden"
                    aria-label={
                      isMenuOpen ? t("aria.close_menu") : t("aria.open_menu")
                    }
                  />
                  <div className="flex min-w-0 flex-1 items-center justify-between md:min-w-32">
                    <NavbarBrand className="min-w-0 flex-1 gap-3 md:w-full md:min-w-32">
                      <Link
                        as={NextLink}
                        href="/?category=all"
                        title={t("nav.home")}
                      >
                        <Image
                          loading="eager"
                          src={siteHeaderLogo}
                          alt={siteName}
                          radius="none"
                          className="object-contain dark:hidden"
                          classNames={{
                            img: "w-full sm:min-w-5 md:min-w-32 h-8 sm:h-10 md:h-12",
                            wrapper: "cursor-pointer",
                          }}
                        />
                        <Image
                          loading="eager"
                          src={siteHeaderDarkLogo}
                          alt={siteName}
                          radius="none"
                          className="object-contain hidden dark:block"
                          classNames={{
                            img: "w-full sm:min-w-5 md:min-w-32 h-8 sm:h-10 md:h-12",
                            wrapper: "cursor-pointer",
                          }}
                        />
                      </Link>
                    </NavbarBrand>
                    <NavbarItem className="flex shrink-0 items-center gap-2 md:hidden">
                      {renderCartControl(t("cart_title"))}
                      {isLoggedIn ? (
                        <ProfileBtn />
                      ) : (
                        <LoginModal triggerView="icon" />
                      )}
                    </NavbarItem>
                  </div>
                  <div className="hidden md:flex w-full flex-start">
                    <NavbarItem className="max-w-[200px]">
                      <LocationSelector triggerClassName={navbarControlClass} />
                    </NavbarItem>
                  </div>
                </NavbarContent>

                <NavbarContent
                  className="hidden md:flex md:basis-1/2"
                  justify="center"
                >
                  <div className="w-full max-w-xl">
                    <GlobalSearchbar isHomeSurface={isHomePage} />
                  </div>
                </NavbarContent>

                <NavbarContent
                  className="hidden md:flex !gap-2 lg:!gap-4 xl:!gap-6"
                  justify="end"
                >
                  <NavbarItem className="shrink-0">
                    <Link
                      as={NextLink}
                      href="/blogs/"
                      title={t("nav.blog")}
                      aria-label={t("nav.blog")}
                      aria-current={isBlogPage ? "page" : undefined}
                      className={`inline-flex min-h-10 min-w-10 items-center justify-center gap-2 rounded-full px-2 text-sm font-semibold transition-colors focus-visible:outline-primary lg:px-3 ${cartControlClass} ${
                        isBlogPage
                          ? "bg-primary/10"
                          : isHomePage
                            ? "hover:bg-white/20"
                            : "hover:bg-default-100"
                      }`}
                    >
                      <BookOpen size={20} aria-hidden="true" />
                      <span className="hidden min-[1024px]:inline">{t("nav.blog")}</span>
                    </Link>
                  </NavbarItem>
                  <NavbarItem className="flex items-end gap-2">
                    <LanguageSwitcher triggerClassName={navbarControlClass} />
                  </NavbarItem>
                  {watchAndBuyTitle && (
                    <NavbarItem className="shrink-0">
                      <Link
                        as={NextLink}
                        href="/play/"
                        aria-label={watchAndBuyTitle}
                        className={`flex items-center justify-center rounded-md focus-visible:outline-primary ${navbarControlClass}`}
                      >
                        <svg
                          width={20}
                          height={20}
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          strokeWidth={1.5}
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          aria-hidden="true"
                        >
                          <rect x="3" y="3" width="18" height="18" rx="5" />
                          <path d="M3 9h18M9 3 6 9m10-6-3 6m7-4-2 4" />
                          <path d="m10 12 5 3-5 3z" />
                        </svg>
                      </Link>
                    </NavbarItem>
                  )}
                  <NavbarItem className="flex items-end gap-2">
                    <ThemeSwitch />
                  </NavbarItem>
                  <NavbarItem>
                    <div className="flex items-center">
                      {renderCartControl(t("cart_title"))}
                    </div>
                  </NavbarItem>
                  <NavbarItem>
                    {isLoggedIn ? (
                      <ProfileBtn />
                    ) : (
                      <LoginModal triggerClassName={loginTriggerClass} />
                    )}
                  </NavbarItem>
                </NavbarContent>

                <NavbarMenu className="z-50">
                  <NavbarMenuItem className="flex justify-between items-center gap-6 pb-4 border-b border-divider">
                    <LanguageSwitcher
                      triggerClassName={mobileMenuControlClass}
                    />
                    <ThemeSwitch variant="switch" />
                  </NavbarMenuItem>
                  <NavbarMenuItem className="border-b border-divider py-2">
                    <button
                      type="button"
                      className="flex w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-left text-lg hover:bg-default-100"
                      onClick={() => {
                        setIsMenuOpen(false);
                        if (isLoggedIn) {
                          router.push("/cart");
                        } else {
                          openOfflineCart();
                        }
                      }}
                    >
                      <ShoppingCart size={20} className="text-default-500" />
                      <span className="flex-1">{t("cart_title")}</span>
                      {activeCartCount > 0 && (
                        <span className="min-w-6 rounded-full bg-primary px-2 py-0.5 text-center text-xs text-primary-foreground">
                          {activeCartCount}
                        </span>
                      )}
                    </button>
                  </NavbarMenuItem>
                  <div className="flex flex-col gap-1 mt-2">
                    {navMenuItems.map((item, index) => {
                      const Icon = item.icon;
                      const isActive =
                        item.href === "/blogs/"
                          ? isBlogPage
                          : router.pathname === item.href;
                      return (
                        <NavbarMenuItem key={`${item.label}-${index}`}>
                          <Link
                            as={NextLink}
                            color="foreground"
                            href={item.href}
                            aria-current={isActive ? "page" : undefined}
                            size="lg"
                            className={`w-full flex items-center gap-3 py-2 px-2 rounded-lg transition-colors ${isActive ? "bg-primary/10 text-primary" : "hover:bg-default-100"}`}
                            onClick={() => setIsMenuOpen(false)}
                          >
                            <Icon
                              size={20}
                              aria-hidden="true"
                              className={
                                isActive ? "text-primary" : "text-default-500"
                              }
                            />
                            <span>{item.label}</span>
                          </Link>
                        </NavbarMenuItem>
                      );
                    })}
                  </div>
                </NavbarMenu>
              </HeroUINavbar>

              <div className="w-full px-3 pb-1 md:hidden">
                <LocationSelector triggerClassName={navbarControlClass} />
              </div>
            </div>
          </div>

          <div
            data-mobile-search-row
            className={`relative z-10 w-full px-3 pb-3 transition-[padding] duration-300 ease-out motion-reduce:transition-none md:hidden ${
              isMobileHeaderUpperVisible ? "pt-0" : "pt-2.5"
            }`}
          >
            <GlobalSearchbar
              isHomeSurface={isHomePage && !isMobileThemeSurface}
            />
          </div>
        </div>

        {isHomePage && (
          <div className="relative z-10 w-full">
            <CategoryTabs
              className="w-2xl"
              mergedSurface
              hideImages={
                isMobileViewport ? !isMobileHeaderUpperVisible : undefined
              }
              onBackgroundImageChange={handleCategoryBackgroundImageChange}
            />
          </div>
        )}
      </div>
      <OfflineCartDrawer
        isOpen={isOfflineCartOpen}
        onClose={closeOfflineCart}
      />
    </>
  );
};
