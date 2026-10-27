// lib/cookies.ts

// Define the shape of cookie options
interface CookieOptions {
  expires?: number; // Number of days
  secure?: boolean;
  sameSite?: "strict" | "lax" | "none";
  path?: string;
}

export const AUTH_SESSION_SYNC_KEY = "hyperlocal:auth-session-change";
export const CART_SYNC_STORAGE_KEY = "hyperlocal:cart-change";

/** Notify other tabs without copying a token or customer identifier. */
export function announceAuthSessionChange(): void {
  if (typeof window === "undefined") return;
  try {
    const nonce = window.crypto?.randomUUID?.() || Math.random().toString(36);
    localStorage.setItem(AUTH_SESSION_SYNC_KEY, `${Date.now()}:${nonce}`);
  } catch {
    // Storage can be unavailable in privacy modes. Transport-level session
    // binding still prevents stale authenticated responses in that case.
  }
}

export function subscribeToAuthSessionChanges(
  listener: () => void,
): () => void {
  if (typeof window === "undefined") return () => {};
  const handleStorage = (event: StorageEvent) => {
    if (event.key === AUTH_SESSION_SYNC_KEY && event.newValue) listener();
  };
  window.addEventListener("storage", handleStorage);
  return () => window.removeEventListener("storage", handleStorage);
}

// Set a cookie with the given name, value, and options
export function setCookie<T>(
  name: string,
  value: T,
  options: CookieOptions = {}
): void {
  if (typeof document === "undefined") return;

  const defaultOptions: CookieOptions = {
    expires: 365,
    secure:
      typeof window !== "undefined" && window.location.protocol === "https:",
    sameSite: "lax",
    path: "/",
  };

  const { expires, ...rest } = { ...defaultOptions, ...options };

  let cookieString = `${encodeURIComponent(name)}=${encodeURIComponent(
    JSON.stringify(value)
  )}`;

  if (expires) {
    const date = new Date();
    date.setDate(date.getDate() + expires);
    cookieString += `; expires=${date.toUTCString()}`;
  }

  Object.entries(rest).forEach(([key, val]) => {
    cookieString += `; ${key}${val ? `=${val}` : ""}`;
  });

  document.cookie = cookieString;
}

// Get a cookie by name, with type inference
export function getCookie<T>(name: string): T | null {
  // If running on the server, return null — there is no document.cookie
  if (typeof document === "undefined") return null;

  const cookies = document.cookie
    .split(";")
    .reduce((acc: { [key: string]: string }, cookie: string) => {
      const [key, value] = cookie.trim().split("=");
      acc[key] = value;
      return acc;
    }, {});
  try {
    return cookies[name] ? JSON.parse(decodeURIComponent(cookies[name])) : null;
  } catch {
    return null;
  }
}

// Delete a cookie by name
export function deleteCookie(name: string): void {
  if (typeof document === "undefined") return;
  document.cookie = `${encodeURIComponent(
    name
  )}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
}
