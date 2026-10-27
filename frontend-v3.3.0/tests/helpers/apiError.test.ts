import {
  AxiosError,
  AxiosHeaders,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from "axios";
import { afterEach, describe, expect, it, vi } from "vitest";
import {
  getApiFailureDetails,
  getSafeClientErrorResponse,
  reportApiFailure,
  withApiFailureMetadata,
} from "@/helpers/apiError";

type AxiosErrorOptions = {
  responseData?: unknown;
  status?: number;
  url?: string;
  withResponse?: boolean;
};

const createAxiosError = ({
  responseData = { message: "Private response body" },
  status = 422,
  url = "https://api.example.com/orders?access_token=private&email=user%40example.com",
  withResponse = true,
}: AxiosErrorOptions = {}): AxiosError => {
  const config: InternalAxiosRequestConfig = {
    method: "post",
    url,
    headers: new AxiosHeaders({ Authorization: "Bearer private-token" }),
    data: { cardNumber: "4111111111111111" },
  };
  const response: AxiosResponse = {
    config,
    data: responseData,
    headers: new AxiosHeaders({ "set-cookie": "session=private" }),
    status,
    statusText: "Unprocessable Content",
  };

  return new AxiosError(
    "Private transport error message",
    "ERR_BAD_RESPONSE",
    config,
    undefined,
    withResponse ? response : undefined,
  );
};

afterEach(() => {
  vi.restoreAllMocks();
});

describe("API failure reporting", () => {
  it("returns only allowlisted Axios metadata and strips query parameters", () => {
    const error = createAxiosError();

    expect(getApiFailureDetails(error)).toEqual({
      code: "ERR_BAD_RESPONSE",
      method: "POST",
      path: "/orders",
      status: 422,
    });
  });

  it("warns with a sanitized summary once for the same Axios error", () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const consoleError = vi
      .spyOn(console, "error")
      .mockImplementation(() => {});
    const error = createAxiosError();

    reportApiFailure(error, "creating order");
    reportApiFailure(error, "creating order");

    expect(warn).toHaveBeenCalledTimes(1);
    expect(warn).toHaveBeenCalledWith("[API] creating order failed", {
      code: "ERR_BAD_RESPONSE",
      method: "POST",
      path: "/orders",
      status: 422,
    });
    expect(consoleError).not.toHaveBeenCalled();
  });

  it("redacts customer-specific path segments", () => {
    const error = createAxiosError({
      url: "https://api.example.com/user/orders/ORD-123/recovery?token=private",
    });

    expect(getApiFailureDetails(error)?.path).toBe("/user/orders/:id/recovery");
  });

  it("keeps unexpected non-Axios errors visible through console.error", () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const consoleError = vi
      .spyOn(console, "error")
      .mockImplementation(() => {});
    const error = new Error("Unexpected parsing failure");

    reportApiFailure(error, "loading products");

    expect(consoleError).toHaveBeenCalledOnce();
    expect(consoleError).toHaveBeenCalledWith(
      "[API] Unexpected loading products failure:",
      error,
    );
    expect(warn).not.toHaveBeenCalled();
    expect(getApiFailureDetails(error)).toBeNull();
  });
});

describe("safe client error responses", () => {
  it.each([400, 409, 422])(
    "preserves and sanitizes a %i client-error envelope",
    (status) => {
      const error = createAxiosError({
        status,
        responseData: {
          success: false,
          message: "Please\u0000check your details.\n",
          data: {
            email: "Enter a valid email.\r",
            name: ["Name is required.\u0000", 123],
            "invalid field name!": "must be discarded",
          },
          errors: ["The request conflicts with existing data.\t", null],
          debug: "SQL and stack details must not reach the customer",
        },
      });

      expect(getSafeClientErrorResponse(error)).toEqual({
        success: false,
        message: "Please check your details.",
        data: {
          email: "Enter a valid email.",
          name: ["Name is required."],
        },
        errors: ["The request conflicts with existing data."],
      });
    },
  );

  it("refuses a raw server-error response", () => {
    const error = createAxiosError({
      status: 500,
      responseData: {
        success: false,
        message: "SQLSTATE connection failure",
        exception: "DatabaseException",
        trace: ["private stack frame"],
      },
    });

    expect(getSafeClientErrorResponse(error)).toBeNull();
  });

  it("refuses an Axios network error without a response", () => {
    const error = createAxiosError({ withResponse: false });

    expect(getSafeClientErrorResponse(error)).toBeNull();
  });

  it.each([
    null,
    "not an API envelope",
    {},
    { success: true, message: "This must not become an error response" },
    { success: "false", message: "Wrong success type" },
  ])("rejects a malformed failure envelope: %j", (responseData) => {
    const error = createAxiosError({ responseData });

    expect(getSafeClientErrorResponse(error)).toBeNull();
  });
});

describe("API retry metadata", () => {
  it("retries server/network failures but not permanent client failures", () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    const fallback = { success: false, message: "An error occurred." };

    expect(
      withApiFailureMetadata(createAxiosError({ status: 500 }), fallback),
    ).toMatchObject({ retryable: true });
    expect(
      withApiFailureMetadata(createAxiosError({ status: 422 }), fallback),
    ).toMatchObject({ retryable: false });
    expect(
      withApiFailureMetadata(
        createAxiosError({ withResponse: false }),
        fallback,
      ),
    ).toMatchObject({ retryable: true });
  });
});
