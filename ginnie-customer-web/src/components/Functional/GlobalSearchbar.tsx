import React, { useState, useCallback, useMemo, useEffect } from "react";
import { Button, Input } from "@heroui/react";
import { ClipboardPenLine, Search } from "lucide-react";
import { useDisclosure } from "@heroui/react";
import useSWR from "swr";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { Product, PaginatedResponse } from "@/types/ApiResponse";
import { getProducts } from "@/routes/api";
import SearchModal from "../Modals/SearchModal";
import { UserLocation } from "../Location/types/LocationAutoComplete.types";
import { getCookie } from "@/lib/cookies";
import { useRouter } from "next/router";
import { useRecentSearches } from "@/hooks/useRecentSearches";
import { useTranslation } from "react-i18next";
import { useSettings } from "@/contexts/SettingsContext";

const DEBOUNCE_DELAY = 300;

const searchFetcher = async (
  key: string,
): Promise<PaginatedResponse<Product[]> | null> => {
  const [, query] = key.split(":");
  if (!query || query.trim().length < 2) return null;

  const { lat = "", lng = "" } =
    (getCookie("userLocation") as UserLocation) || {};

  const response = await getProducts({
    search: query,
    page: 1,
    per_page: 8,
    latitude: lat,
    longitude: lng,
    include_child_categories: 0,
  });

  return response;
};

interface GlobalSearchBarProps {
  /** Home search intentionally keeps its hero/category-aware treatment. */
  isHomeSurface?: boolean;
}

