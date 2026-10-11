import { store } from "@/lib/redux/store";
import { updateCartData } from "./updators";
import { deleteCookie, getCookie, setCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { addToast } from "@heroui/react";
import { clearRecentlyViewed } from "@/lib/redux/slices/recentlyViewedSlice";

export const LOCATION_CHANGED_EVENT = "hyperlocal:location-changed";

export const onLocationChange = () => {
  if (typeof window === "undefined") return;

  window.dispatchEvent(new Event(LOCATION_CHANGED_EVENT));

  // Clear recently viewed products when location changes
  store.dispatch(clearRecentlyViewed());

  const pathButtonMap: Record<string, string[]> = {
    "/": [
      "home-products-refetch",
      "home-sections-refetch",
      "home-stores-refetch",
      "home-banners-refetch",
      "home-brands-refetch",
      "home-categories-refetch",
      "home-category-tabs",
    ],
    "/stores": ["refetch-store-page"],
    "/feature-sections": ["refetch-sections-page"],
    "/cart": ["refetch-cart-page", "refetch-similar-products"],
    "/categories": ["refetch-categories-page"],
    "/shopping-list": ["shopping-list-refetch"],
    "/brands": ["refetch-brands-page"],
  };

  const normalizePath = (path: string) =>
    path !== "/" ? path.replace(/\/+$/, "") : "/";

  const currentPath = normalizePath(window.location.pathname);

  let buttonIds: string[] = [];

  if (currentPath.startsWith("/feature-sections/")) {
    // Handle dynamic slug
    buttonIds = ["refetch-section-products"];
  } else if (
    currentPath.startsWith("/products/") ||
    currentPath.startsWith("/share/products/")
  ) {
    buttonIds = ["similar-products-refetch", "specific-product-refetch"];
  } else if (currentPath.startsWith("/brands/")) {
    buttonIds = ["refetch-brand-products"];
  } else if (currentPath.startsWith("/categories/")) {
    buttonIds = ["category-products-refetch"];
  } else if (currentPath.startsWith("/stores/")) {
    buttonIds = ["refetch-store-products"];
  } else {
    // Handle exact matches
    buttonIds = pathButtonMap[currentPath] || [];
  }

  buttonIds.forEach((id) => document.getElementById(id)?.click?.());

  updateCartData(false, false, 0, false);
};

export const onAppLoad = (
  defaultLocation: { lat: number; lng: number } | null = null,
) => {
  const path = typeof window !== "undefined" ? window.location.pathname : "";

  // 1. Check for location in URL params (deep link support)
  if (typeof window !== "undefined") {
    const urlParams = new URLSearchParams(window.location.search);
    const urlLat = urlParams.get("lat");
    const urlLng = urlParams.get("lng");

    if (urlLat && urlLng) {
      const currentUserLocation = getCookie<UserLocation>("userLocation");
      if (!currentUserLocation || currentUserLocation.source === "default") {
        const newUserLocation: UserLocation = {
          lat: parseFloat(urlLat),
          lng: parseFloat(urlLng),
          placeName: "Shared Location",
          placeDescription: "",
          source: "shared",
        };
        setCookie("userLocation", newUserLocation);
        // Location changed via URL, trigger refetch for current page components
        onLocationChange();
      }
    }
  }

  let userLocation = getCookie<UserLocation>("userLocation");

  if (
    defaultLocation &&
    (!userLocation || userLocation.source === "default")
  ) {
    const defaultChanged =
      !userLocation ||
      userLocation.lat !== defaultLocation.lat ||
      userLocation.lng !== defaultLocation.lng;

    if (defaultChanged) {
      setCookie<UserLocation>("userLocation", {
        ...defaultLocation,
        placeName: "Default Location",
        placeDescription: "",
        source: "default",
      });
      userLocation = getCookie<UserLocation>("userLocation");
      onLocationChange();
    }
  } else if (!defaultLocation && userLocation?.source === "default") {
    deleteCookie("userLocation");
    userLocation = null;
    onLocationChange();
  }

  if (store.getState().auth.isLoggedIn) {
    if (path !== "/cart" && path !== "/cart/") {
      updateCartData(true, false);
    }
  }

  if (!userLocation) {
    // Open location modal if no location is set (removed the share link exclusion)
    document.getElementById("location-modal-btn")?.click();
    addToast({ color: "default", title: "Please Select Location First !" });
  }
};

export const onHomeCategoryChange = () => {
  if (typeof window === "undefined") return;

  const pathButtonMap: Record<string, string[]> = {
    "/": [
      "home-banners-refetch",
      "home-brands-refetch",
      "home-categories-refetch",
      "home-products-refetch",
      "home-sections-refetch",
    ],
  };

  const normalizePath = (path: string) =>
    path !== "/" ? path.replace(/\/+$/, "") : "/";

  const currentPath = normalizePath(window.location.pathname);

  let buttonIds: string[] = [];

  buttonIds = pathButtonMap[currentPath] || [];

  // The builder homepage now switches category layouts through its
  // category-aware SWR key. Its hidden refetch button is reserved for
  // location changes; clicking it here would revalidate the same layout a
  // second time after the URL change.
  if (currentPath === "/" && document.getElementById("home-layout")) {
    buttonIds = buttonIds.filter((id) => id !== "home-sections-refetch");
  }

  buttonIds.forEach((id) => document.getElementById(id)?.click?.());
};
