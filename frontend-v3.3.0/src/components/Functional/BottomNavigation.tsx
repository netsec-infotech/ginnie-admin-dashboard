import { useState, useEffect, useMemo } from "react";
import { Home, Clapperboard, User, Package, Store } from "lucide-react";
import { useRouter } from "next/router";
import { useSelector } from "react-redux";
import { RootState } from "@/lib/redux/store";
import { addToast } from "@heroui/react";
import { useTranslation } from "react-i18next";
import { useSettings } from "@/contexts/SettingsContext";

const BottomNavigation = () => {
  const [isVisible, setIsVisible] = useState(true);
  const [lastScrollY, setLastScrollY] = useState(0);
  const router = useRouter();
  const { t } = useTranslation();
  const { isSingleVendor } = useSettings();
  const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);

  const activeTab = useMemo(() => {
    const pathToTabMap: Record<string, string> = {
      "/": "home",
      "/play": "watch",
      "/categories": "categories",
      "/my-account": "profile",
    };
    return pathToTabMap[router.pathname] || "";
  }, [router.pathname]);

  useEffect(() => {
    const controlNavbar = () => {
      if (typeof window !== "undefined") {
        const currentScrollY = window.scrollY;

        if (currentScrollY < lastScrollY || currentScrollY < 10) {
          setIsVisible(true);
        } else {
          setIsVisible(false);
        }

        setLastScrollY(currentScrollY);
      }
    };

    if (typeof window !== "undefined") {
      window.addEventListener("scroll", controlNavbar);
      return () => window.removeEventListener("scroll", controlNavbar);
    }
  }, [lastScrollY]);

  const navItems = [
    { id: "home", label: t("home_title"), icon: Home, path: "/" },
    {
      id: "categories",
      label: t("categories"),
      icon: Package,
      path: "/categories",
    },
    {
      id: "watch",
      label: t("nav.watch", { defaultValue: "Watch" }),
      icon: Clapperboard,
      path: "/play/",
    },
    {
      id: "stores",
      label: t("pageTitle.stores"),
      icon: Store,
      path: "/stores",
      protected: false,
    },
    {
      id: "profile",
      label: t("profile"),
      icon: User,
      path: "/my-account",
      protected: true,
    },
  ].filter((item) => !(isSingleVendor && item.id === "stores"));

  const handleTabClick = (
    path?: string,
    protectedTab?: boolean,
  ) => {
    if (protectedTab && !isLoggedIn) {
      document.getElementById("login-btn")?.click();
      addToast({ title: "Please Login to Continue !", color: "warning" });
      return;
    }
    if (path) router.push(path);
  };

  return (
    <div
      data-bottom-navigation
      className={`fixed inset-x-0 bottom-0 z-50 border-t border-default-200/70 bg-background/95 shadow-[0_-4px_18px_rgba(15,23,42,0.08)] backdrop-blur-xl transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] md:hidden ${
        isVisible ? "translate-y-0" : "translate-y-full"
      }`}
    >
      <div>
        <div className="max-w-md mx-auto">
          <nav
            aria-label={t("nav.mobileNavigation")}
            className="flex items-center justify-around gap-2 px-1 pt-2 pb-[calc(env(safe-area-inset-bottom,0px)+0.75rem)]"
          >
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = activeTab === item.id;
              return (
                <button
                  type="button"
                  key={item.id}
                  aria-current={isActive ? "page" : undefined}
                  onClick={() =>
                    handleTabClick(item.path, item.protected)
                  }
                  className={`relative flex min-w-0 flex-1 flex-col items-center justify-center px-2 py-1.5 transition-[color,opacity,transform] duration-300 ease-out active:scale-[0.96] focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ${
                    isActive
                      ? "text-foreground opacity-100"
                      : "text-default-400 opacity-80 hover:text-default-600 hover:opacity-100"
                  }`}
                >
                  <Icon
                    aria-hidden="true"
                    size={21}
                    strokeWidth={isActive ? 2.1 : 1.7}
                    className="mb-1 transition-colors duration-300 ease-out"
                  >
                    {item.id === "watch" && <path d="m10 13 5 3-5 3z" />}
                  </Icon>
                  <span
                    className={`text-xs transition-colors duration-300 ease-out ${
                      isActive ? "font-semibold" : "font-medium"
                    }`}
                  >
                    {item.label}
                  </span>
                </button>
              );
            })}
          </nav>
        </div>
      </div>
    </div>
  );
};

export default BottomNavigation;
