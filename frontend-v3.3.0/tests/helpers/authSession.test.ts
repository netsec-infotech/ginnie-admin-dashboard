import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  addToast: vi.fn(),
  announceAuthSessionChange: vi.fn(),
  appleLogin: vi.fn(),
  clearCart: vi.fn(() => ({ type: "cart/clear" })),
  clearRecentlyViewed: vi.fn(() => ({ type: "recently-viewed/clear" })),
  deleteCookie: vi.fn(),
  dispatch: vi.fn(),
  getCookie: vi.fn(),
  googleLogin: vi.fn(),
  login: vi.fn(),
  logoutAction: vi.fn(() => ({ type: "auth/logout" })),
  logoutApi: vi.fn(),
  mutate: vi.fn(),
  phoneLogin: vi.fn(),
  registerUser: vi.fn(),
  routerPush: vi.fn(),
  setAnalyticsUserId: vi.fn(),
  setCookie: vi.fn(),
  state: { isLoggedIn: true },
  updateCartData: vi.fn(),
  updateDataOnAuth: vi.fn(),
  verifyUser: vi.fn(),
}));

vi.mock("@/lib/cookies", () => ({
  announceAuthSessionChange: mocks.announceAuthSessionChange,
  deleteCookie: mocks.deleteCookie,
  getCookie: mocks.getCookie,
  setCookie: mocks.setCookie,
}));

vi.mock("@/lib/firebase", () => ({
  clearRecaptchaVerifier: vi.fn(),
  getFirebaseErrorMessage: vi.fn(() => "Firebase error"),
  initializeRecaptchaVerifier: vi.fn(),
}));

vi.mock("@/routes/api", () => ({
  appleLogin: mocks.appleLogin,
  googleLogin: mocks.googleLogin,
  login: mocks.login,
  logout: mocks.logoutApi,
  phoneLogin: mocks.phoneLogin,
  registerUser: mocks.registerUser,
  verifyUser: mocks.verifyUser,
}));

vi.mock("@heroui/react", () => ({ addToast: mocks.addToast }));
vi.mock("swr", () => ({ mutate: mocks.mutate }));
vi.mock("firebase/app", () => ({ FirebaseError: class FirebaseError {} }));
vi.mock("firebase/auth", () => ({
  signInWithPhoneNumber: vi.fn(),
  signInWithPopup: vi.fn(),
}));

vi.mock("@/lib/redux/slices/authSlice", () => ({
  login: vi.fn((payload) => ({ type: "auth/login", payload })),
  logout: mocks.logoutAction,
}));
vi.mock("@/lib/redux/slices/cartSlice", () => ({
  clearCart: mocks.clearCart,
}));
vi.mock("@/lib/redux/slices/recentlyViewedSlice", () => ({
  clearRecentlyViewed: mocks.clearRecentlyViewed,
}));
vi.mock("@/lib/redux/store", () => ({
  store: {
    dispatch: mocks.dispatch,
    getState: () => ({ auth: { isLoggedIn: mocks.state.isLoggedIn } }),
  },
}));

vi.mock("next/router", () => ({
  default: { push: mocks.routerPush },
}));
vi.mock("@/helpers/updators", () => ({
  syncOfflineCartToServer: vi.fn(),
  updateCartData: mocks.updateCartData,
  updateDataOnAuth: mocks.updateDataOnAuth,
}));
vi.mock("@/lib/analytics", () => ({
  setAnalyticsUserId: mocks.setAnalyticsUserId,
  setAnalyticsUserProperties: vi.fn(),
  trackLogin: vi.fn(),
  trackSignUp: vi.fn(),
}));
vi.mock("../../i18n", () => ({
  default: { t: (key: string) => key },
}));

import { handleAppleLogin, handleGoogleLogin, handleLogout, handleUnauthorized } from "@/helpers/auth";
import { signInWithPopup, type UserCredential } from "firebase/auth";
import type { FirebaseInstance } from "@/lib/firebase";

const deferred = () => {
  let resolve!: () => void;
  const promise = new Promise<void>((resolvePromise) => {
    resolve = resolvePromise;
  });

  return { promise, resolve };
};

beforeEach(() => {
  vi.clearAllMocks();
  mocks.state.isLoggedIn = true;
  mocks.dispatch.mockImplementation((action: { type?: string }) => {
    if (action?.type === "auth/logout") mocks.state.isLoggedIn = false;
    return action;
  });
  mocks.getCookie.mockImplementation((name: string) =>
    name === "access_token" ? "private-token" : null,
  );
  mocks.logoutApi.mockResolvedValue({
    success: false,
    message: "Server logout failed",
    data: undefined,
  });
  mocks.routerPush.mockResolvedValue(true);
  window.history.replaceState({}, "", "/");
  localStorage.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
  delete window.firebaseInstance;
});

