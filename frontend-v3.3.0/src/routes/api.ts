import axios from "axios";
import {
  normalizeSupportAttachment,
  SupportRequestError,
  supportFormData,
} from "@/helpers/support";
import type {
  StartSupportSession,
  SupportDraft,
  SupportHistory,
  SupportMessage,
  SupportRealtimeData,
  SupportSession,
  SupportThreadData,
  SupportTopic,
  SupportUpdates,
} from "@/types/support";
import {
  getApiFailureDetails,
  getSafeClientErrorResponse,
  reportApiFailure,
  sanitizeApiMessage,
  type SafeClientErrorResponse,
  withApiFailureMetadata,
} from "@/helpers/apiError";
import type {
  ApiBlogCategory,
  BlogDetailResponse,
  BlogListResponse,
} from "@/types/blog";
import { setupInterceptors } from "./interceptor";
import type { LanguageLabelsData, LanguagesData } from "@/types/localization";
import type { SharedCartData, SharedCartPreview, ImportSharedCartData, CartImportAction } from "@/types/sharedCart";
import {
  Address,
  ApiResponse,
  BannerData,
  Brand,
  CartResponse,
  CartSyncData,
  Category,
  CheckDeliveryZone,
  DeliveryLocationResponse,
  DeliveryZone,
  FAQ,
  FeaturedSection,
  KeywordSearch,
  Order,
  OrderCheckoutResponse,
  PaymentTransactionStatusResponse,
  PaginatedResponse,
  PaystackCreateOrderResponse,
  Product,
  ProductFaq,
  ProductReviews,
  PromoCode,
  RazorpayOrderData,
  SellerFeedbackItem,
  SellerReview,
  Settings,
  SidebarFilters,
  ScheduledDeliveryDatesData,
  ScheduledDeliverySlotsData,
  CartDeliveryDatesData,
  CartDeliverySlotsData,
  Store,
  Transaction,
  userData,
  VerifyUserData,
  WalletTransaction,
  Wishlist,
  WishTitle,
  VersionCheckData,
  ReferralInfo,
  HomeLayoutData,
  ReturnReason,
  LoyaltyPointsTransactionsData,
  XenditCreateOrderResponse,
} from "@/types/ApiResponse";
import {
  AddBalanceParams,
  AddressParams,
  DeductBalanceParams,
  PrepareWalletRechargeResponse,
  LoyaltyTransactionsParams,
  RegisterUserParams,
  UpdateUserParams,
  WalletTransactionParams,
} from "@/types/params";
import type {
  WatchAndBuyConfig,
  WatchAndBuyProfileReelsData,
  WatchAndBuyProfileStoriesData,
  WatchAndBuyReelFeedData,
  WatchAndBuyReelLikeParams,
  WatchAndBuyReelLikeResult,
  WatchAndBuyResponse,
  WatchAndBuyStorySeenParams,
  WatchAndBuyStorySeenResult,
  WatchAndBuyStoryTrayData,
} from "@/types/WatchAndBuy";
import {
  fallbackApiRes,
  fallbackBannerRes,
  fallbackPaginateRes,
  fallbackPaginateResOfProductReviews,
} from "@/config/constants";

// Helper function to construct API base URL properly
const constructApiBaseUrl = (baseUrl: string | undefined): string => {
  if (!baseUrl) {
    console.error(
      "NEXT_PUBLIC_ADMIN_PANEL_URL is not defined in environment variables.",
    );
    return "/api"; // Fallback to relative path to avoid crashing top-level module load
  }

  const trimmedUrl = baseUrl.trim();
  if (!trimmedUrl) {
    console.error("NEXT_PUBLIC_ADMIN_PANEL_URL is empty.");
    return "/api";
  }

  try {
    const url = new URL(trimmedUrl);
    // Preserve existing pathname and append /api
    const pathname = url.pathname.replace(/\/$/, ""); // Remove trailing slash if exists
    url.pathname = `${pathname}/api`;
    return url.toString();
  } catch (error) {
    console.error("Invalid NEXT_PUBLIC_ADMIN_PANEL_URL:", trimmedUrl);
    console.error("Error details:", error);
    // Return the original trimmed value or fallback to avoid crashing module load
    return trimmedUrl.endsWith("/api")
      ? trimmedUrl
      : `${trimmedUrl.replace(/\/$/, "")}/api`;
  }
};

const api = axios.create({
  baseURL: constructApiBaseUrl(process.env.NEXT_PUBLIC_ADMIN_PANEL_URL),
  timeout: 15000,
});

// Apply interceptors to the axios instance
setupInterceptors(api);

export const getWebLanguages = async (): Promise<ApiResponse<LanguagesData>> => {
  try {
    const response = await api.get<ApiResponse<LanguagesData>>("/languages", {
      params: { surface: "web" },
      headers: { Accept: "application/json" },
      timeout: 5000,
    });
    return response.data;
  } catch {
    return fallbackApiRes;
  }
};

export const getWebLanguageLabels = async (
  locale: string,
  signal?: AbortSignal,
): Promise<ApiResponse<LanguageLabelsData>> => {
  try {
    const response = await api.get<ApiResponse<LanguageLabelsData>>("/language-labels", {
      params: { surface: "web", locale },
      // A language switch fetches its new dictionary before updating the cookie.
      headers: { Accept: "application/json", "X-Locale": locale },
      signal,
      timeout: 5000,
    });
    return response.data;
  } catch {
    return fallbackApiRes;
  }
};

// Support uses the same authenticated client, but keeps failures actionable:
// a failed send must not be mistaken for a successfully saved message.
async function supportRequest<T>(
  request: () => Promise<{ data: ApiResponse<T> }>,
): Promise<T> {
  try {
    const { data } = await request();
    if (!data.success || data.data == null) {
      throw new SupportRequestError(
        sanitizeApiMessage(data.message) ||
          "Unable to complete support request.",
      );
    }
    return data.data;
  } catch (error) {
    if (error instanceof SupportRequestError) throw error;
    const safe = getSafeClientErrorResponse(error);
    const status = axios.isAxiosError(error)
      ? error.response?.status
      : undefined;
    const validationMessage =
      (status === 409 || status === 422) && axios.isAxiosError(error)
        ? sanitizeApiMessage(error.response?.data?.message)
        : undefined;
    const retryHeader = axios.isAxiosError(error)
      ? error.response?.headers?.["retry-after"]
      : undefined;
    const retryAfter =
      retryHeader == null
        ? 0
        : /^\d+$/.test(String(retryHeader))
          ? Number(retryHeader)
          : Math.max(
              0,
              Math.ceil((Date.parse(String(retryHeader)) - Date.now()) / 1000),
            );
    throw new SupportRequestError(
      safe?.message || validationMessage || "",
      status,
      Number.isFinite(retryAfter) ? retryAfter : 0,
    );
  }
}

export const getSupportThread = (signal?: AbortSignal) =>
  supportRequest<SupportThreadData>(() =>
    api.get("/user/support/thread", { signal }),
  );
export const getSupportRealtime = (signal?: AbortSignal) =>
  supportRequest<SupportRealtimeData>(() =>
    api.get("/user/support/realtime", { signal }),
  );
