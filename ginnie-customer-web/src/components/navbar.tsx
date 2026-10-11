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
    <ShoppingCart className="!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))] cursor-pointer" />
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
  const [categoryHeaderBackgroundImage, setCategoryHeaderBackgroundImage] =
    useState<string | null>(null);
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
    () =>
      resolveHomeHeaderAppearance(
        homeGeneralSettings,
        categoryHeaderBackgroundImage,
      ),
    [categoryHeaderBackgroundImage, homeGeneralSettings],
  );
  const handleCategoryBackgroundImageChange = useCallback(
    (backgroundImage: string | null) => {
      setCategoryHeaderBackgroundImage(backgroundImage);
    },
    [],
  );
  const hasHomeHeaderMedia = homeHeaderAppearance.type !== "color";
  const headerStyle = isHomePage
    ? homeHeaderAppearance.fontColor
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

  // Menu items with translation keys
  const navMenuItems = [
    { label: t("nav.home"), href: "/", icon: Home },
    { label: t("nav.brands"), href: "/brands", icon: Tags },
    { label: t("nav.faqs"), href: "/faqs", icon: HelpCircle },
    { label: t("nav.about_us"), href: "/about-us", icon: Info },
  ];
  const navbarControlClass = isHomePage
    ? "!text-[#111827] dark:!text-[#111827]"
    : "!text-foreground";
  const mobileMenuControlClass = isHomePage
    ? "!text-white dark:!text-white"
    : "!text-foreground";
  const loginTriggerClass = isHomePage
    ? "!bg-white/50 data-[hover=true]:!bg-white/60 dark:!bg-white/50 dark:data-[hover=true]:!bg-white/60 !text-[var(--home-header-foreground,var(--home-category-foreground,#111827))]"
    : "!bg-content1/80 data-[hover=true]:!bg-content2 !text-foreground";
  const cartControlClass = isHomePage
    ? "!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))]"
    : "!text-foreground";

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
        <ShoppingCart className={`${cartControlClass} cursor-pointer`} />
      </Link>
    </Badge>
  );
  return (
    <>
      <div
        className={
          isHomePage
            ? `sticky top-0 z-[40] isolate w-full will-change-[box-shadow] ${
                hasHomeHeaderMedia
                  ? "shadow-none"
                  : "shadow-[0_5px_20px_rgba(15,23,42,0.08)]"
              }`
            : "contents"
        }
        style={headerStyle}
      >
        {isHomePage && (
          <HomeHeaderBackground appearance={homeHeaderAppearance} />
        )}

        <div
          data-navbar-root
          className={`w-full flex flex-col items-start z-50 ${
            isHomePage
              ? "relative bg-transparent shadow-none"
              : "sticky top-0 border-b border-default-200/80 bg-background/90 backdrop-blur-2xl dark:border-white/10 dark:bg-[#111111]/90"
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
            maxWidth="2xl"
            position="static"
            className="p-0"
            classNames={{
              wrapper:
                "p-0 px-2 md:px-4 translate-z-0 [--navbar-height:3.5rem] !min-h-14",
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
                <NavbarItem className="shrink-0 md:hidden">
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

            <NavbarContent className="hidden md:flex !gap-6" justify="end">
              <NavbarItem className="flex items-end gap-2">
                <LanguageSwitcher triggerClassName={navbarControlClass} />
              </NavbarItem>
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
                <LanguageSwitcher triggerClassName={mobileMenuControlClass} />
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

          <div className="w-full px-3 pb-1 md:hidden">
            <LocationSelector triggerClassName={navbarControlClass} />
          </div>

          <div className="w-full px-3 pb-3 md:hidden">
            <GlobalSearchbar isHomeSurface={isHomePage} />
          </div>
        </div>

        {isHomePage && (
          <CategoryTabs
            className="w-2xl"
            mergedSurface
            onBackgroundImageChange={handleCategoryBackgroundImageChange}
          />
        )}
      </div>
      <OfflineCartDrawer
        isOpen={isOfflineCartOpen}
        onClose={closeOfflineCart}
      />
    </>
  );
};
