import React, {
  CSSProperties,
  FC,
  useCallback,
  useEffect,
  useMemo,
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
import { ShoppingCart, Home, Tags, HelpCircle, Info, X } from "lucide-react";
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

const FallbackCartIcon = () => (
  <Link as={NextLink} href="/cart">
    <ShoppingCart className="text-default-500 cursor-pointer" />
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

export const Navbar: FC = () => {
  const { t } = useTranslation();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [showDemoWarning, setShowDemoWarning] = useState(true);
  const [isScrolled, setIsScrolled] = useState(false);
  const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
  const { webSettings, demoMode, systemSettings, homeGeneralSettings } =
    useSettings();
  const router = useRouter();
  const cartCount =
    useSelector((state: RootState) => state.cart.cartData?.items_count) || 0;

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
  const homeHeaderAppearance = useMemo(
    () => resolveHomeHeaderAppearance(homeGeneralSettings),
    [homeGeneralSettings],
  );
  const hasHomeHeaderMedia = homeHeaderAppearance.type !== "color";
  const homeHeaderStyle =
    isHomePage && homeHeaderAppearance.fontColor
      ? ({
          "--home-header-foreground": homeHeaderAppearance.fontColor,
          "--home-category-foreground": homeHeaderAppearance.fontColor,
          color: homeHeaderAppearance.fontColor,
        } as CSSProperties)
      : undefined;
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

  // Track scroll position for premium shrink effect
  const updateScrollState = useCallback(() => {
    setIsScrolled(window.scrollY > 10);
  }, []);

  useEffect(() => {
    window.addEventListener("scroll", updateScrollState, { passive: true });
    return () => window.removeEventListener("scroll", updateScrollState);
  }, [updateScrollState]);

  // Menu items with translation keys
  const navMenuItems = [
    { label: t("nav.home"), href: "/", icon: Home },
    { label: t("nav.brands"), href: "/brands", icon: Tags },
    { label: t("nav.faqs"), href: "/faqs", icon: HelpCircle },
    { label: t("nav.about_us"), href: "/about-us", icon: Info },
  ];
  const renderCartControl = (title?: string) => (
    <Badge
      color="primary"
      content={activeCartCount || undefined}
      variant="solid"
      classNames={{ badge: "text-xs" }}
    >
      <Link
        title={title}
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
        <ShoppingCart className="text-default-500 cursor-pointer" />
      </Link>
    </Badge>
  );

  return (
    <>
      <div
        className={
          isHomePage
            ? `sticky top-0 z-[40] isolate w-full transition-[border-color,box-shadow] duration-300 ease-out ${
                hasHomeHeaderMedia
                  ? "shadow-none"
                  : isScrolled
                    ? "shadow-[0_12px_32px_rgba(15,23,42,0.14)]"
                    : "shadow-[0_5px_20px_rgba(15,23,42,0.08)]"
              }`
            : "contents"
        }
        style={homeHeaderStyle}
      >
        {isHomePage && (
          <HomeHeaderBackground appearance={homeHeaderAppearance} />
        )}
        {/* ── Header: logo / search / actions ──────────────────────────────── */}
        <div
          data-navbar-root
          className={`w-full flex flex-col items-start z-50 transition-[background-color,border-color,box-shadow] duration-300 ease-out ${
            isHomePage
              ? "relative bg-transparent shadow-none"
              : "sticky top-0 border-b border-default-200/80 bg-background/90 backdrop-blur-2xl  dark:border-white/10 dark:bg-[#111111]/90"
          }`}
        >
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
            maxWidth="full"
            position="static"
            className="p-0"
            classNames={{
              wrapper: `p-0 px-4 md:px-8 transition-all duration-300 ${isScrolled ? "[--navbar-height:3rem] py-0 !min-h-12" : "[--navbar-height:3.5rem] !min-h-14"}`,
              base: "!bg-transparent !backdrop-blur-none !shadow-none",
            }}
            isMenuOpen={isMenuOpen}
            onMenuOpenChange={setIsMenuOpen}
          >
            {/* Logo and Location */}
            <NavbarContent className="md:basis-1/4 w-full" justify="start">
              <NavbarMenuToggle
                className="md:hidden"
                aria-label={
                  isMenuOpen ? t("aria.close_menu") : t("aria.open_menu")
                }
              />
              <div className="flex justify-between items-center w-full md:w-auto">
                <div className="flex basis-0 flex-row flex-grow flex-nowrap justify-start bg-transparent items-center no-underline text-medium whitespace-nowrap box-border gap-3 w-full min-w-32">
                  <NavbarBrand className="gap-3 w-full min-w-32">
                    {/* `as={NextLink}`: HeroUI's Link renders a bare <a>, which
                    triggers a full page load. It doesn't depend on
                    @react-aria/link, so it never reads the RouterProvider
                    context that _app.tsx wires up. */}
                    {/* `?category=all` rather than a bare "/": it's an explicit
                    reset, so the persisted homeCategory cookie must not
                    resurrect the previously selected category. */}
                    <Link
                      as={NextLink}
                      href="/?category=all"
                      title={t("nav.home")}
                    >
                      {/* Light theme logo */}
                      <Image
                        loading="eager"
                        src={siteHeaderLogo}
                        alt={siteName}
                        radius="none"
                        className="object-contain dark:hidden"
                        classNames={{
                          img: "h-8 sm:h-10 md:h-12 w-full sm:min-w-5 md:min-w-32",
                          wrapper: "cursor-pointer",
                        }}
                      />
                      {/* Dark theme logo */}
                      <Image
                        loading="eager"
                        src={siteHeaderDarkLogo}
                        alt={siteName}
                        radius="none"
                        className="object-contain hidden dark:block"
                        classNames={{
                          img: "h-8 sm:h-10 md:h-12 w-full sm:min-w-5 md:min-w-32",
                          wrapper: "cursor-pointer",
                        }}
                      />
                    </Link>
                  </NavbarBrand>

                  <div className="hidden md:flex w-full flex-start">
                    <NavbarItem className="max-w-[200px]">
                      <LocationSelector />
                    </NavbarItem>
                  </div>
                </div>

                <div className="flex items-center gap-2 md:hidden">
                  <div className="bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-500 font-bold text-[10px] px-1.5 py-0.5 rounded flex items-center gap-1 border border-warning-200 dark:border-warning-700/50 sm:hidden">
                    <span>⚡</span>
                    <span>10 Mins</span>
                  </div>
                  <NavbarItem className="min-w-0 max-w-[40px]">
                    <LocationSelector />
                  </NavbarItem>
                  <NavbarItem>
                    <div className="flex items-center">
                      {renderCartControl()}
                    </div>
                  </NavbarItem>
                  <NavbarItem>
                    {isLoggedIn ? (
                      <ProfileBtn />
                    ) : (
                      <LoginModal triggerView="icon" />
                    )}
                  </NavbarItem>
                </div>
              </div>
            </NavbarContent>

            {/* Search Bar - Desktop */}
            <NavbarContent
              className="hidden md:flex md:basis-1/2 lg:basis-[55%]"
              justify="center"
            >
              <div className="w-full max-w-2xl px-4">
                <GlobalSearchbar />
              </div>
            </NavbarContent>

            {/* Right Side Actions - Desktop */}
            <NavbarContent className="hidden md:flex gap-2" justify="end">
              {/* <NavbarItem className="max-w-[200px]">
                <LocationSelector />
              </NavbarItem> */}
              <NavbarItem>
                <LanguageSwitcher />
              </NavbarItem>
              <NavbarItem>
                <ThemeSwitch />
              </NavbarItem>
              <NavbarItem>
                <div className="flex items-center">
                  {renderCartControl(t("cart_title"))}
                </div>
              </NavbarItem>
              <NavbarItem>
                {isLoggedIn ? <ProfileBtn /> : <LoginModal />}
              </NavbarItem>
            </NavbarContent>

            {/* Mobile Menu */}
            <NavbarMenu>
              <NavbarMenuItem className="flex justify-between items-center gap-4 pb-4 border-b border-divider">
                <LanguageSwitcher />
                <ThemeSwitch variant="switch" />
              </NavbarMenuItem>
              <div className="flex flex-col gap-1 mt-2">
                {navMenuItems.map((item, index) => {
                  const Icon = item.icon;
                  return (
                    <NavbarMenuItem key={`${item.label}-${index}`}>
                      <Link
                        as={NextLink}
                        color="foreground"
                        href={item.href}
                        size="lg"
                        className="w-full flex items-center gap-3 py-2 px-2 rounded-lg hover:bg-default-100 transition-colors"
                        onClick={() => setIsMenuOpen(false)}
                      >
                        <Icon size={20} className="text-default-500" />
                        <span>{item.label}</span>
                      </Link>
                    </NavbarMenuItem>
                  );
                })}
              </div>
            </NavbarMenu>
          </HeroUINavbar>

          {/* Mobile Search - Full Width */}
          <div className="w-full md:hidden px-2 pb-3">
            <GlobalSearchbar />
          </div>
        </div>

        {/* ── Home category strip: sticks with the home header stack ───────── */}
        {isHomePage && <CategoryTabs className="w-full" mergedSurface />}
      </div>

      <OfflineCartDrawer
        isOpen={isOfflineCartOpen}
        onClose={closeOfflineCart}
      />
    </>
  );
};