const GlobalSearchBar: React.FC<GlobalSearchBarProps> = ({
  isHomeSurface = false,
}) => {
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [isTyping, setIsTyping] = useState<boolean>(false);
  const { t } = useTranslation();
  const { homeGeneralSettings } = useSettings();
  const router = useRouter();

  const placeholders = useMemo<string[]>(() => {
    const SearchPlaceHolders = homeGeneralSettings?.searchLabels || [];
    if (Array.isArray(SearchPlaceHolders) && SearchPlaceHolders.length > 0) {
      return SearchPlaceHolders;
    }
    return [t("search_placeholder")];
  }, [homeGeneralSettings?.searchLabels, t]);

  // === Animated placeholder state ===
  const [placeholderIndex, setPlaceholderIndex] = useState<number>(0);
  const [animationState, setAnimationState] = useState<
    "enter" | "stay" | "exit"
  >("enter");

  // Animation sequence: enter → stay → exit
  useEffect(() => {
    if (!placeholders || placeholders.length <= 1) {
      setTimeout(() => {
        setAnimationState("stay");
      }, 0);
      return;
    }

    const enterDuration = 500; // Time to slide from bottom to center
    const stayDuration = 2000; // Time to stay in center
    const exitDuration = 500; // Time to slide from center to top

    // Start with enter animation
    setTimeout(() => {
      setAnimationState("enter");
    }, 0);

    const stayTimer = setTimeout(() => {
      setAnimationState("stay");
    }, enterDuration);

    const exitTimer = setTimeout(() => {
      setAnimationState("exit");
    }, enterDuration + stayDuration);

    const nextTimer = setTimeout(
      () => {
        setPlaceholderIndex((prev) => (prev + 1) % placeholders.length);
        setAnimationState("enter");
      },
      enterDuration + stayDuration + exitDuration,
    );

    return () => {
      clearTimeout(stayTimer);
      clearTimeout(exitTimer);
      clearTimeout(nextTimer);
    };
  }, [placeholderIndex, placeholders]);

  // === Search logic ===
  const { recentSearches, addSearch, removeSearch, clearAll } =
    useRecentSearches();

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        onOpen();
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [onOpen]);

  const debouncedSearchQuery = useDebouncedValue(searchQuery, DEBOUNCE_DELAY);
  const swrKey = debouncedSearchQuery.trim()
    ? `search:${debouncedSearchQuery}`
    : null;

  const {
    data: searchResponse,
    error,
    isLoading,
    mutate,
  } = useSWR(swrKey, searchFetcher, {
    revalidateOnFocus: false,
    revalidateOnReconnect: false,
    shouldRetryOnError: false,
    errorRetryCount: 1,
  });

  const searchResults = useMemo<Product[]>(() => {
    if (
      !searchResponse?.success ||
      !searchResponse?.data?.data ||
      !Array.isArray(searchResponse.data.data)
    ) {
      return [];
    }
    return searchResponse.data.data;
  }, [searchResponse]);

  // Extract keywords from API response
  const searchKeywords = useMemo<string[]>(() => {
    if (
      searchResponse?.success &&
      Array.isArray(searchResponse?.data?.keywords)
    ) {
      return searchResponse.data.keywords;
    }
    return [];
  }, [searchResponse]);

  const handleInputChange = useCallback((value: string) => {
    setSearchQuery(value);
    setIsTyping(true);
  }, []);

  useEffect(() => {
    if (debouncedSearchQuery !== searchQuery) return;
    const timeout = setTimeout(() => setIsTyping(false), 0);
    return () => clearTimeout(timeout);
  }, [debouncedSearchQuery, searchQuery]);

  const handleClearSearch = useCallback(() => {
    setSearchQuery("");
    mutate(undefined, false);
  }, [mutate]);

  const handleSearchSubmit = useCallback(
    (query: string = searchQuery) => {
      const trimmedQuery = query.trim();
      if (trimmedQuery.length >= 2) {
        addSearch(trimmedQuery);
        if (trimmedQuery !== debouncedSearchQuery) {
          mutate(searchFetcher(`search:${trimmedQuery}`), false);
        }
        onClose();
        router.push({
          pathname: "/products/search",
          query: { q: trimmedQuery },
        });
      }
    },
    [searchQuery, addSearch, debouncedSearchQuery, mutate, router, onClose],
  );

  const handleProductClick = useCallback(
    (product: Product) => {
      router.push(`/products/${product.slug}`);
      onClose();
      handleClearSearch();
    },
    [onClose, router, handleClearSearch],
  );

  const handleChipClick = useCallback(
    (searchTerm: string) => {
      setSearchQuery(searchTerm);
      handleSearchSubmit(searchTerm);
    },
    [handleSearchSubmit],
  );

  const handleRemoveSearch = useCallback(
    (searchTerm: string, e: React.MouseEvent) => {
      e.stopPropagation();
      removeSearch(searchTerm);
    },
    [removeSearch],
  );

  const formatDeliveryTime = useCallback((time: number | null) => {
    return !time ? "N/A" : `${time} min`;
  }, []);

  const getOpacity = () => {
    return animationState === "stay" ? 1 : 0;
  };

  return (
    <>
      <div className="relative w-full md:max-w-md sm:max-w-full overflow-hidden">
        <Input
          as={"div"}
          classNames={{
            inputWrapper:
              isHomeSurface
                ? "!bg-white/50 data-[hover=true]:!bg-white/60 group-data-[focus=true]:!bg-white/60 dark:!bg-white/50 dark:data-[hover=true]:!bg-white/60 dark:group-data-[focus=true]:!bg-white/60"
                : "!bg-default-100 !border !border-default-200/80 data-[hover=true]:!bg-default-200 group-data-[focus=true]:!bg-default-200 dark:!bg-content1 dark:!border-default-100/20 dark:data-[hover=true]:!bg-content2 dark:group-data-[focus=true]:!bg-content2",
            input: isHomeSurface
              ? "!text-gray-700 dark:!text-gray-700"
              : "!text-foreground",
          }}
          startContent={
            <Search
              className={`w-4 h-4 ${isHomeSurface ? "text-gray-400" : "text-foreground/50"}`}
            />
          }
          endContent={
            <Button
              title={t("userLayout.shoppingList")}
              onPress={() => {
                router.push("/shopping-list");
              }}
              isIconOnly
              className="p-0 bg-transparent"
            >
              <ClipboardPenLine
                size={20}
                className={
                  isHomeSurface
                    ? "bg-transparent text-gray-500"
                    : "bg-transparent text-foreground/60"
                }
              />
            </Button>
          }
          onClick={onOpen}
          readOnly
          className="cursor-pointer"
        />

        {/* Animated placeholder text */}
        <span
          aria-hidden
          key={placeholderIndex}
          className={`absolute left-12 top-[75%] -translate-y-1/2
            ${isHomeSurface ? "text-gray-500" : "text-foreground/60"} truncate w-[70%] text-sm pointer-events-none
            transition-all duration-600 ease-in-out`}
          style={{
            transform: `translateY(calc(-50% + ${animationState === "enter" ? "20px" : animationState === "exit" ? "-20px" : "0px"}))`,
            opacity: getOpacity(),
          }}
        >
          {String(placeholders?.[placeholderIndex] ?? "")
            .replace(/_/g, " ")
            .replace(/\b\w/g, (c) => c.toUpperCase()) || "Search"}
        </span>
      </div>

      <SearchModal
        isOpen={isOpen}
        isTyping={isTyping}
        onClose={() => {
          onClose();
          handleClearSearch();
        }}
        searchQuery={searchQuery}
        searchResults={searchResults}
        keywords={searchKeywords}
        isLoading={isLoading}
        hasError={!!error}
        recentSearches={recentSearches}
        handleClearSearch={handleClearSearch}
        handleInputChange={handleInputChange}
        handleSearchSubmit={handleSearchSubmit}
        handleProductClick={handleProductClick}
        handleChipClick={handleChipClick}
        handleRemoveSearch={handleRemoveSearch}
        handleClearAllSearches={clearAll}
        formatDeliveryTime={formatDeliveryTime}
      />
    </>
  );
};

export default GlobalSearchBar;
