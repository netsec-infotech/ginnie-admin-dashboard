import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import type { GetServerSidePropsContext } from "next";
import { getCookie, setCookie } from "@/lib/cookies";
import type { LocalizationSnapshot } from "@/types/localization";

// Languages
import enTranslation from "./public/locales/en.json";
import hiTranslation from "./public/locales/hi.json";
import arTranslation from "./public/locales/ar.json";

const LANGUAGE_KEY = "i18nextLng";

const DEFAULT_LANG = "en";

const languages = {
  en: {
    translation: enTranslation,
  },
  hi: {
    translation: hiTranslation,
  },
  ar: {
    translation: arTranslation,
  },
};

// Initialize i18n with static defaults. Client-specific detection will run later.
i18n.use(initReactI18next).init({
  resources: languages,
  // Static HTML and the first browser render must use the same language.
  // Restore the browser preference only after React has hydrated.
  lng: DEFAULT_LANG,
  fallbackLng: DEFAULT_LANG,
  interpolation: {
    escapeValue: false,
  },
});

// Function to change language and persist in cookie
export const changeLanguage = (lng: string) => {
  const language = Object.hasOwn(languages, lng) ? lng : DEFAULT_LANG;
  // Persist cookie only in browser
  if (typeof window !== "undefined") {
    setCookie(LANGUAGE_KEY, language, { expires: 365 });
  }
  i18n.changeLanguage(language);

  // Ensure this code runs only in the browser
  if (typeof document !== "undefined") {
    document.documentElement.setAttribute("lang", language);
    if (language === "ar") {
      document.documentElement.setAttribute("dir", "rtl");
    } else {
      document.documentElement.setAttribute("dir", "ltr");
    }
  }
};

// Existing useTranslation callers read from i18next while Redux owns the
// active dictionary. Replace the bundle before announcing a language change.
export const applyLanguageSnapshot = (snapshot: LocalizationSnapshot) => {
  // Replace the entire active bundle so an admin-deleted key cannot survive
  // from an earlier API response in this browser tab.
  i18n.removeResourceBundle(snapshot.locale, "translation");
  i18n.addResourceBundle(snapshot.locale, "translation", snapshot.labels, false, true);
  if (typeof document !== "undefined") {
    document.documentElement.setAttribute("lang", snapshot.locale);
    document.documentElement.setAttribute("dir", snapshot.direction);
  }
  void i18n.changeLanguage(snapshot.locale);
};

export const restoreLanguagePreference = () => {
  changeLanguage(getCookie<string>(LANGUAGE_KEY) || DEFAULT_LANG);
};

// Compatibility with page data loaders. Resources are bundled above; never
// mutate the shared server instance from a request cookie. Besides differing
// from static HTML, that would leak language between concurrent SSR requests.
export const loadTranslations = async (context: GetServerSidePropsContext) => {
  void context;
};

export default i18n;
