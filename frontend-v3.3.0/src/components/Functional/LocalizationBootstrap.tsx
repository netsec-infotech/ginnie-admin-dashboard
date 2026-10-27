import { useEffect, useRef } from "react";
import { bootstrapLocalization, refreshLocalization } from "@/lib/localization/client";

export default function LocalizationBootstrap({ serverBootstrapped = false }: { serverBootstrapped?: boolean }) {
  const bootstrappedAtMount = useRef(serverBootstrapped);
  useEffect(() => {
    if (!bootstrappedAtMount.current) void bootstrapLocalization();
    const refreshWhenVisible = () => {
      if (document.visibilityState === "visible") void refreshLocalization();
    };
    const refreshWhenOnline = () => { void refreshLocalization(); };
    document.addEventListener("visibilitychange", refreshWhenVisible);
    window.addEventListener("online", refreshWhenOnline);
    return () => {
      document.removeEventListener("visibilitychange", refreshWhenVisible);
      window.removeEventListener("online", refreshWhenOnline);
    };
  }, []);
  return null;
}