export async function authorizeSupportChannel(
  endpoint: string,
  socketId: string,
  channelName: string,
  signal?: AbortSignal,
): Promise<{ auth: string; channel_data?: string }> {
  const base = new URL(
    constructApiBaseUrl(process.env.NEXT_PUBLIC_ADMIN_PANEL_URL),
    window.location.href,
  );
  const url = new URL(endpoint, base);
  // The account token belongs to the configured backend, not an arbitrary
  // URL in a response or the separately hosted WebSocket server.
  if (url.origin !== base.origin || url.username || url.password)
    throw new Error("Invalid support authorization endpoint.");
  const body = new URLSearchParams({
    socket_id: socketId,
    channel_name: channelName,
  });
  // Axios supplies the form content type; never override Content-Length.
  const response = await api.post(url.href, body, {
    signal,
    headers: { Accept: "application/json" },
  });
  if (typeof response.data?.auth !== "string" || !response.data.auth)
    throw new Error("Invalid support authorization response.");
  return response.data;
}
export const getSupportTopics = (signal?: AbortSignal) =>
  supportRequest<SupportTopic[]>(() =>
    api.get("/user/support/topics", { signal }),
  );
export const getSupportUpdates = (
  after_message_id: number,
  after_call_id: number,
  signal?: AbortSignal,
) =>
  supportRequest<SupportUpdates>(() =>
    api.get("/user/support/thread/updates", {
      params: { after_message_id, after_call_id },
      signal,
    }),
  );
export const getSupportHistory = (
  before_message_id: number,
  signal?: AbortSignal,
) =>
  supportRequest<SupportHistory>(() =>
    api.get("/user/support/thread/messages", {
      params: { before_message_id, limit: 30 },
      signal,
    }),
  );
export const startSupportSession = (draft: StartSupportSession) =>
  supportRequest<SupportSession>(() =>
    api.post("/user/support/sessions", supportFormData(draft)),
  );
export const sendSupportMessage = (slug: string, draft: SupportDraft) =>
  supportRequest<SupportMessage>(() =>
    api.post(
      `/user/support/sessions/${encodeURIComponent(slug)}/messages`,
      supportFormData(draft),
    ),
  );

// These actions may legitimately return a null data payload.
async function supportAction(slug: string, action: string, body: object) {
  await supportRequest(() =>
    api
      .post(
        `/user/support/sessions/${encodeURIComponent(slug)}/${action}`,
        body,
      )
      .then((response) => ({
        data: { ...response.data, data: response.data.data ?? {} },
      })),
  );
}
export const setSupportTyping = (slug: string, typing: boolean) =>
  supportAction(slug, "typing", { typing });
export const markSupportRead = (slug: string, message_id: number) =>
  supportAction(slug, "read", { message_id });
export const requestSupportCallback = (
  slug: string,
  phone: string,
  scheduled_at?: string,
) =>
  supportAction(slug, "callbacks", {
    phone: phone.trim() || null,
    scheduled_at: scheduled_at || null,
  });
export const resolveSupportSession = (slug: string) =>
  supportAction(slug, "resolve", {});
export const rateSupportSession = (
  slug: string,
  score: number,
  feedback: string,
) =>
  supportAction(slug, "rating", { score, feedback: feedback.trim() || null });
export const getSupportAttachment = async (
  media: number,
  signal?: AbortSignal,
): Promise<Blob> => {
  // Use the configured backend and authenticated media endpoint; never forward
  // the bearer token to a URL supplied in an attachment response.
  const response = await api.get<Blob>(`/user/support/attachments/${media}`, {
    responseType: "blob",
    signal,
  });
  return normalizeSupportAttachment(response.data);
};

/* <----------------- API Function --------------------->*/

// ALL Settings
export const getSettings = async (
  params: { access_token?: string | null } = {},
): Promise<ApiResponse<Settings>> => {
  try {
    const response = await api.get<ApiResponse<Settings>>("/settings", {
      headers: params.access_token
        ? { Authorization: `Bearer ${params.access_token}` }
        : undefined,
    });

    return response.data;
  } catch (error: any) {
    reportApiFailure(error);
    // Check if it's a 503 maintenance mode response
    if (error?.response?.status === 503) {
      const responseData = error.response?.data;
      if (
        responseData &&
        typeof responseData === "object" &&
        (responseData as any).maintenance === true
      ) {
        // Return maintenance mode response
        return {
          success: false,
          message:
            sanitizeApiMessage((responseData as any).message) ||
            "Maintenance mode active",
          data: null,
        };
      }
    }
    return { success: false, message: "An error occurred.", data: null };
  }
};

export const getVersionCheck = async (
  params: {
    app: string;
    current_version: string;
    platform: string;
  } = {
    app: "web",
    current_version: process.env.NEXT_PUBLIC_APP_VERSION || "0",
    platform: "android",
  },
): Promise<ApiResponse<VersionCheckData>> => {
  try {
    const response = await api.get<ApiResponse<VersionCheckData>>(
      "/settings/check-version",
      {
        params,
      },
    );
    return response.data;
  } catch (error: any) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: null };
  }
};

