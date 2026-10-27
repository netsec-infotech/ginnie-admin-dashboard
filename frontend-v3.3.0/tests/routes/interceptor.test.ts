import axios, {
  AxiosError,
  AxiosHeaders,
  type AxiosInstance,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from "axios";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { setupInterceptors } from "@/routes/interceptor";
import { maintenanceStore } from "@/stores/maintenanceStore";
import { deleteCookie, setCookie } from "@/lib/cookies";

const authMocks = vi.hoisted(() => ({
  handleUnauthorized: vi.fn(),
}));

vi.mock("@/helpers/auth", () => ({
  handleUnauthorized: authMocks.handleUnauthorized,
}));

const createResponse = (
  config: InternalAxiosRequestConfig,
  status: number,
  data: unknown,
): AxiosResponse => ({
  config,
  data,
  headers: new AxiosHeaders(),
  status,
  statusText: status === 200 ? "OK" : "Request failed",
});

const createAxiosError = (
  status: number,
  data: unknown,
  url: string,
): AxiosError => {
  const config: InternalAxiosRequestConfig = {
    headers: new AxiosHeaders(),
    method: "get",
    url,
  };

  return new AxiosError(
    "Private backend error",
    "ERR_BAD_RESPONSE",
    config,
    undefined,
    createResponse(config, status, data),
  );
};

const createRejectingInstance = (error: AxiosError): AxiosInstance => {
  const instance = axios.create({
    adapter: async () => Promise.reject(error),
  });
  setupInterceptors(instance);
  return instance;
};

const createSuccessfulInstance = (): AxiosInstance => {
  const instance = axios.create({
    adapter: async (config) => createResponse(config, 200, { success: true }),
  });
  setupInterceptors(instance);
  return instance;
};

beforeEach(() => {
  maintenanceStore.setMaintenance(false, null);
  deleteCookie("access_token");
  deleteCookie("i18nextLng");
  vi.stubEnv("NEXT_PUBLIC_DEFAULT_LOCALE", "en");
  authMocks.handleUnauthorized.mockReset();
});

afterEach(() => {
  maintenanceStore.setMaintenance(false, null);
  vi.restoreAllMocks();
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  deleteCookie("access_token");
  deleteCookie("i18nextLng");
});

describe("request locale header", () => {
  it.each(["en", "hi", "ar", "fr-CA"])(
    "sends the selected %s language code",
    async (locale) => {
      setCookie("i18nextLng", locale);
      const response = await createSuccessfulInstance().get("/products");

      expect(response.config.headers.get("X-Locale")).toBe(locale);
      expect(response.config.headers.get("Accept-Language")).toBe(locale);
    },
  );

  it("reads a changed language for the next request on the same client", async () => {
    const instance = createSuccessfulInstance();
    setCookie("i18nextLng", "en");
    const first = await instance.get("/products");
    setCookie("i18nextLng", "ar");
    const second = await instance.get("/products");

    expect(first.config.headers.get("X-Locale")).toBe("en");
    expect(second.config.headers.get("X-Locale")).toBe("ar");
  });

  it("uses the environment fallback when no language is selected", async () => {
    vi.stubEnv("NEXT_PUBLIC_DEFAULT_LOCALE", "hi");
    const response = await createSuccessfulInstance().get("/settings");

    expect(response.config.headers.get("X-Locale")).toBe("hi");
    expect(response.config.headers.get("Accept-Language")).toBe("hi");
  });

  it.each(["", "  ", 42, { locale: "ar" }, "en\r\ninvalid"])(
    "uses the environment fallback for an invalid cookie: %j",
    async (value) => {
      vi.stubEnv("NEXT_PUBLIC_DEFAULT_LOCALE", "hi");
      setCookie("i18nextLng", value);
      const response = await createSuccessfulInstance().get("/products");

      expect(response.config.headers.get("X-Locale")).toBe("hi");
    },
  );

  it("uses the fallback when the language cookie cannot be parsed", async () => {
    vi.stubEnv("NEXT_PUBLIC_DEFAULT_LOCALE", "ar");
    document.cookie = "i18nextLng=invalid-json; path=/";
    const response = await createSuccessfulInstance().get("/products");

    expect(response.config.headers.get("X-Locale")).toBe("ar");
  });

  it("recognizes an explicit lowercase locale header without duplicating it", async () => {
    setCookie("i18nextLng", "ar");
    const response = await createSuccessfulInstance().get("/products", {
      headers: { "x-locale": "hi" },
    });

    expect(response.config.headers.get("X-Locale")).toBe("hi");
    expect(response.config.headers.get("Accept-Language")).toBe("hi");
    expect(
      Object.keys(response.config.headers.toJSON()).filter(
        (key) => key.toLowerCase() === "x-locale",
      ),
    ).toHaveLength(1);
  });

  it("preserves a caller's existing Accept-Language header", async () => {
    setCookie("i18nextLng", "hi");
    const response = await createSuccessfulInstance().get("/products", {
      headers: { "Accept-Language": "hi,en;q=0.8" },
    });

    expect(response.config.headers.get("X-Locale")).toBe("hi");
    expect(response.config.headers.get("Accept-Language")).toBe("hi,en;q=0.8");
  });

  it("uses the environment fallback for server requests without a locale", async () => {
    vi.stubGlobal("window", undefined);
    vi.stubEnv("NEXT_PUBLIC_DEFAULT_LOCALE", "ar");
    const response = await createSuccessfulInstance().get("/products");

    expect(response.config.headers.get("X-Locale")).toBe("ar");
  });

  it("keeps explicit server request locales isolated on a shared client", async () => {
    vi.stubGlobal("window", undefined);
    const instance = createSuccessfulInstance();
    const [arabic, hindi] = await Promise.all([
      instance.get("/products", { headers: { "X-Locale": "ar" } }),
      instance.get("/products", { headers: { "X-Locale": "hi" } }),
    ]);
    const fallback = await instance.get("/products");

    expect(arabic.config.headers.get("X-Locale")).toBe("ar");
    expect(hindi.config.headers.get("X-Locale")).toBe("hi");
    expect(fallback.config.headers.get("X-Locale")).toBe("en");
  });

  it.each([undefined, "", "  ", "invalid locale"])(
    "uses English if the environment fallback is unavailable or invalid: %j",
    async (value) => {
      vi.stubEnv("NEXT_PUBLIC_DEFAULT_LOCALE", value);
      const response = await createSuccessfulInstance().get("/products");

      expect(response.config.headers.get("X-Locale")).toBe("en");
    },
  );
});

describe("response interceptor", () => {
  it("reports a sanitized 500 summary without console.error and keeps the rejection", async () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const consoleError = vi
      .spyOn(console, "error")
      .mockImplementation(() => {});
    const error = createAxiosError(
      500,
      { trace: "private stack", token: "private-token" },
      "https://api.example.com/products?access_token=private-token",
    );
    const instance = createRejectingInstance(error);

    await expect(instance.get("/products")).rejects.toBe(error);

    expect(warn).toHaveBeenCalledWith("[API] request failed", {
      code: "ERR_BAD_RESPONSE",
      method: "GET",
      path: "/products",
      status: 500,
    });
    expect(consoleError).not.toHaveBeenCalled();
  });

  it("activates maintenance for a maintenance=true 503 response", async () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    const error = createAxiosError(
      503,
      { maintenance: true, message: "Back\u0000 shortly\n" },
      "/settings",
    );
    const instance = createRejectingInstance(error);

    await expect(instance.get("/settings")).rejects.toBe(error);

    expect(maintenanceStore.getState()).toEqual({
      isActive: true,
      message: "Back shortly",
    });
  });

  it("discards a malformed maintenance message", async () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    const error = createAxiosError(
      503,
      { maintenance: true, message: { debug: "private" } },
      "/settings",
    );
    const instance = createRejectingInstance(error);

    await expect(instance.get("/settings")).rejects.toBe(error);

    expect(maintenanceStore.getState()).toEqual({
      isActive: true,
      message: null,
    });
  });

  it("does not clear maintenance after an unrelated successful response", async () => {
    maintenanceStore.setMaintenance(true, "Back shortly");
    const instance = createSuccessfulInstance();

    await expect(instance.get("/products")).resolves.toMatchObject({
      status: 200,
    });

    expect(maintenanceStore.getState()).toEqual({
      isActive: true,
      message: "Back shortly",
    });
  });

  it("clears maintenance after a successful settings response", async () => {
    maintenanceStore.setMaintenance(true, "Back shortly");
    const instance = createSuccessfulInstance();

    await expect(instance.get("/settings?platform=web")).resolves.toMatchObject(
      { status: 200 },
    );

    expect(maintenanceStore.getState()).toEqual({
      isActive: false,
      message: null,
    });
  });

  it("discards an authenticated response after the active account changes", async () => {
    setCookie("access_token", "account-a");
    const instance = axios.create({
      adapter: async (config) => {
        setCookie("access_token", "account-b");
        return createResponse(config, 200, { private: "account-a" });
      },
    });
    setupInterceptors(instance);

    await expect(instance.get("/user")).rejects.toMatchObject({
      code: "ERR_STALE_SESSION",
    });
  });

  it("does not let an old account's delayed 401 sign out the active account", async () => {
    setCookie("access_token", "account-a");
    const instance = axios.create({
      adapter: async (config) => {
        setCookie("access_token", "account-b");
        throw new AxiosError(
          "Unauthorized",
          "ERR_BAD_REQUEST",
          config,
          undefined,
          createResponse(config, 401, { message: "Unauthenticated" }),
        );
      },
    });
    setupInterceptors(instance);

    await expect(instance.get("/user")).rejects.toMatchObject({
      response: { status: 401 },
    });
    expect(authMocks.handleUnauthorized).not.toHaveBeenCalled();
  });
});
