import axios from "axios";

export type SafeValidationDetails =
  | string
  | string[]
  | Record<string, string | string[]>;

export type SafeClientErrorResponse = {
  success: false;
  message: string;
  data?: SafeValidationDetails;
  errors?: SafeValidationDetails;
};

type RetryableApiFailure = {
  retryable: boolean;
};

export type ApiFailureDetails = Readonly<{
  code?: string;
  method?: string;
  path?: string;
  status?: number;
}>;

const reportedAxiosErrors = new WeakSet<object>();
const SAFE_CLIENT_ERROR_STATUSES = new Set([400, 409, 422]);
const IDENTIFIER_PARENT_SEGMENTS = new Set([
  "addresses",
  "notifications",
  "orders",
  "returns",
  "shared",
  "transactions",
  "wishlists",
]);
const STATIC_RESOURCE_SEGMENTS = new Set([
  "active",
  "cancel",
  "count",
  "delivery-boy-location",
  "feedback",
  "flagged",
  "history",
  "items",
  "map",
  "recovery",
  "reschedule",
  "reviews",
  "status",
]);

export const sanitizeApiMessage = (value: unknown): string | undefined => {
  if (typeof value !== "string") return undefined;

  const sanitized = value
    .replace(/[\u0000-\u001f\u007f]/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, 500);
  return sanitized || undefined;
};

const sanitizeValidationDetails = (
  value: unknown,
  depth = 0,
): SafeValidationDetails | undefined => {
  if (depth > 1) return undefined;

  if (Array.isArray(value)) {
    const messages = value
      .slice(0, 20)
      .map(sanitizeApiMessage)
      .filter((message): message is string => Boolean(message));
    return messages.length ? messages : undefined;
  }

  if (!value || typeof value !== "object") {
    return sanitizeApiMessage(value);
  }

  const entries = Object.entries(value)
    .slice(0, 50)
    .flatMap(([field, fieldValue]) => {
      if (!/^[a-zA-Z0-9_.-]{1,80}$/.test(field)) return [];
      const sanitizedValue = sanitizeValidationDetails(fieldValue, depth + 1);
      return sanitizedValue === undefined ? [] : [[field, sanitizedValue]];
    });

  return entries.length
    ? (Object.fromEntries(entries) as Record<string, string | string[]>)
    : undefined;
};

const redactPathIdentifiers = (path: string): string => {
  const segments = path.split("/");

  return segments
    .map((segment, index) => {
      if (!segment) return segment;

      let decodedSegment = segment;
      try {
        decodedSegment = decodeURIComponent(segment);
      } catch {
        // Keep the encoded segment for the conservative checks below.
      }

      const parent = segments[index - 1]?.toLowerCase();
      const normalizedSegment = decodedSegment.toLowerCase();
      const looksLikeIdentifier =
        /^\d+$/.test(decodedSegment) ||
        /^[0-9a-f]{8}-[0-9a-f-]{27,}$/i.test(decodedSegment) ||
        decodedSegment.includes("@") ||
        (decodedSegment.length >= 24 &&
          /^[a-zA-Z0-9_-]+$/.test(decodedSegment));
      const followsPrivateResource =
        Boolean(parent && IDENTIFIER_PARENT_SEGMENTS.has(parent)) &&
        !STATIC_RESOURCE_SEGMENTS.has(normalizedSegment);

      return looksLikeIdentifier || followsPrivateResource ? ":id" : segment;
    })
    .join("/");
};

const getSafePath = (url: string | undefined): string | undefined => {
  if (!url) return undefined;

  try {
    // A fixed dummy origin lets us safely parse both relative and absolute URLs.
    // Only the path is retained: query strings can contain customer information.
    return redactPathIdentifiers(
      new URL(url, "https://local.invalid").pathname,
    );
  } catch {
    const path = url.split(/[?#]/, 1)[0]?.trim();
    return path ? redactPathIdentifiers(path) : undefined;
  }
};

export const getApiFailureDetails = (
  error: unknown,
): ApiFailureDetails | null => {
  if (!axios.isAxiosError(error)) return null;

  const method = error.config?.method?.toUpperCase();
  const path = getSafePath(error.config?.url);

  return {
    ...(error.code ? { code: error.code } : {}),
    ...(method ? { method } : {}),
    ...(path ? { path } : {}),
    ...(error.response?.status ? { status: error.response.status } : {}),
  };
};

/**
 * Keep actionable validation/conflict messages while refusing to pass raw
 * server, auth or network error bodies into customer-facing components.
 */
export const getSafeClientErrorResponse = (
  error: unknown,
): SafeClientErrorResponse | null => {
  if (!axios.isAxiosError(error)) return null;

  const status = error.response?.status;
  if (!status || !SAFE_CLIENT_ERROR_STATUSES.has(status)) return null;

  const payload = error.response?.data;
  if (!payload || typeof payload !== "object") return null;

  const unsafeResponse = payload as Record<string, unknown>;
  if (unsafeResponse.success !== false) return null;

  const response: SafeClientErrorResponse = {
    success: false,
    message:
      sanitizeApiMessage(unsafeResponse.message) ||
      "Please check your details and try again.",
  };
  const data = sanitizeValidationDetails(unsafeResponse.data);
  const errors = sanitizeValidationDetails(unsafeResponse.errors);

  if (data !== undefined) response.data = data;
  if (errors !== undefined) response.errors = errors;

  return response;
};

/**
 * Report expected transport failures without handing the Axios Error object to
 * `console.error`. Next's Pages Router dev overlay treats an Error in that call
 * as an uncaught runtime error, even when the request was already handled.
 *
 * The summary deliberately excludes headers, params, request bodies and the
 * response body. Unexpected non-Axios failures still use `console.error` so
 * genuine programming defects remain visible during development.
 */
export const reportApiFailure = (
  error: unknown,
  operation = "request",
): void => {
  const details = getApiFailureDetails(error);

  if (!details) {
    console.error(`[API] Unexpected ${operation} failure:`, error);
    return;
  }

  if (typeof error === "object" && error !== null) {
    if (reportedAxiosErrors.has(error)) return;
    reportedAxiosErrors.add(error);
  }

  console.warn(`[API] ${operation} failed`, details);
};

/** Attach retry policy to a typed fallback without exposing the raw failure. */
export const withApiFailureMetadata = <T extends object>(
  error: unknown,
  fallback: T,
): T & RetryableApiFailure => {
  const details = getApiFailureDetails(error);
  reportApiFailure(error);

  const status = details?.status;
  const retryable =
    details !== null &&
    (status === undefined ||
      status >= 500 ||
      status === 408 ||
      status === 425 ||
      status === 429);

  return { ...fallback, retryable };
};