describe("auth session cleanup", () => {
  it.each([
    ["Google", handleGoogleLogin, mocks.googleLogin],
    ["Apple", handleAppleLogin, mocks.appleLogin],
  ] as const)("shows a rejected %s backend login and leaves the dialog open", async (_name, login, api) => {
    window.firebaseInstance = {} as FirebaseInstance;
    vi.mocked(signInWithPopup).mockResolvedValue({ user: { getIdToken: async () => "firebase-token" } } as UserCredential);
    api.mockResolvedValue({ success: false, message: "Account is disabled" });
    const setIsLoading = vi.fn();
    const onOpenChange = vi.fn();
    await login({ setIsLoading, onOpenChange });
    expect(mocks.addToast).toHaveBeenCalledWith(expect.objectContaining({ color: "danger", description: "Account is disabled" }));
    expect(onOpenChange).not.toHaveBeenCalled();
    expect(mocks.setCookie).not.toHaveBeenCalled();
    expect(setIsLoading).toHaveBeenLastCalledWith(false);
  });
  it("runs concurrent unauthorized cleanup as one flight without calling logout", async () => {
    const navigation = deferred();
    window.history.replaceState({}, "", "/my-account/orders");
    mocks.routerPush.mockReturnValue(navigation.promise);

    const first = handleUnauthorized();
    const second = handleUnauthorized();

    expect(second).toBe(first);
    expect(mocks.logoutApi).not.toHaveBeenCalled();
    expect(mocks.logoutAction).toHaveBeenCalledOnce();
    expect(mocks.clearCart).toHaveBeenCalledOnce();
    expect(mocks.clearRecentlyViewed).toHaveBeenCalledOnce();
    expect(mocks.mutate).toHaveBeenCalledWith(expect.any(Function), undefined, { revalidate: false });
    const clearsKey = mocks.mutate.mock.calls[0][0];
    for (const key of ["user-data", "addresses:2", "/api/orders?page=1", "/api/wishlists", "/api/notifications?page=1", "/save-for-later", "/cart/dates", [{ page: 1, transaction_type: "" }], [{ key: "loyalty-points", page: 1 }]]) {
      expect(clearsKey(key)).toBe(true);
    }
    for (const key of ["/categories", "/banners", "/products/milk", "search:milk", ["seller-reviews", 1], ["watch-and-buy-stories", 1]]) {
      expect(clearsKey(key)).toBe(false);
    }

    navigation.resolve();
    await Promise.all([first, second]);

    expect(mocks.routerPush).toHaveBeenCalledOnce();
    expect(mocks.deleteCookie.mock.calls).toEqual([["access_token"], ["user"]]);
    expect(mocks.setAnalyticsUserId).toHaveBeenCalledWith("");
    expect(mocks.announceAuthSessionChange).toHaveBeenCalledOnce();
  });

  it("clears the local session even when the logout API reports failure", async () => {
    localStorage.setItem("fcm-token", "fcm-private");
    localStorage.setItem("shoppingListKeywords", "milk");

    await expect(handleLogout(false)).resolves.toBeUndefined();

    expect(mocks.logoutApi).toHaveBeenCalledOnce();
    expect(mocks.logoutApi).toHaveBeenCalledWith("private-token", {
      fcm_token: "fcm-private",
    });
    expect(mocks.logoutAction).toHaveBeenCalledOnce();
    expect(mocks.clearCart).toHaveBeenCalledOnce();
    expect(mocks.clearRecentlyViewed).toHaveBeenCalledOnce();
    expect(mocks.deleteCookie.mock.calls).toEqual([["access_token"], ["user"]]);
    expect(localStorage.getItem("shoppingListKeywords")).toBeNull();
    expect(mocks.setAnalyticsUserId).toHaveBeenCalledWith("");
    expect(mocks.announceAuthSessionChange).toHaveBeenCalledOnce();
  });

  it("continues authoritative cleanup when browser storage is blocked", async () => {
    vi.spyOn(Storage.prototype, "removeItem").mockImplementation(() => {
      throw new DOMException("Storage blocked", "SecurityError");
    });
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});

    await expect(handleUnauthorized()).resolves.toBeUndefined();

    expect(mocks.logoutAction).toHaveBeenCalledOnce();
    expect(mocks.deleteCookie.mock.calls).toEqual([["access_token"], ["user"]]);
    expect(mocks.clearCart).toHaveBeenCalledOnce();
    expect(mocks.clearRecentlyViewed).toHaveBeenCalledOnce();
    expect(warn).toHaveBeenCalledWith(
      "[Auth] Unable to clear shopping-list storage; continuing local sign-out.",
    );
  });
});