// Banners
export const getBannerImages = async (params: {
  position?: "top" | "carousel" | "sidebar";
  scope_category_slug?: string;
  per_page?: string | number;
  page?: string | number;
  latitude?: string | number;
  longitude?: string | number;
}): Promise<PaginatedResponse<BannerData>> => {
  try {
    const response = await api.get("/banners", {
      params: params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackBannerRes;
  }
};

// User Interactions
export const verifyUser = async (params: {
  type: "email" | "mobile";
  value: string;
}): Promise<ApiResponse<VerifyUserData>> => {
  try {
    const response = await api.post("/verify-user", null, { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const registerUser = async (params: RegisterUserParams) => {
  try {
    const response = await api.post("/register", null, { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getReferralInfo = async (
  access_token?: string | null,
): Promise<ApiResponse<ReferralInfo>> => {
  try {
    const response = await api.get<ApiResponse<ReferralInfo>>(
      "/user/referral",
      {
        headers: access_token
          ? { Authorization: `Bearer ${access_token}` }
          : undefined,
      },
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

export const deleteUser = async () => {
  try {
    const response = await api.delete("/user/delete-account");
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const login = async (params: {
  email?: string;
  password: string;
  mobile?: string;
  fcm_token?: string | null;
  device_type?: "web";
}): Promise<ApiResponse<userData>> => {
  try {
    const response = await api.post("/login", null, { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

export const googleLogin = async (params: {
  idToken: string;
  fcm_token?: string | null;
  device_type?: string | null;
  friends_code?: string | null;
  country?: string | null;
  iso_2?: string | null;
}): Promise<ApiResponse<userData>> => {
  try {
    const response = await api.post("/auth/google/callback", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

export const appleLogin = async (params: {
  idToken: string;
  fcm_token?: string | null;
  device_type?: string | null;
  friends_code?: string | null;
  country?: string | null;
  iso_2?: string | null;
}): Promise<ApiResponse<userData>> => {
  try {
    const response = await api.post("/auth/apple/callback", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

export const phoneLogin = async (params: {
  idToken: string;
  name?: string | null;
  friends_code?: string | null;
  fcm_token?: string | null;
  device_type?: string | null;
  access_token?: string;
}): Promise<ApiResponse<userData>> => {
  try {
    const { access_token, ...body } = params;
    const config = access_token
      ? { headers: { Authorization: `Bearer ${access_token}` } }
      : {};
    const response = await api.post("/auth/phone/callback", body, config);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

// Custom SMS OTP (auth/send-otp)
export const sendOtp = async (params: {
  mobile: string;
  expires_in?: number;
}): Promise<ApiResponse<{ mobile: string; expires_in: number }>> => {
  try {
    const response = await api.post("/auth/send-otp", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Custom SMS OTP verification (auth/verify-otp)
export const verifyOtp = async (params: {
  mobile: string;
  otp: string;
  friends_code?: string | null;
}): Promise<ApiResponse<userData>> => {
  try {
    const response = await api.post("/auth/verify-otp", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const logout = async (
  access_token: string | null,
  params?: { fcm_id?: string; fcm_token?: string | null },
): Promise<ApiResponse<{}>> => {
  try {
    const response = await api.post(
      "/logout",
      params ?? {},
      access_token
        ? {
            headers: {
              Authorization: `Bearer ${access_token}`,
            },
          }
        : undefined,
    );

    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

export const forgotPassword = async (params: {
  email: string;
}): Promise<ApiResponse<null>> => {
  try {
    const response = await api.post("/forget-password", null, { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getUserData = async (
  params: { access_token?: string } = {},
): Promise<ApiResponse<userData>> => {
  try {
    const response = await api.get("/user/profile", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

export const updateUserData = async (params: UpdateUserParams | FormData) => {
  try {
    // Pass params to the request
    const response = await api.post<ApiResponse<userData>>(
      "/user/profile",
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const updateEmail = async (email: string) => {
  try {
    const response = await api.post<ApiResponse<userData>>(
      "/user/update-email",
      {
        email,
      },
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const resendVerificationEmail = async () => {
  try {
    const response = await api.post<ApiResponse<any>>(
      "/user/email/verification-notification",
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

//categories
export const getCategories = async (
  params: {
    page?: string | number;
    per_page?: string | number;
    slug?: string;
    latitude?: string | number;
    longitude?: string | number;
  } = {},
): Promise<PaginatedResponse<Category[]>> => {
  try {
    const response = await api.get("/categories", { params });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackPaginateRes);
  }
};

export const getSubCategories = async (
  params: {
    page?: string | number;
    per_page?: string | number;
    slug?: string;
    latitude?: string | number;
    longitude?: string | number;
    filter?: "random" | "top_category";
  } = {},
): Promise<PaginatedResponse<Category[]>> => {
  try {
    const response = await api.get("/categories/sub-categories", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

// Address Interactions
export const getAddresses = async (
  params: {
    access_token?: string;
    page?: number;
    per_page?: number;
    latitude?: string | number;
    longitude?: string | number;
    zone_id?: string | number;
  } = {},
): Promise<PaginatedResponse<Address[]>> => {
  try {
    const response = await api.get("/user/addresses", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

export const addAddress = async (params: AddressParams) => {
  try {
    // Pass params to the request
    const response = await api.post<ApiResponse<Address>>(
      "/user/addresses",
      params,
    );
    return response.data;
  } catch (error: any) {
    reportApiFailure(error);
    return getSafeClientErrorResponse(error) || fallbackApiRes;
  }
};

export const editAddress = async (params: AddressParams) => {
  try {
    // Pass params to the request
    const response = await api.put<ApiResponse<Address>>(
      `/user/addresses/${params.id}`,
      params,
    );
    return response.data;
  } catch (error: any) {
    reportApiFailure(error);
    return getSafeClientErrorResponse(error) || fallbackApiRes;
  }
};

export const deleteAddress = async (params: { id: string | number }) => {
  try {
    // Pass params to the request
    const response = await api.delete<ApiResponse<Address>>(
      `/user/addresses/${params.id}`,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

//wallet

export const prepareWalletRecharge = async (params: AddBalanceParams) => {
  try {
    // Pass params to the request
    const response = await api.post<ApiResponse<PrepareWalletRechargeResponse>>(
      "/user/wallet/prepare-wallet-recharge",
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { ...fallbackApiRes, message: getSafeClientErrorResponse(error)?.message || fallbackApiRes.message };
  }
};

export const addBalance = async (params: AddBalanceParams) => {
  try {
    // Pass params to the request
    const response = await api.post<ApiResponse<object>>(
      "/user/wallet/add-balance",
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const confirmWalletRecharge = async (params: {
  razorpay_order_id: string;
  razorpay_payment_id?: string;
  razorpay_signature?: string;
}) => {
  try {
    const response = await api.post<ApiResponse<object>>(
      "/user/wallet/confirm-recharge",
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const deductBalance = async (params: DeductBalanceParams) => {
  try {
    // Pass params to the request
    const response = await api.post<ApiResponse<object>>(
      "/user/wallet/deduct-balance",
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getTransactions = async (
  params: {
    payment_status?: string;
    limit?: string;
    type?: string;
    page?: string | number;
    per_page?: string | number;
    access_token?: string | null;
    search?: string;
    sort?: string;
    date_from?: string;
    date_to?: string;
  } = {},
): Promise<PaginatedResponse<Transaction[]>> => {
  try {
    const { access_token, ...queryParams } = params;
    const response = await api.get("/user/order-transactions", {
      headers: access_token
        ? { Authorization: `Bearer ${access_token}` }
        : undefined,
      params: queryParams,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

export const getWalletTransactions = async (
  params: WalletTransactionParams,
): Promise<PaginatedResponse<WalletTransaction[]>> => {
  try {
    const response = await api.get("/user/wallet/transactions", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

//loyalty points

// The transactions endpoint is the single source for loyalty data: it returns
// the summary fields (points_balance, redemption_value, …) alongside the
// paginated transactions, so no separate summary call is needed.
export const getLoyaltyPointsTransactions = async (
  params: LoyaltyTransactionsParams = {},
): Promise<ApiResponse<LoyaltyPointsTransactionsData>> => {
  try {
    const response = await api.get("/user/loyalty-points/transactions", {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return {
      success: false,
      message: "",
      data: {
        is_enabled: true,
        points_balance: 0,
        redemption_value: 0,
        equivalent_monetary_value: 0,
        expiring_points_in_30_days: 0,
        transactions: [],
        pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      },
    };
  }
};

export const getNotifications = async (
  params: {
    page?: number;
    per_page?: number;
    access_token?: string | null;
  } = {},
): Promise<ApiResponse<any>> => {
  try {
    const { access_token, ...queryParams } = params;
    const response = await api.get("/user/notifications", {
      headers: access_token
        ? { Authorization: `Bearer ${access_token}` }
        : undefined,
      params: queryParams,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const markNotificationRead = async (
  id: string,
): Promise<ApiResponse<any>> => {
  try {
    const response = await api.post(`/user/notifications/${id}/read`, { id });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const markAllNotificationsRead = async (): Promise<ApiResponse<any>> => {
  try {
    const response = await api.post("/user/notifications/mark-all-read");
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Brands
export const getBrands = async (
  params: {
    page?: string | number;
    per_page?: string | number;
    scope_category_slug?: string;
    latitude?: string | number;
    longitude?: string | number;
  } = {},
): Promise<PaginatedResponse<Brand[]>> => {
  try {
    const response = await api.get("/brands", { params });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackPaginateRes);
  }
};

// Stores
export const getStores = async (
  params: {
    latitude?: string | number;
    longitude?: string | number;
    page?: string | number;
    per_page?: string | number;
    search?: string;
  } = {},
): Promise<PaginatedResponse<Store[]>> => {
  try {
    const response = await api.get("/delivery-zone/stores", { params });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackPaginateRes);
  }
};

export const getSpecificStore = async (
  slug: string,
): Promise<ApiResponse<Store>> => {
  try {
    const response = await api.get(`/stores/${slug}`);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const notifyStoreOpening = async (
  storeId: number,
  isNotify: boolean,
): Promise<ApiResponse<{ is_notify: boolean }>> => {
  try {
    const response = await api.post(`/stores/${storeId}/notify-me`, {
      is_notify: isNotify,
    });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackApiRes);
  }
};

// Scheduled delivery

/**
 * The store's scheduled-delivery config: opening days, booking window, lead
 * time. `store` is the NUMERIC store id — the slug 404s here, unlike /stores.
 */
export const getScheduledDeliveryDates = async (
  store: string | number,
): Promise<ApiResponse<ScheduledDeliveryDatesData>> => {
  try {
    const response = await api.get(`/scheduled-delivery/stores/${store}/dates`);
    return response.data;
  } catch (error) {
    /**
     * A 422 here is the backend's way of saying "this store doesn't do
     * scheduled delivery" — an expected answer for plenty of stores, not a
     * fault. Logging it as an error filled the console on every cart view, so
     * only genuine failures are reported. Callers read `success` either way.
     */
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      return fallbackApiRes;
    }
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

/**
 * Bookable slots for one date, WITH live availability — this is the only
 * source of `is_available` / `remaining_capacity`, so the picker must call it
 * per date rather than reusing the store record's static `slots[]`.
 *
 * `date` is a local YYYY-MM-DD. The backend rejects a past date outright
 * (`success: false`) and answers an out-of-window date with `success: true`
 * plus an empty `slots` array and an explanatory `message`.
 */
export const getScheduledDeliverySlots = async (
  store: string | number,
  date: string,
): Promise<ApiResponse<ScheduledDeliverySlotsData>> => {
  try {
    const response = await api.get(
      `/scheduled-delivery/stores/${store}/slots`,
      { params: { date } },
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

/* --- Cart-scoped scheduling (newer contract; see helpers/scheduling.ts) --- */

/**
 * Bookable dates for the CURRENT cart — the backend resolves the store from the
 * cart, so no id is passed. Unlike the per-store `/dates`, this returns the days
 * themselves rather than rules to expand.
 *
 * A 404 means this contract isn't deployed; it's reported as an unsuccessful
 * response rather than thrown so callers can fall back to the store contract.
 */
export const getCartDeliveryDates = async (): Promise<
  ApiResponse<CartDeliveryDatesData>
> => {
  try {
    const response = await api.get("/cart/dates");
    return response.data;
  } catch (error) {
    // 404 = contract absent, 422 = store doesn't schedule. Both are expected
    // answers rather than faults, and logging them would spam every cart view.
    if (
      axios.isAxiosError(error) &&
      (error.response?.status === 404 || error.response?.status === 422)
    ) {
      return fallbackApiRes;
    }
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

/**
 * Slots for one date on the current cart, with `remaining_capacity`.
 * `date` is a local YYYY-MM-DD.
 */
export const getCartDeliverySlots = async (
  date: string,
): Promise<ApiResponse<CartDeliverySlotsData>> => {
  try {
    const response = await api.get("/cart/slots", { params: { date } });
    return response.data;
  } catch (error) {
    if (
      axios.isAxiosError(error) &&
      (error.response?.status === 404 || error.response?.status === 422)
    ) {
      return fallbackApiRes;
    }
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

/* --- Order recovery (flagged reschedule / cancellation) --- */

/**
 * The recovery slots endpoint takes its date as DD-MM-YYYY (per the API docs'
 * example, `date=12-07-1999`) while the app speaks YYYY-MM-DD everywhere.
 * Converted at the boundary so no caller has to know.
 */
const toRecoveryDateParam = (date: string): string => {
  const [y, m, d] = date.split("-");
  return `${d}-${m}-${y}`;
};

/**
 * Slots the flagged order can move into on one date. Order-scoped rather than
 * store-scoped: the backend resolves the store (and excludes the order's own
 * dead slot) itself. `date` is a local YYYY-MM-DD and is required.
 */
export const getOrderRecoverySlots = async (
  orderId: string | number,
  date: string,
): Promise<ApiResponse<CartDeliverySlotsData>> => {
  try {
    const response = await api.get(`/user/orders/${orderId}/recovery/slots`, {
      params: { date: toRecoveryDateParam(date) },
    });
    return response.data;
  } catch (error) {
    if (
      axios.isAxiosError(error) &&
      (error.response?.status === 404 || error.response?.status === 422)
    ) {
      return fallbackApiRes;
    }
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

/**
 * Move a flagged order to a new window. Rejected by the backend once
 * `reschedule_deadline` has passed, so the UI must not be the only guard.
 *
 * Unlike the getters above, a failure here is surfaced with the backend's own
 * message: the customer pressed a button and is owed the reason it didn't work.
 */
export const rescheduleOrder = async (
  orderId: string | number,
  params: {
    scheduled_date: string;
    scheduled_slot_start_time?: string;
    scheduled_slot_end_time?: string;
  },
): Promise<ApiResponse<null> | SafeClientErrorResponse> => {
  try {
    const response = await api.post(
      `/user/orders/${orderId}/recovery/reschedule`,
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return getSafeClientErrorResponse(error) || fallbackApiRes;
  }
};

/** Cancel a flagged order. Triggers a refund for non-COD payments. */
export const cancelFlaggedOrder = async (
  orderId: string | number,
): Promise<ApiResponse<null> | SafeClientErrorResponse> => {
  try {
    const response = await api.post(`/user/orders/${orderId}/recovery/cancel`);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return getSafeClientErrorResponse(error) || fallbackApiRes;
  }
};

export const getStoresByMap = async (params: {
  ne_lat: number;
  ne_lng: number;
  sw_lat: number;
  sw_lng: number;
}): Promise<ApiResponse<{ count: number; stores: Store[] }>> => {
  try {
    const response = await api.post("/stores/map", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Delivery Zone
export const checkDeliveryZone = async (params: {
  latitude: string | number;
  longitude: string | number;
}): Promise<ApiResponse<CheckDeliveryZone>> => {
  try {
    const response = await api.get("/delivery-zone/check", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { success: false, message: "An error occurred.", data: undefined };
  }
};

export const getDeliveryZones = async (
  params: {
    page?: string | number;
    per_page?: string | number;
    search?: number | string;
  } = {},
): Promise<PaginatedResponse<DeliveryZone[]>> => {
  try {
    const response = await api.get("/delivery-zone", { params });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackPaginateRes);
  }
};

export const getDeliveryZoneBySlug = async (
  params: {
    slug?: string;
  } = {},
): Promise<ApiResponse<DeliveryZone>> => {
  try {
    const { slug = "" } = params;
    const response = await api.get(`/delivery-zone/${slug}`, {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Blogs
export const getBlogCategories = async (
  params: { access_token?: string | null } = {},
): Promise<ApiResponse<ApiBlogCategory[]>> => {
  try {
    const response = await api.get<ApiResponse<ApiBlogCategory[]>>(
      "/blog-categories",
      { params, headers: { Accept: "application/json" } },
    );
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, { ...fallbackApiRes, data: [] });
  }
};

export const getBlogs = async (
  params: {
    page?: string | number;
    per_page?: string | number;
    search?: string;
    category_id?: string | number;
    category_slug?: string;
    tag?: string;
    is_editors_pick?: boolean;
    access_token?: string | null;
  } = {},
): Promise<BlogListResponse> => {
  try {
    const response = await api.get<BlogListResponse>("/blogs", {
      params,
      headers: { Accept: "application/json" },
    });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, {
      ...fallbackPaginateRes,
      data: { ...fallbackPaginateRes.data, last_page: 1 },
    });
  }
};

export const getBlogEditorsPick = (): Promise<BlogListResponse> =>
  getBlogs({ page: 1, per_page: 6 });

export const getBlogBySlug = async (params: {
  slug: string;
  access_token?: string | null;
  latitude?: number | string;
  longitude?: number | string;
}): Promise<BlogDetailResponse> => {
  try {
    const { slug, ...rest } = params;
    const response = await api.get<BlogDetailResponse>(
      `/blogs/${encodeURIComponent(slug)}`,
      { params: rest, headers: { Accept: "application/json" } },
    );
    return response.data;
  } catch (error) {
    return {
      ...withApiFailureMetadata(error, fallbackApiRes),
      notFound: getApiFailureDetails(error)?.status === 404,
    };
  }
};

//Products
export const getProducts = async (
  params: {
    page?: string | number;
    slug?: string;
    per_page?: string | number;
    exclude_product?: string;
    latitude?: number | string;
    longitude?: number | string;
    access_token?: string | undefined;
    categories?: string;
    brands?: string;
    search?: string;
    store?: string;
    include_child_categories?: number;
    attribute_values?: string;
  } = {},
): Promise<PaginatedResponse<Product[], { keywords: string[] }>> => {
  try {
    const response = await api.get("/delivery-zone/products", {
      params,
    });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, {
      ...fallbackPaginateRes,
      data: {
        ...fallbackPaginateRes.data,
        keywords: [],
      },
    }) as PaginatedResponse<Product[], { keywords: string[] }>;
  }
};

export const getSidebarFilters = async (params: {
  latitude: string | number;
  longitude: string | number;
  attribute_values?: string;
  categories?: string;
  brands?: string;
  type?: string;
  value?: string;
  access_token?: string;
}): Promise<ApiResponse<SidebarFilters>> => {
  try {
    const { access_token, ...rest } = params;
    const response = await api.get<ApiResponse<SidebarFilters>>(
      "/products/sidebar-filters",
      {
        params: rest,
        headers: access_token
          ? { Authorization: `Bearer ${access_token}` }
          : undefined,
      },
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getProductBySlug = async (
  params: {
    slug?: string;
    store_id?: number;
    latitude?: number | string;
    longitude?: number | string;
    is_notify?: number;
    variant_id?: number | string;
    access_token?: string | undefined;
  } = {},
): Promise<ApiResponse<Product>> => {
  try {
    const { slug, ...rest } = params;
    const response = await api.get(`/products/${slug}`, {
      params: rest,
    });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackApiRes);
  }
};

export const getProductsByKeyword = async (
  params: {
    keywords?: string;
    latitude?: number | string;
    longitude?: number | string;
    per_page?: string | number;
  } = {},
): Promise<ApiResponse<KeywordSearch>> => {
  try {
    const response = await api.get(`/products/search-by-keywords`, {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getProductReviews = async (params: {
  page: string | number;
  per_page: string | number;
  access_token?: string | null;
  slug?: string;
}): Promise<PaginatedResponse<ProductReviews>> => {
  try {
    const { slug } = params;
    const response = await api.get(`/products/${slug}/reviews`, {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateResOfProductReviews;
  }
};

export const getProductFAQs = async (params: {
  page: string | number;
  per_page: string | number;
  access_token?: string | null;
  slug?: string;
  search?: string;
}): Promise<PaginatedResponse<ProductFaq[]>> => {
  try {
    const { slug } = params;
    const response = await api.get(`/products/${slug}/faqs`, {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

// Product Reviews
export const giveProductReview = async (
  params: {
    product_id?: string | number;
    order_item_id?: string | number;
    rating?: number;
    title?: string;
    comment?: string;
    images?: File[];
  } = {},
): Promise<ApiResponse<object>> => {
  try {
    const formData = new FormData();
    if (params.product_id)
      formData.append("product_id", params.product_id.toString());
    if (params.order_item_id)
      formData.append("order_item_id", params.order_item_id.toString());
    if (params.rating !== undefined)
      formData.append("rating", params.rating.toString());
    if (params.title) formData.append("title", params.title);
    if (params.comment) formData.append("comment", params.comment);

    if (params.images)
      params.images.forEach((file) => formData.append("review_images[]", file));

    const response = await api.post("/reviews", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const updateProductReview = async (
  params: {
    id?: string | number;
    rating?: number;
    title?: string;
    comment?: string;
    images?: File[];
  } = {},
): Promise<ApiResponse<object>> => {
  try {
    let response;

    if (params.images && params.images.length > 0) {
      // Use FormData when uploading images
      const formData = new FormData();

      if (params.id) formData.append("id", params.id.toString());
      if (params.rating !== undefined)
        formData.append("rating", params.rating.toString());
      if (params.title) formData.append("title", params.title);
      if (params.comment) formData.append("comment", params.comment);

      params.images.forEach((file) => {
        formData.append("review_images[]", file);
      });

      response = await api.post(`/reviews/${params.id}`, formData, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });
    } else {
      // Send as JSON when no images
      response = await api.post(`/reviews/${params.id}`, params);
    }

    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const giveOrderItemSellerReview = async (
  params: {
    seller_id?: string | number;
    order_id?: number;
    order_item_id?: string | number;
    rating?: string | number;
    title?: string;
    description?: string;
  } = {},
): Promise<ApiResponse<SellerFeedbackItem>> => {
  try {
    const response = await api.post("/seller-feedback", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const updateOrderItemSellerReview = async (
  params: {
    id?: number | string;
    rating?: string | number;
    title?: string;
    description?: string;
  } = {},
): Promise<ApiResponse<SellerFeedbackItem>> => {
  try {
    const response = await api.post(`/seller-feedback/${params.id}`, params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

//Sections
export const getSections = async (
  params: {
    latitude?: string | number;
    longitude?: string | number;
    page?: string | number;
    per_page?: string | number;
    products_limit?: string | number;
    section_type?: string;
    access_token?: string | undefined;
    scope_category_slug?: string;
  } = {},
): Promise<PaginatedResponse<FeaturedSection[]>> => {
  try {
    const response = await api.get("/featured-sections", { params });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackPaginateRes);
  }
};

export const getSectionBySlug = async (
  params: {
    page?: string | number;
    slug?: string;
    per_page?: string | number;
    latitude?: number | string;
    longitude?: number | string;
    access_token?: string | undefined;
    categories?: string;
    brands?: string;
    colors?: string;
    sort?: string;
    search?: string;
    attribute_values?: string;
  } = {},
): Promise<PaginatedResponse<Product[]>> => {
  try {
    const { slug = "" } = params;
    const response = await api.get(`/featured-sections/${slug}/products`, {
      params,
    });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackPaginateRes);
  }
};

// Home Layout (new unified homepage). GET /home-layout resolves the layout for the
// current delivery zone. Non-standard envelope: array at data.sections (not data.data),
// so it uses ApiResponse<HomeLayoutData> and callers must guard `data?.sections ?? []`.
export const getHomeLayout = async (
  params: {
    platform?: string;
    category_slug?: string;
    page?: string | number;
    per_page?: string | number;
    latitude?: string | number;
    longitude?: string | number;
    access_token?: string | null;
  } = {},
): Promise<ApiResponse<HomeLayoutData>> => {
  try {
    const response = await api.get("/home-layout", {
      params: { platform: "web", ...params },
    });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackApiRes);
  }
};

// Paginated content for a single home-layout section ("view all" / load more).
// Standard paginated feed, so it reuses PaginatedResponse / fallbackPaginateRes.
// Items are the raw home-layout product shape — run them through adaptHomeLayoutProduct.
export const getHomeLayoutSection = async (
  sectionId: string | number,
  params: {
    page?: string | number;
    per_page?: string | number;
    latitude?: string | number;
    longitude?: string | number;
    access_token?: string | null;
  } = {},
): Promise<PaginatedResponse<Product[]>> => {
  try {
    const response = await api.get(`/home-layout/sections/${sectionId}`, {
      params,
    });
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackPaginateRes);
  }
};

// Cart Management
export const getSharedCart = async (shareCode: string, params: { latitude?: number; longitude?: number } = {}): Promise<ApiResponse<SharedCartPreview> & { status?: number }> => {
  try {
    const response = await api.get(`/user/cart/shared/${encodeURIComponent(shareCode)}`, { params });
    return response.data;
  } catch (error) {
    return { ...withApiFailureMetadata(error, fallbackApiRes), status: getApiFailureDetails(error)?.status };
  }
};

export const shareCart = async (params: { title: string }): Promise<ApiResponse<SharedCartData>> => {
  try {
    const response = await api.post("/user/cart/share", params);
    return response.data;
  } catch (error) {
    return withApiFailureMetadata(error, fallbackApiRes);
  }
};

export const importSharedCart = async (shareCode: string, action: CartImportAction = "merge"): Promise<ApiResponse<ImportSharedCartData> & { status?: number }> => {
  try {
    const response = await api.post(`/user/cart/shared/${encodeURIComponent(shareCode)}/import`, { action });
    return response.data;
  } catch (error) {
    return { ...withApiFailureMetadata(error, fallbackApiRes), status: getApiFailureDetails(error)?.status };
  }
};

export const addToCart = async (params: {
  product_variant_id: string | number;
  store_id: string | number;
  quantity: string | number;
  replace_quantity?: boolean;
  addons?: { addon_group_id: number; addon_item_id: number }[];
  is_notify?: number | boolean;
}): Promise<ApiResponse<CartResponse>> => {
  try {
    const response = await api.post("/user/cart/add", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getCart = async (params: {
  address_id?: string | number;
  promo_code?: string;
  rush_delivery?: boolean;
  use_wallet?: boolean;
  use_loyalty_points?: boolean;
  latitude: number | string;
  longitude: number | string;
}): Promise<ApiResponse<CartResponse>> => {
  try {
    const response = await api.get("/user/cart", {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getSaveForLaterItems = async (): Promise<
  ApiResponse<CartResponse>
> => {
  try {
    const response = await api.get("/user/cart/item/save-for-later");
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const saveCartItemToSaveForLater = async (
  cartItemId: string | number,
  quantity: string | number,
): Promise<ApiResponse<{}>> => {
  try {
    const response = await api.post(
      `/user/cart/item/save-for-later/${cartItemId}`,
      { quantity },
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const removeItemFromCart = async (
  cartItemId: string | number,
): Promise<ApiResponse<[]>> => {
  try {
    const response = await api.delete(`/user/cart/item/${cartItemId}`);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const updateCartItem = async (params: {
  cartItemId: string | number;
  quantity?: string | number;
  addons?: { addon_group_id: number; addon_item_id: number }[];
}): Promise<ApiResponse<[]>> => {
  try {
    const { cartItemId, ...rest } = params;
    const response = await api.post(`/user/cart/item/${cartItemId}`, rest);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const syncOfflineCart = async (params: {
  items: {
    store_id: number;
    product_variant_id: number;
    quantity: number;
  }[];
}): Promise<ApiResponse<CartSyncData>> => {
  try {
    const response = await api.post("/user/cart/sync", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const clearCart = async (): Promise<ApiResponse<null>> => {
  try {
    const response = await api.get("/user/cart/clear-cart");
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

/** Replaces the cart's rider tip; `tip_amount: 0` removes it. */
export const setCartTip = async (params: {
  cart_id: number | string;
  tip_amount: number;
}): Promise<ApiResponse<{ cart_id: number; rider_tip_amount: number }>> => {
  try {
    const response = await api.post("/user/cart/tip", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

/**
 * ADDS to the order's existing tip (the backend accumulates, it does not
 * replace) and needs its own `payment_method` since the order is already
 * paid. Identified by order slug/uuid — the numeric id 404s.
 *
 * For gateway methods the payment is collected client-side FIRST (see
 * `collectRazorpayPayment`) and its ids are forwarded here so the backend
 * can tie the tip to the transaction.
 */
export const addOrderTip = async (params: {
  orderSlug: string;
  rider_tip_amount: number;
  payment_method: string;
  razorpay_order_id?: string;
  transaction_id?: string;
  razorpay_signature?: string;
  reference?: string;
}): Promise<ApiResponse<Order>> => {
  try {
    const { orderSlug, ...body } = params;
    const response = await api.post(`/user/orders/${orderSlug}/tip`, body);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

//Promo code
export const getPromoCodes = async (): Promise<ApiResponse<PromoCode[]>> => {
  try {
    const response = await api.get("/user/promos/available");
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const validatePromoCode = async (
  params: {
    cart_amount?: string | number;
    promo_code?: string;
    delivery_charge?: string | number;
  } = {},
): Promise<ApiResponse<{ promo_code: string; discount: string }>> => {
  try {
    const response = await api.get("/user/promos/validate", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Orders
export const getOrders = async (
  params: {
    per_page?: string | number;
    page?: string | number;
    access_token?: string | null;
    date_range?: string;
    status?: string;
    order_type?: string;
    delivery_type?: string;
  } = {},
): Promise<PaginatedResponse<Order[]>> => {
  try {
    const { access_token = "" } = params;
    const response = await api.get("/user/orders", {
      headers: access_token
        ? { Authorization: `Bearer ${access_token}` }
        : undefined,
      params: params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

export const reorderOrder = async (
  orderId: string | number,
): Promise<ApiResponse<any>> => {
  try {
    const response = await api.post(`/user/orders/${orderId}/reorder`);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};
export const cancelOrderItem = async (
  params: {
    orderItemId?: string;
  } = {},
): Promise<ApiResponse<[]>> => {
  try {
    const response = await api.post(
      `/user/orders/items/${params.orderItemId}/cancel`,
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const returnOrderItem = async (
  params: {
    orderItemId?: string;
    reason?: string;
    /** Id of the chosen option from `getReturnReasons`, when one was picked. */
    reasonId?: string;
    images?: File[];
  } = {},
): Promise<ApiResponse<[]>> => {
  try {
    const formData = new FormData();

    // Do NOT send orderItemId in the body
    if (params.reason) {
      formData.append("reason", params.reason);
    }

    if (params.reasonId) {
      formData.append("return_reason_id", params.reasonId);
    }

    if (params?.images && params.images.length > 0) {
      params.images.forEach((file) => {
        formData.append("images[]", file);
      });
    }

    const response = await api.post(
      `/user/orders/items/${params.orderItemId}/return`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      },
    );

    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getReturnReasons = async (
  params: {
    orderItemId?: string;
    access_token?: string | null;
  } = {},
): Promise<ApiResponse<ReturnReason[]>> => {
  try {
    const { orderItemId, ...rest } = params;
    const response = await api.get(
      `/user/orders/items/${orderItemId}/return-reasons`,
      { params: rest },
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const cancelReturnReq = async (
  params: {
    orderItemId?: string;
  } = {},
): Promise<ApiResponse<[]>> => {
  try {
    const { orderItemId } = params;
    const response = await api.post(
      `/user/orders/items/${orderItemId}/return-cancel`,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getSpecificOrders = async (
  params: { slug?: string; access_token?: string | null } = {},
): Promise<ApiResponse<Order>> => {
  try {
    const { slug = "", access_token = "" } = params;
    const response = await api.get(`/user/orders/${slug}`, {
      headers: access_token
        ? { Authorization: `Bearer ${access_token}` }
        : undefined,
      params: params,
    });

    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const createOrder = async (
  params:
    | {
        payment_type?: string;
        amount?: string | number;
        promo_code?: string;
        promo_discount?: string;
        gift_card?: string;
        gift_card_discount?: string;
        rush_delivery?: boolean | string | number;
        use_wallet?: boolean | string | number;
        use_loyalty_points?: boolean | string | number;
        address_id?: string | number;
        order_note?: string;
        /**
         * Booked delivery window, from
         * `/scheduled-delivery/stores/{id}/slots`. Mutually exclusive with
         * `rush_delivery` — the two contradict each other.
         */
        delivery_time_slot_id?: number | string;
        /**
         * The cart contract's slot identity, from `/cart/slots`. Mutually
         * exclusive with `delivery_time_slot_id` — that contract's slots carry
         * no id, so a window is booked by date + these two times.
         */
        scheduled_date?: string;
        scheduled_slot_start_time?: string;
        scheduled_slot_end_time?: string;
        transaction_id?: string;
        razorpay_order_id?: string;
        razorpay_signature?: string;
        redirect_url?: string;
      }
    | FormData = {},
): Promise<ApiResponse<OrderCheckoutResponse> | SafeClientErrorResponse> => {
  try {
    const isFormData = params instanceof FormData;
    const response = await api.post("/user/orders", params, {
      headers: isFormData
        ? {
            "Content-Type": "multipart/form-data",
          }
        : undefined,
    });
    return response.data;
  } catch (error: any) {
    reportApiFailure(error);
    return getSafeClientErrorResponse(error) || fallbackApiRes;
  }
};

export const checkPaymentTransactionStatus = async (
  transactionUuid: string,
): Promise<ApiResponse<PaymentTransactionStatusResponse>> => {
  try {
    const response = await api.get(
      `/user/payment-transactions/${encodeURIComponent(transactionUuid)}/status`,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const getDeliveryBoyLocation = async (
  orderSlug: string,
): Promise<ApiResponse<DeliveryLocationResponse>> => {
  try {
    const response = await api.get(
      `/user/orders/${orderSlug}/delivery-boy-location`,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// WishList Management
// get WishList with their Items
export const getWishListWithItems = async (
  params: {
    page?: string | number;
    per_page?: string | number;
    access_token?: string | null;
  } = {},
): Promise<PaginatedResponse<Wishlist[]>> => {
  try {
    const response = await api.get("/user/wishlists", {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

// Create a new wishlist or add item to the existing / new wishlist
export const CreateWishListWithItems = async (
  params: {
    wishlist_title?: null | string;
    product_id?: null | number;
    product_variant_id?: null | number;
    store_id?: null | number;
  } = {},
): Promise<ApiResponse<object>> => {
  try {
    const response = await api.post("/user/wishlists", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const CreateWishListWithOutItems = async (
  params: {
    title?: null | string;
  } = {},
): Promise<ApiResponse<object>> => {
  try {
    const response = await api.post("/user/wishlists/create", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// get all wishlist titles
export const getAllWishlistTitles = async (
  params: {
    access_token?: string | null;
  } = {},
): Promise<ApiResponse<WishTitle>> => {
  try {
    const response = await api.get("/user/wishlists/titles", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// getSpecificWishlist
export const getWishlistById = async (
  id: string,
): Promise<ApiResponse<Wishlist>> => {
  try {
    const response = await api.get(`/user/wishlists/${id}`);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Update a wishlist
export const UpdateWishlistById = async (
  params: {
    id?: null | number;
    title?: string | null;
  } = {},
): Promise<ApiResponse<object>> => {
  const { id = "" } = params;

  try {
    const response = await api.put(`/user/wishlists/${id}`, params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// delete wishlist
export const deleteWishlistById = async (
  id: string,
): Promise<ApiResponse<object>> => {
  try {
    const response = await api.delete(`/user/wishlists/${id}`);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Remove item from wishlist
export const deleteWishlistItemById = async (
  itemId: string | number,
): Promise<ApiResponse<object>> => {
  try {
    const response = await api.delete(`/user/wishlists/items/${itemId}`);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// Move item to another wishlist
export const moveItemFromAnotherWishList = async (
  params: {
    itemId?: null | number;
    target_wishlist_id?: string | number;
  } = {},
): Promise<ApiResponse<object>> => {
  const { itemId = "" } = params;

  try {
    const response = await api.put(
      `/user/wishlists/items/${itemId}/move`,
      params,
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// FAQs
export const getFaqs = async (
  params: {
    page?: string | number;
    per_page?: string | number;
    search?: string;
  } = {},
): Promise<PaginatedResponse<FAQ[]>> => {
  try {
    const response = await api.get("/faqs", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

//Delivery Boy Review
export const giveDeliveryBoyReview = async (
  params: {
    delivery_boy_id?: string | number;
    order_id?: string | number;
    rating?: number;
    title?: string | number;
    description?: string;
  } = {},
): Promise<ApiResponse<object>> => {
  try {
    const response = await api.post("/delivery-boy/feedback", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const updateDeliveryBoyReview = async (
  params: {
    id?: string | number;
    rating?: number;
    title?: string | number;
    description?: string;
  } = {},
): Promise<ApiResponse<object>> => {
  try {
    const { id } = params;
    const response = await api.post(`/delivery-boy/feedback/${id}`, params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

// RazorPay
export const createRazorPayOrder = async (
  params: {
    amount?: string | number;
    currency?: string;
    receipt?: string;
  } = {},
): Promise<ApiResponse<RazorpayOrderData>> => {
  try {
    const response = await api.post("/razorpay/create-order", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { ...fallbackApiRes, message: getSafeClientErrorResponse(error)?.message || fallbackApiRes.message };
  }
};

// Stripe
export const createStripeIntent = async (
  params: {
    amount?: string | number;
    currency?: string;
  } = {},
): Promise<ApiResponse<{ clientSecret: string }>> => {
  try {
    const response = await api.post("/stripe/create-order", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { ...fallbackApiRes, message: getSafeClientErrorResponse(error)?.message || fallbackApiRes.message };
  }
};

// PayStack
export const xenditCreateOrder = async (params: {
  amount: number | string;
  order_id: number | string;
  redirect_url: string;
  /**
   * Prefills the hosted checkout's name/email/mobile fields. Xendit only
   * honours these when they're part of the Create Invoice payload, so the
   * backend must forward this object as the invoice's `customer`.
   */
  customer?: {
    given_names?: string;
    surname?: string;
    email?: string;
    mobile_number?: string;
  };
}): Promise<ApiResponse<XenditCreateOrderResponse>> => {
  try {
    const response = await api.post("/xendit/create-order", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackApiRes;
  }
};

export const paystackCreateOrder = async (
  params: {
    amount?: string | number;
    currency?: string;
  } = {},
): Promise<ApiResponse<PaystackCreateOrderResponse>> => {
  try {
    const response = await api.post("/paystack/create-order", params);
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return { ...fallbackApiRes, message: getSafeClientErrorResponse(error)?.message || fallbackApiRes.message };
  }
};

export const sellerRegister = async (
  params:
    | FormData
    | {
        name?: string;
        email?: string;
        mobile?: string;
        password?: string;
        address?: string;
        city?: string;
        state?: string;
        landmark?: string;
        zipcode?: string;
        country?: string;
        latitude?: string;
        longitude?: string;
        business_license?: string | File;
        articles_of_incorporation?: string | File;
        national_identity_card?: string | File;
        authorized_signature?: string | File;
      },
): Promise<
  ApiResponse<PaystackCreateOrderResponse> | SafeClientErrorResponse
> => {
  try {
    // Check if params is FormData
    const isFormData = params instanceof FormData;

    const response = await api.post("/seller/register", params, {
      headers: isFormData
        ? {
            // Let browser set Content-Type with boundary for FormData
            // Don't manually set 'Content-Type': 'multipart/form-data'
          }
        : {
            "Content-Type": "application/json",
          },
    });

    return response.data;
  } catch (error: any) {
    reportApiFailure(error);
    return getSafeClientErrorResponse(error) || fallbackApiRes;
  }
};

export const getSellerReviews = async (params: {
  seller_id?: string | number;
  page: string | number;
  per_page: string | number;
}): Promise<PaginatedResponse<SellerReview[]>> => {
  try {
    const response = await api.get(`seller-feedback`, {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error);
    return fallbackPaginateRes;
  }
};

/* <----------------- Watch & Buy --------------------->*/

export const getWatchAndBuyConfig = async (): Promise<
  WatchAndBuyResponse<WatchAndBuyConfig>
> => {
  try {
    const response = await api.get<WatchAndBuyResponse<WatchAndBuyConfig>>(
      "/watch-and-buy/config",
    );
    return response.data;
  } catch (error) {
    reportApiFailure(error, "Watch & Buy config");
    return {
      success: false,
      message: "Unable to load Watch & Buy.",
      data: null,
    };
  }
};

export const getWatchAndBuyReels = async (params: {
  latitude: number;
  longitude: number;
  store_slug?: string;
  cursor?: string | null;
  session_id?: string | null;
  per_page?: number;
  access_token?: string | null;
}): Promise<WatchAndBuyResponse<WatchAndBuyReelFeedData>> => {
  try {
    const response = await api.get<
      WatchAndBuyResponse<WatchAndBuyReelFeedData>
    >("/watch-and-buy/reels", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error, "Watch & Buy Reel feed");
    return {
      success: false,
      message: "Unable to load Reels.",
      data: {
        items: [],
        meta: { next_cursor: null, per_page: params.per_page ?? 10 },
      },
    };
  }
};

export const getWatchAndBuyProfile = async (
  username: string,
  params: {
    latitude: number;
    longitude: number;
    page?: number;
    per_page?: number;
    access_token?: string | null;
  },
): Promise<WatchAndBuyResponse<WatchAndBuyProfileReelsData>> => {
  try {
    const response = await api.get<
      WatchAndBuyResponse<WatchAndBuyProfileReelsData>
    >(`/watch-and-buy/profiles/${encodeURIComponent(username)}`, { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error, "Watch & Buy profile");
    return { success: false, message: "Unable to load profile.", data: null };
  }
};

export const getWatchAndBuyStoryTray = async (
  params: {
    store_id?: number;
    store_slug?: string;
    cursor?: string | null;
    session_id?: string | null;
    per_page?: number;
    access_token?: string | null;
  } = {},
): Promise<WatchAndBuyResponse<WatchAndBuyStoryTrayData>> => {
  try {
    const response = await api.get<
      WatchAndBuyResponse<WatchAndBuyStoryTrayData>
    >("/watch-and-buy/statuses", { params });
    return response.data;
  } catch (error) {
    reportApiFailure(error, "Watch & Buy Story tray");
    return {
      success: false,
      message: "Unable to load Stories.",
      data: {
        items: [],
        meta: { next_cursor: null, per_page: params.per_page ?? 10 },
      },
    };
  }
};

export const getWatchAndBuyProfileStories = async (
  username: string,
  params: {
    store_id?: number;
    store_slug?: string;
    cursor?: string | null;
    per_page?: number;
    access_token?: string | null;
  } = {},
): Promise<WatchAndBuyResponse<WatchAndBuyProfileStoriesData>> => {
  try {
    const response = await api.get<
      WatchAndBuyResponse<WatchAndBuyProfileStoriesData>
    >(`/watch-and-buy/profiles/${encodeURIComponent(username)}/statuses`, {
      params,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error, "Watch & Buy profile Stories");
    return {
      success: false,
      message: "Unable to load profile Stories.",
      data: null,
    };
  }
};

export const updateWatchAndBuyReelLikes = async (
  params: WatchAndBuyReelLikeParams,
): Promise<WatchAndBuyResponse<WatchAndBuyReelLikeResult[]>> => {
  const { access_token, ...body } = params;
  try {
    const response = await api.put<
      WatchAndBuyResponse<WatchAndBuyReelLikeResult[]>
    >("/watch-and-buy/reels/likes", body, {
      params: access_token ? { access_token } : undefined,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error, "Watch & Buy Reel like");
    return {
      success: false,
      message: "Unable to update Reel like.",
      data: null,
    };
  }
};

export const markWatchAndBuyStoriesSeen = async (
  params: WatchAndBuyStorySeenParams,
): Promise<WatchAndBuyResponse<WatchAndBuyStorySeenResult[]>> => {
  const { access_token, ...body } = params;
  try {
    const response = await api.put<
      WatchAndBuyResponse<WatchAndBuyStorySeenResult[]>
    >("/watch-and-buy/statuses/seen", body, {
      params: access_token ? { access_token } : undefined,
    });
    return response.data;
  } catch (error) {
    reportApiFailure(error, "Watch & Buy Story seen");
    return {
      success: false,
      message: "Unable to mark Story as seen.",
      data: null,
    };
  }
};
