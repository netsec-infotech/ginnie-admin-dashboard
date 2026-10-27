import type { IncomingMessage } from "node:http";
import { parse } from "cookie";
import { getWebLanguageLabels, getWebLanguages } from "@/routes/api";
import {
  getApiSnapshot,
  getLocalSnapshot,
  localLanguages,
  LOCAL_DEFAULT,
  parseLabels,
  parseLanguages,
} from "@/lib/localization/data";
import type { LocalizationSnapshot, WebLanguage } from "@/types/localization";

export type ServerLocalization = {
  snapshot: LocalizationSnapshot;
  languages: WebLanguage[];
  defaultLocale: string;
};

const requestResults = new WeakMap<IncomingMessage, ServerLocalization>();

export function getRequestLocalization(request: IncomingMessage | undefined): ServerLocalization | undefined {
  return request ? requestResults.get(request) : undefined;
}

function cookieLocale(request: IncomingMessage | undefined): string | null {
  if (!request) return null;
  try {
    const raw = parse(request.headers.cookie || "").i18nextLng;
    if (!raw) return null;
    const parsed: unknown = JSON.parse(decodeURIComponent(raw));
    return typeof parsed === "string" && parsed ? parsed : null;
  } catch {
    return null;
  }
}

export async function loadServerLocalization(request: IncomingMessage | undefined): Promise<ServerLocalization> {
  const saved = cookieLocale(request);
  const prefetchedLocale = saved || LOCAL_DEFAULT;
  const candidateRequest = getWebLanguageLabels(prefetchedLocale);
  const response = await getWebLanguages();
  const list = response.success ? parseLanguages(response.data) : null;
  const selected = list?.languages.some((item) => item.code === saved)
    ? saved!
    : list?.default || (getLocalSnapshot(saved || "") ? saved! : LOCAL_DEFAULT);
  const labelsResponse = selected === prefetchedLocale
    ? await candidateRequest
    : await getWebLanguageLabels(selected);
  const parsed = labelsResponse.success ? parseLabels(labelsResponse.data, selected) : null;
  const expectedDirection = list?.languages.find((item) => item.code === selected)?.direction;
  const snapshot = parsed && (!expectedDirection || parsed.direction === expectedDirection)
    ? getApiSnapshot(parsed)
    : getLocalSnapshot(selected) || getLocalSnapshot(LOCAL_DEFAULT)!;
  const result: ServerLocalization = {
    snapshot,
    languages: list?.languages || localLanguages,
    defaultLocale: list?.default || LOCAL_DEFAULT,
  };
  if (request) requestResults.set(request, result);
  return result;
}
