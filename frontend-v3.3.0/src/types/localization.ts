export type Direction = "ltr" | "rtl";

export type TranslationTree = {
  [key: string]: string | TranslationTree;
};

export type WebLanguage = {
  code: string;
  name: string;
  native_name: string;
  direction: Direction;
};

export type LanguagesData = {
  default: string;
  languages: WebLanguage[];
};

export type LanguageLabelsData = {
  locale: string;
  direction: Direction;
  labels: TranslationTree;
};

export type LocalizationSnapshot = {
  locale: string;
  direction: Direction;
  labels: TranslationTree;
  source: "api" | "local";
};
