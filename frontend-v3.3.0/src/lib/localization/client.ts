import { getWebLanguageLabels, getWebLanguages } from "@/routes/api";
import { getCookie, setCookie } from "@/lib/cookies";
import { store } from "@/lib/redux/store";
import {
  activateLanguage,
  languagesReceived,
  localizationFailed,
  switchFinished,
  switchStarted,
} from "@/lib/redux/slices/localizationSlice";
import {
  getApiSnapshot,
  getLocalSnapshot,
  LOCAL_DEFAULT,
  parseLabels,
  parseLanguages,
} from "@/lib/localization/data";
import { applyLanguageSnapshot } from "../../../i18n";
import type { LocalizationSnapshot, WebLanguage } from "@/types/localization";

const LANGUAGE_COOKIE = "i18nextLng";
let sequence = 0;
let pendingSwitch: AbortController | null = null;
let bootPromise: Promise<void> | null = null;
let refreshPromise: Promise<void> | null = null;

function commit(snapshot: LocalizationSnapshot, persistPreference: boolean): void {
  store.dispatch(activateLanguage(snapshot));
  applyLanguageSnapshot(snapshot);
  if (persistPreference) setCookie(LANGUAGE_COOKIE, snapshot.locale, { expires: 365 });
}

function revealDocument(): void {
  if (typeof document === "undefined") return;
  requestAnimationFrame(() => {
    document.documentElement.removeAttribute("data-locale-pending");
  });
}

async function fetchSnapshot(locale: string, signal?: AbortSignal): Promise<LocalizationSnapshot | null> {
  const response = await getWebLanguageLabels(locale, signal);
  const parsed = response.success ? parseLabels(response.data, locale) : null;
  return parsed ? getApiSnapshot(parsed) : null;
}

export function bootstrapLocalization(): Promise<void> {
  if (bootPromise) return bootPromise;
  bootPromise = (async () => {
    const saved = getCookie<string>(LANGUAGE_COOKIE);
    const candidate = typeof saved === "string" && saved.trim() ? saved : null;
    const candidateFallback = getLocalSnapshot(candidate || LOCAL_DEFAULT);
    const local = candidateFallback || getLocalSnapshot(LOCAL_DEFAULT)!;
    commit(local, false);
    revealDocument();

    const startedAt = sequence;
    const prefetchedLocale = candidate || LOCAL_DEFAULT;
    const candidatePromise = fetchSnapshot(prefetchedLocale);
    const listResponse = await getWebLanguages();
    const list = listResponse.success ? parseLanguages(listResponse.data) : null;
    if (!list) {
      if (candidate && !candidateFallback) setCookie(LANGUAGE_COOKIE, LOCAL_DEFAULT, { expires: 365 });
      store.dispatch(localizationFailed());
      return;
    }
    store.dispatch(languagesReceived({ languages: list.languages, defaultLocale: list.default }));
    const selected = candidate && list.languages.some((item) => item.code === candidate)
      ? candidate
      : list.default;
    const selectedLanguage = list.languages.find((item) => item.code === selected);
    const snapshot = selected === prefetchedLocale
      ? await candidatePromise
      : await fetchSnapshot(selected);
    if (sequence !== startedAt) return;
    if (snapshot && snapshot.direction === selectedLanguage?.direction) {
      commit(snapshot, selected !== candidate);
      return;
    }
    const fallback = getLocalSnapshot(selected);
    if (fallback) commit(fallback, selected !== candidate);
    else {
      commit(getLocalSnapshot(LOCAL_DEFAULT)!, true);
      store.dispatch(localizationFailed());
    }
  })().finally(() => {
    bootPromise = null;
  });
  return bootPromise;
}

export function refreshLocalization(): Promise<void> {
  if (bootPromise) return bootPromise;
  if (refreshPromise) return refreshPromise;
  refreshPromise = (async () => {
    const startedAt = sequence;
    const current = store.getState().localization.active.locale;
    const [listResponse, currentSnapshot] = await Promise.all([
      getWebLanguages(),
      fetchSnapshot(current),
    ]);
    const list = listResponse.success ? parseLanguages(listResponse.data) : null;
    if (!list) return;
    store.dispatch(languagesReceived({ languages: list.languages, defaultLocale: list.default }));
    const selected = list.languages.find((item) => item.code === current);
    const snapshot = selected ? currentSnapshot : await fetchSnapshot(list.default);
    const direction = selected?.direction ||
      list.languages.find((item) => item.code === list.default)?.direction;
    if (sequence !== startedAt || !snapshot || snapshot.direction !== direction) return;
    commit(snapshot, !selected);
  })().finally(() => {
    refreshPromise = null;
  });
  return refreshPromise;
}

export async function switchLanguage(locale: string): Promise<boolean> {
  const state = store.getState().localization;
  if (locale === state.active.locale && state.switchStatus !== "loading") return true;
  const language: WebLanguage | undefined = state.languages.find((item) => item.code === locale);
  if (!language) return false;
  const requestId = ++sequence;
  pendingSwitch?.abort();
  const controller = new AbortController();
  pendingSwitch = controller;
  store.dispatch(switchStarted({ locale, requestId }));
  const apiSnapshot = await fetchSnapshot(locale, controller.signal);
  if (sequence !== requestId) return false;
  const snapshot = apiSnapshot?.direction === language.direction
    ? apiSnapshot
    : getLocalSnapshot(locale);
  if (!snapshot) {
    store.dispatch(switchFinished({ requestId }));
    return false;
  }
  applyLanguageSnapshot(snapshot);
  setCookie(LANGUAGE_COOKIE, locale, { expires: 365 });
  store.dispatch(switchFinished({ requestId, snapshot }));
  pendingSwitch = null;
  return true;
}
