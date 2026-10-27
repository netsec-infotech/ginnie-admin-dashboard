import { useMemo } from "react";

import type { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { staticLat, staticLng } from "@/config/constants";
import { useSettings } from "@/contexts/SettingsContext";
import { getCookie } from "@/lib/cookies";

const isCoordinate = (value: unknown): value is number =>
  typeof value === "number" && Number.isFinite(value);

export const useWatchAndBuyLocation = () => {
  const { defaultLocation } = useSettings();
  const cookieLocation = getCookie<UserLocation>("userLocation");
  const latitude = isCoordinate(cookieLocation?.lat)
    ? cookieLocation.lat
    : isCoordinate(defaultLocation?.lat)
      ? defaultLocation.lat
      : staticLat;
  const longitude = isCoordinate(cookieLocation?.lng)
    ? cookieLocation.lng
    : isCoordinate(defaultLocation?.lng)
      ? defaultLocation.lng
      : staticLng;

  return useMemo(
    () => ({ latitude, longitude }),
    [latitude, longitude],
  );
};
