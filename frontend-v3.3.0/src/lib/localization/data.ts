import en from "../../../public/locales/en.json";
import hi from "../../../public/locales/hi.json";
import ar from "../../../public/locales/ar.json";
import type {
  Direction,
  LanguageLabelsData,
  LanguagesData,
  LocalizationSnapshot,
  TranslationTree,
  WebLanguage,
} from "@/types/localization";

export const LOCAL_DEFAULT = "en";

const localDictionaries: Record<string, TranslationTree> = { en, hi, ar };

export const localLanguages: WebLanguage[] = [
  { code: "en", name: en.language_switcher.languages.en, native_name: en.language_switcher.languages.en, direction: "ltr" },
  { code: "hi", name: hi.language_switcher.languages.hi, native_name: hi.language_switcher.languages.hi, direction: "ltr" },
  { code: "ar", name: ar.language_switcher.languages.ar, native_name: ar.language_switcher.languages.ar, direction: "rtl" },
];

const isRecord = (value: unknown): value is Record<string, unknown> =>
  value !== null && typeof value === "object" && !Array.isArray(value);

const isDirection = (value: unknown): value is Direction =>
  value === "ltr" || value === "rtl";

const isLocaleCode = (value: unknown): value is string =>
  typeof value === "string" && /^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(value);

const validTree = (value: unknown, depth = 0): value is TranslationTree => {
  if (!isRecord(value) || depth > 20) return false;
  return Object.entries(value).every(
    ([key, entry]) =>
      key !== "__proto__" && key !== "constructor" && key !== "prototype" &&
      (typeof entry === "string" || validTree(entry, depth + 1)),
  );
};

export function parseLanguages(value: unknown): LanguagesData | null {
  if (!isRecord(value) || !isLocaleCode(value.default) || !Array.isArray(value.languages)) {
    return null;
  }
  const languages: WebLanguage[] = [];
  const codes = new Set<string>();
  for (const entry of value.languages) {
    if (
      !isRecord(entry) ||
      !isLocaleCode(entry.code) ||
      typeof entry.name !== "string" ||
      !entry.name.trim() ||
      typeof entry.native_name !== "string" ||
      !isDirection(entry.direction) ||
      codes.has(entry.code)
    ) return null;
    codes.add(entry.code);
    languages.push(entry as WebLanguage);
  }
  if (!codes.has(value.default)) return null;
  return { default: value.default, languages };
}

export function parseLabels(value: unknown, requestedLocale: string): LanguageLabelsData | null {
  if (
    !isRecord(value) ||
    value.locale !== requestedLocale ||
    !isDirection(value.direction) ||
    !validTree(value.labels) ||
    Object.keys(value.labels).length === 0
  ) return null;
  return value as LanguageLabelsData;
}

function mergeTrees(base: TranslationTree, override: TranslationTree): TranslationTree {
  const merged: TranslationTree = { ...base };
  for (const [key, value] of Object.entries(override)) {
    if (typeof value === "string") {
      if (value.trim()) merged[key] = value;
    } else {
      const previous = merged[key];
      merged[key] = mergeTrees(typeof previous === "string" ? {} : previous || {}, value);
    }
  }
  return merged;
}

export function getLocalSnapshot(locale: string): LocalizationSnapshot | null {
  const labels = localDictionaries[locale];
  const language = localLanguages.find((entry) => entry.code === locale);
  if (!labels || !language) return null;
  return {
    locale,
    direction: language.direction,
    labels: mergeTrees(localDictionaries.en, labels),
    source: "local",
  };
}

export function getApiSnapshot(data: LanguageLabelsData): LocalizationSnapshot {
  const fallback = getLocalSnapshot(data.locale)?.labels || localDictionaries.en;
  return {
    locale: data.locale,
    direction: data.direction,
    labels: mergeTrees(fallback, data.labels),
    source: "api",
  };
}

export function translate(
  labels: TranslationTree,
  key: string,
  values: Record<string, string | number> = {},
): string {
  const result = key.split(".").reduce<string | TranslationTree | undefined>(
    (node, segment) => typeof node === "string" ? undefined : node?.[segment],
    labels,
  );
  if (typeof result !== "string") return "";
  return result.replace(/{{\s*([^{}]+?)\s*}}/g, (match, name: string) =>
    Object.hasOwn(values, name) ? String(values[name]) : match,
  );
}
