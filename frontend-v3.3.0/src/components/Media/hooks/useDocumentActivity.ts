import { useEffect, useState } from "react";

/** Tracks whether browser focus/visibility should suspend immersive playback. */
export const useDocumentActivity = (enabled: boolean) => {
  const [isInactive, setIsInactive] = useState(false);

  useEffect(() => {
    if (!enabled) return;

    const handleBlur = () => setIsInactive(true);
    const handleFocus = () => setIsInactive(false);
    const handleVisibility = () =>
      setIsInactive(document.visibilityState === "hidden");

    const initialCheckTimer = window.setTimeout(handleVisibility, 0);
    window.addEventListener("blur", handleBlur);
    window.addEventListener("focus", handleFocus);
    document.addEventListener("visibilitychange", handleVisibility);

    return () => {
      window.clearTimeout(initialCheckTimer);
      window.removeEventListener("blur", handleBlur);
      window.removeEventListener("focus", handleFocus);
      document.removeEventListener("visibilitychange", handleVisibility);
    };
  }, [enabled]);

  return enabled && isInactive;
};
