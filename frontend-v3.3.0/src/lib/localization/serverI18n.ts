import { createInstance } from "i18next";
import { initReactI18next } from "react-i18next";
import type { LocalizationSnapshot } from "@/types/localization";

export function createServerI18n(snapshot: LocalizationSnapshot) {
  const instance = createInstance();
  void instance.use(initReactI18next).init({
    resources: { [snapshot.locale]: { translation: snapshot.labels } },
    lng: snapshot.locale,
    fallbackLng: snapshot.locale,
    initImmediate: false,
    interpolation: { escapeValue: false },
  });
  return instance;
}
