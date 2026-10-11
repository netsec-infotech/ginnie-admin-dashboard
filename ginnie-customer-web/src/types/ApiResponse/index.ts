export type ApiResponse<T> = {
  success: boolean;
  message: string;
  access_token?: string;
  data?: T | null;
  errors?: string[];
  total?: number;
};

export type VersionCheckData = {
  update_available: boolean;
  update_type: string;
  min_supported_version: string;
  latest_version: string;
  message: string;
  update_url: string;
};

export type PaginatedResponse<T, M = {}> = {
  success: boolean;
  message: string;
  data: {
    current_page: number;
    data: T;
    first_page_url?: string;
    from?: number;
    last_page?: number;
    last_page_url?: string;
    links?: {
      url: string | null;
      label: string;
      active: boolean;
    }[];
    next_page_url?: string | null;
    path?: string;
    per_page: number;
    prev_page_url?: string | null;
    to?: number;
    total: number;
    keywords?: string[];
  } & M;
};

export type Settings = [
  {
    variable: "app";
    value: AppSettings;
  },
  {
    variable: "authentication";
    value: AuthenticationSettings;
  },
  {
    variable: "web";
    value: WebSettings;
  },
  {
    variable: "system";
    value: SystemSettings;
  },
  {
    variable: "payment";
    value: PaymentSettings;
  },
  {
    variable: "notification";
    value: NotificationSettings;
  },
  {
    variable: "home_general_settings";
    value: HomeGeneralSettings;
  },
  {
    variable: "loyalty_point";
    value: LoyaltyPointSettings;
  },
];

export type LoyaltyPointSettings = {
  is_enabled: boolean;
  /** Points earned per currency unit spent (backend-defined earn rate) */
  point_rate: number;
  /** Monetary value of one point */
  redemption_value: number;
  /** Max % of the order value payable with points */
  max_redemption_percentage: number;
  /** Minimum order value required before points can be redeemed */
  min_order_value_to_redeem: number;
  is_expiry_enabled: boolean;
  expiry_days: number;
};

export type SystemSettings = {
  // App basics
  appName: string;
  logo: string;
  favicon: string;
  copyrightDetails: string;
  systemTimezone: string;

  // Support
  sellerSupportNumber: string;
  sellerSupportEmail: string;

  // Store & checkout
  systemVendorType: "single" | "multiple";
  checkoutType: "single_store" | "multi_store";
  /**
   * Which homepage the backend wants rendered. "home_builder" selects the
   * section-based /home-layout homepage; anything else (including absent) keeps
   * the legacy featured-sections homepage. Left open rather than a closed union
   * — the admin panel can add layouts we don't know about, and those must fall
   * back rather than fail to typecheck.
   */
  layoutType?: "home_builder" | string;
  minimumCartAmount: number;
  maximumItemsAllowedInCart: number;
  lowStockLimit: number | string;
  maximumDistanceToNearestStore: string | null;

  // Wallet
  enableWallet: boolean;
  welcomeWalletBalanceAmount: number;

  // Currency
  currency: string;
  currencySymbol: string;

  // Third-party integrations
  enableThirdPartyStoreSync: boolean;
  Shopify: boolean;
  Woocommerce: boolean;
  etsy: boolean;

  // Maintenance modes
  sellerAppMaintenanceMode: boolean;
  sellerAppMaintenanceMessage: string;
  webMaintenanceMode: boolean;
  webMaintenanceMessage: string;

  // Demo mode (✅ missing earlier)
  demoMode: boolean;
  adminDemoModeMessage: string;
  sellerDemoModeMessage: string;
  customerDemoModeMessage: string;
  customerLocationDemoModeMessage: string;
  deliveryBoyDemoModeMessage: string;

  // Refer & Earn
  referEarnStatus: boolean;
  referEarnMethodUser: "fixed" | "percentage" | string;
  referEarnBonusUser: string;
  referEarnMaximumBonusAmountUser: string;
  referEarnMethodReferral: "fixed" | "percentage" | string;
  referEarnBonusReferral: string;
  referEarnMaximumBonusAmountReferral: string;
  referEarnMinimumOrderAmount: string;
  referEarnNumberOfTimesBonus: string;
};

export interface PaymentSettings {
  stripePayment: boolean;
  stripePaymentMode: "test" | "live";
  stripePublishableKey: string;
  stripeCurrencyCode: string;

  razorpayPayment: boolean;
  razorpayPaymentMode: "test" | "live";
  razorpayKeyId: string;

  paystackPayment: boolean;
  paystackPaymentMode: "test" | "live";
  paystackPublicKey: string;

  cod: boolean;

  directBankTransfer: boolean;
  bankAccountName: string;
  bankAccountNumber: string;
  bankName: string;
  bankCode: string;
  bankExtraNote: string;

  flutterwavePayment: boolean;
  flutterwavePaymentMode: "test" | "live";
  flutterwavePublicKey: string;
  flutterwaveCurrencyCode: string;

  xenditPayment: boolean;
  xenditPaymentMode: "test" | "live";
  xenditCurrencyCode: string;

  wallet: boolean;
}

export type AuthenticationSettings = {
  // SMS Gateway configuration
  customSms: boolean;
  customSmsUrl: string;
  customSmsMethod: "GET" | "POST" | string;
  googleRecaptchaSiteKey: string;
  customSmsTokenAccountSid: string;
  customSmsAuthToken: string;
  customSmsTextFormatData: string;
  customSmsHeaderKey: string[];
  customSmsHeaderValue: string[];
  customSmsParamsKey: string[];
  customSmsParamsValue: string[];
  customSmsBodyKey: string[];
  customSmsBodyValue: string[];
  firebase: boolean;

  /**
   * Selected SMS gateway for OTP flows.
   * "firebase" → use Firebase phone auth
   * "custom"   → use backend auth/send-otp & auth/verify-otp
   */
  smsGateway?: "firebase" | "custom";

  // Firebase configuration
  fireBaseApiKey: string;
  fireBaseAuthDomain: string;
  fireBaseDatabaseURL: string;
  fireBaseProjectId: string;
  fireBaseStorageBucket: string;
  fireBaseMessagingSenderId: string;
  fireBaseAppId: string;
  fireBaseMeasurementId: string;

  // Social login configuration
  appleLogin: boolean;
  googleLogin: boolean;
  facebookLogin: boolean;
  googleApiKey: string;
};

export type NotificationSettings = {
  firebaseProjectId: string;
  serviceAccountFile: string;
  vapIdKey: string;
};

export type WebSettings = {
  siteName: string;
  customerWebUrl?: string;
  /** Brand colour, e.g. "#397dea". Applied over the theme at runtime. */
  primaryColor?: string;
  /** Optional text colour override. Frequently empty — treat "" as unset. */
  fontColor?: string;
  poweredByText?: string;
  poweredByUrl?: string;
  siteCopyright: string;
  supportNumber: string;
  supportEmail: string;
  address: string;
  shortDescription: string;
  siteHeaderLogo: string;
  siteHeaderDarkLogo: string;
  siteFooterLogo: string;
  siteFavicon: string;
  headerScript: string;
  footerScript: string;
  googleMapKey: string;
  mapIframe: string;
  appDownloadSection: boolean;
  appSectionTitle: string;
  appSectionTagline: string;
  appSectionPlaystoreLink: string;
  appSectionAppstoreLink: string;
  appSectionShortDescription: string;
  facebookLink: string;
  instagramLink: string;
  xLink: string;
  youtubeLink: string;
  shippingFeatureSection: string;
  shippingFeatureSectionTitle: string;
  shippingFeatureSectionDescription: string;
  returnFeatureSection: string;
  returnFeatureSectionTitle: string;
  returnFeatureSectionDescription: string;
  safetySecurityFeatureSection: string;
  safetySecurityFeatureSectionTitle: string;
  safetySecurityFeatureSectionDescription: string;
  supportFeatureSection: string;
  supportFeatureSectionTitle: string;
  supportFeatureSectionDescription: string;
  /**
   * "Order Now" promo banner. `orderNowSection` is the on/off switch — the
   * section renders nothing when it's false.
   */
  orderNowSection?: boolean;
  orderNowSectionBadgeIcon?: string;
  orderNowSectionTagline?: string;
  orderNowSectionTitle?: string;
  /** Rendered in an accent colour after the title. */
  orderNowSectionTitleHighlight?: string;
  orderNowSectionDescription?: string;
  orderNowButtonText?: string;
  /** May be absolute (possibly another domain) or a site-relative path. */
  orderNowButtonUrl?: string;
  orderNowFeatures?: string[];
  orderNowSectionImage?: string;
  /**
   * Seller-register hero banner. Every field is optional — the component falls
   * back to its i18n strings so the hero still reads correctly before settings
   * load or when a field is left blank in admin.
   */
  sellerBannerIconText?: string;
  sellerBannerTitle?: string;
  /** Rendered in an accent colour below the title. */
  sellerBannerTitleHighlight?: string;
  sellerBannerDescription?: string;
  /** Drives the scrolling marquee under the hero. */
  sellerBannerFeatures?: string[];
  sellerBannerBackgroundImage?: string;
  metaKeywords: string;
  metaDescription: string;
  defaultLatitude: string;
  defaultLongitude: string;
  enableCountryValidation: boolean;
  allowedCountries: string[];
  returnRefundPolicy: string;
  shippingPolicy: string;
  privacyPolicy: string;
  termsCondition: string;
  aboutUs: string;
  /**
   * Site version shown in the footer chip. Optional — older backends don't
   * send it, in which case the build-time NEXT_PUBLIC_APP_VERSION is used.
   */
  version?: string;
};

export type AppSettings = {
  appstoreLink: string;
  playstoreLink: string;
  appScheme: string;
  appDomainName: string;
  customerAppScheme?: string;
  customerAppstoreLink?: string;
  customerPlaystoreLink?: string;
  sellerAppScheme?: string;
  sellerAppstoreLink?: string;
  sellerPlaystoreLink?: string;
};

export type HomeGeneralSettings = {
  title: string;
  searchLabels: string[];
  backgroundType: "image" | "color" | "animation" | "lottie";
  backgroundColor: string;
  backgroundImage?: string;
  backgroundAnimation?: string;
  backgroundColorWeb?: string;
  backgroundImageWeb?: string;
  backgroundAnimationWeb?: string;
  icon?: string;
  activeIcon?: string;
  fontColor: string;
};

export type Category = {
  id: number;
  title: string;
  slug: string;
  image: string;
  banner: string;
  icon: string;
  active_icon: string;
  background_type: string | null;
  background_color: string;
  background_image: string;
  font_color: string;
  parent_id: number | null;
  parent_slug: string | null;
  description: string | null;
  status: "active" | "inactive";
  requires_approval: boolean;
  metadata: string | null;
  subcategory_count: number;
  product_count: number;
  enabled?: boolean;
  custom_image?: string | null;
};

export type SliderImage = {
  id: string;
  url: string;
  alt: string;
};
// Extended type definitions
export interface AddonGroupItem {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  indicator: "veg" | "non_veg" | null;
  price: number;
  cost: number;
  stock: number;
  is_available: boolean;
}

export interface AddonGroup {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  selection_type: "single" | "multiple";
  is_required: boolean;
  sort_order: number;
  items: AddonGroupItem[];
}

export interface ProductVariant {
  id: number;
  title: string;
  slug: string;
  image: string;
  weight: number;
  height: number;
  breadth: number;
  length: number;
  availability: boolean;
  cart_item?: {
    exists: boolean;
    cart_item_id: number | null;
  };
  barcode: string;
  is_default: boolean;
  price: number;
  special_price: number;
  store_id: number;
  store_slug: string;
  store_name: string;
  stock: number;
  sku: string;
  attributes: Record<string, string>;
  addon_groups?: AddonGroup[];
  is_addons?: boolean;
  notification_set?: boolean;
}

export interface SwatchValue {
  value: string;
  swatch: string;
}

export interface ProductAttribute {
  name: string;
  slug: string;
  swatche_type: "text" | "image" | "color";
  values: string[];
  swatch_values: SwatchValue[];
}

export interface KeywordSearch {
  keyword: string;
  total_products: number;
  current_page: number;
  last_page: number;
  per_page: number;
  products: Product[];
}

export interface CustomFields {
  [key: string]: string | undefined;
}

export interface CustomProductSectionField {
  id: number;
  uuid: string;
  title: string;
  description: string;
  image: string;
  sort_order: number;
}

export interface CustomProductSection {
  id: number;
  uuid: string;
  title: string;
  description: string;
  sort_order: number;
  fields: CustomProductSectionField[];
}

export interface Product {
  id: number;
  uuid: string;
  category_id: number;
  brand_id: number | null;
  brand_name: string | null;
  seller_id: number;
  title: string;
  slug: string;
  type: "simple" | "variant";
  short_description: string;
  description: string;
  category: string;
  category_name: string;
  brand: string;
  seller: string | null;
  indicator: "veg" | "non_veg" | null;
  favorite: FavoriteItem[] | null;
  estimated_delivery_time: number | null;
  ratings: number;
  rating_count: number;
  main_image: string;
  image_fit?: "contain" | "cover";
  additional_images: string[];
  minimum_order_quantity: number;
  quantity_step_size: number;
  total_allowed_quantity: number;
  is_returnable: number;
  returnable_days: number | null;
  is_cancelable: number;
  cancelable_till: string | null;
  tags: string[];
  warranty_period: string | null;
  guarantee_period: string | null;
  made_in: string | null;
  is_inclusive_tax: string;
  video_type: "youtube" | "self_hosted" | null;
  video_link: string | null;
  status: string;
  featured: "1" | "0" | null;
  metadata: string | null;
  item_count_in_cart?: string;
  seller_ratings?: {
    total_reviews: number | null;
    average_rating: number | null;
    one_star_count: number | null;
    two_star_count: number | null;
    three_star_count: number | null;
    four_star_count: number | null;
    five_star_count: number | null;
  };
  custom_fields: CustomFields;
  created_at: string;
  updated_at: string;
  store_status: {
    is_open: boolean;
    status: string;
    current_slot?: string | null;
    next_opening_time?: string;
  };
  variants: ProductVariant[];
  attributes: ProductAttribute[];
  custom_product_sections: CustomProductSection[];
  notification_set?: boolean;
}

export interface FavoriteItem {
  id: number;
  wishlist_id: number;
  wishlist_title: string;
  variant_id: number;
  variant_name: string;
  store_id: number;
  store_name: string;
}

export interface ProductReviews {
  total_reviews: number;
  average_rating: string;
  ratings_breakdown: RatingsBreakdown;
  reviews: Review[];
}

export interface RatingsBreakdown {
  "1_star": string;
  "2_star": string;
  "3_star": string;
  "4_star": string;
  "5_star": string;
}

export interface Review {
  id: number;
  product_id: number;
  rating: number;
  title: string;
  slug: string;
  comment: string;
  review_images: string[];
  user: {
    id: number;
    name: string;
  };
  created_at: string;
}

export interface SellerReview {
  id: number;
  user_id: number;
  seller_id: number;
  order_id: number;
  rating: number;
  title: string;
  slug: string;
  description: string;
  user: {
    id: number;
    name: string;
  };
  seller: {
    id: number;
    name: string;
  };
  order: {
    id: number;
    order_number: string | null;
  };
  created_at: string; // or Date if you convert later
  updated_at: string; // or Date if you convert later
}

export interface ProductFaq {
  id: number;
  product_id: number;
  product_slug: string;
  product: {
    id: number;
    title: string;
    slug: string;
  };
  question: string;
  answer: string;
  status: "active" | "inactive";
  created_at: string;
  updated_at: string;
}

// Orders Type

export interface Order {
  id: number;
  /**
   * The fulfilling store with its scheduling rules inlined — served on the
   * order detail so the reschedule calendar can apply the same closed-day and
   * booking-window rules as checkout without another fetch.
   */
  store?: {
    id: number;
    name?: string;
    logo?: string;
    is_scheduled_delivery_enabled?: boolean;
    max_advance_booking_days?: number;
    /** Full day names, e.g. ["Monday", "Friday"]; unlisted days are closed. */
    available_days?: string[];
  } | null;
  uuid: string;
  slug: string;
  user_id: number;
  email: string;
  currency_code: string;
  currency_rate: string;
  payment_method: string;
  payment_status: string;
  status: OrderStatus;
  invoice: string;
  fulfillment_type: string;
  /** "delivery" | "pickup" — independent of `is_scheduled_delivery`. */
  delivery_type?: string;
  /** Free text shown for self-collection orders. */
  pickup_instructions?: string | null;
  /** 0/1 from the backend, not a bool. */
  is_rush_order?: number | boolean;
  estimated_delivery_time: number;
  /**
   * Whether a delivery window was booked. Set independently of
   * `delivery_type` — a scheduled order is still `delivery_type: "delivery"`.
   */
  is_scheduled_delivery?: boolean;
  /** Local YYYY-MM-DD of the booked window. */
  scheduled_date?: string | null;
  /** Pre-formatted by the backend, e.g. "04:00 PM - 05:00 PM". */
  scheduled_time_slot_label?: string | null;
  /** Start/end of the booked window, when the backend identifies slots by time. */
  scheduled_slot_start_time?: string | null;
  scheduled_slot_end_time?: string | null;
  delivery_time_slot_id: number | null;
  /**
   * Set when the backend flags this order for customer recovery. Absent on
   * every order from a backend that doesn't serve the feature.
   */
  reschedule_status?: RescheduleStatus;
  reschedule_reason?: string | null;
  reschedule_deadline?: string | null;
  delivery_boy_id: number | null;
  delivery_boy_name: string;
  delivery_boy_phone: number | string;
  delivery_boy_profile: string;
  is_delivery_feedback_given: boolean;

  delivery_feedback: {
    id: number;
    title: string;
    slug: string;
    description: string;
    rating: number;
    created_at: string;
  } | null;

  wallet_balance: string;
  promo_code: string | null;
  promo_discount: string;
  promo_line: null | {
    cashback_flag: boolean;
    created_at: string;
    discount_amount: string;
    id: number;
    is_awarded: boolean;
    order_id: number;
    promo_code: string;
    promo_id: number;
    updated_at: string;
  };
  gift_card: string | null;
  gift_card_discount: string;
  delivery_charge: string | number;
  handling_charges: string | number;
  cod_charges?: string | number;
  per_store_drop_off_fee: string | number;
  delivery_distance_charges: string | number;
  /**
   * NOT part of `final_total` — post-order tips are recorded (and collected
   * via their own `payment_method`) without touching the order totals.
   */
  rider_tip_amount?: number | string;
  tipping_config?: TippingConfig;

  subtotal: string;
  total_payable: string;
  final_total: string;

  shipping_name: string;
  shipping_address_1: string;
  shipping_address_2: string | null;
  shipping_landmark: string;
  shipping_zip: string;
  shipping_phone: string;
  shipping_address_type: string;
  shipping_latitude: string;
  shipping_longitude: string;
  shipping_city: string;
  shipping_state: string;
  shipping_country: string;
  shipping_country_code: string;
  order_note: string;

  items: OrderItem[];
  seller_feedbacks: SellerFeedbackItem[];

  created_at: string;
  updated_at: string;

  /** NEW FIELD */
  payment_response: any | null;
}

/**
 * A selectable reason from `/user/orders/items/{id}/return-reasons`.
 *
 * The label field is loosely typed because the backend has used `reason`,
 * `title` and `name` for this across endpoints; `getReturnReasonLabel` picks
 * whichever is present so a backend rename doesn't blank the picker.
 */
export interface ReturnReason {
  id: number | string;
  reason?: string;
  title?: string;
  name?: string;
  /** Present on some reasons to force a free-text explanation. */
  requires_comment?: boolean;
}

export interface OrderItemReturnRequest {
  id: number;
  order_item_id: number;
  order_id: number;
  user_id: number;
  seller_id: number;
  store_id: number;
  delivery_boy_id: number | null;
  reason: string;
  /** Set when the customer picked from `getReturnReasons` rather than typing. */
  return_reason_id?: number | null;
  return_reason_name?: string | null;
  /**
   * Requirements frozen at request time, so a later change to the store's
   * policy doesn't retroactively alter what this return demands.
   */
  snapshot_otp_required?: boolean;
  snapshot_proof_required?: boolean;
  seller_comment: string | null;
  images: string[];
  refund_amount: number;
  pickup_status: string;
  return_status: string;
  /** OTP the customer hands to the agent collecting this returned item. */
  pickup_otp: string | null;
  pickup_otp_verified: boolean;
  store_rejection_reason: string | null;
  action_on_rejection: string | null;
  /** Structured counterpart to `store_rejection_reason`. */
  rejection_reason_id?: number | null;
  rejection_reason_name?: string | null;
  /** Who the store held responsible — internal, not customer-facing. */
  responsible_party?: string | null;
  deduct_from_rider?: boolean;
  deduction_amount?: number | string | null;
  pickup_images: string[];
  /** Evidence the store attached when rejecting. */
  rejection_photos?: string[];
  rejection_video?: string | null;
  seller_approved_at: string | null;
  picked_up_at: string | null;
  received_at: string | null;
  /** Set once the store confirms the returned goods arrived back. */
  store_confirmed_at?: string | null;
  store_rejected_at?: string | null;
  refund_processed_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_id: number;
  product_variant_id: number;
  store_id: number;

  seller_id: number;
  seller_name: string;

  title: string;
  variant_title: string;

  gift_card_discount: string;
  admin_commission_amount: string;
  seller_commission_amount: string;
  commission_settled: string;
  discounted_price: string;
  promo_discount: string;
  discount: string;
  tax_amount: string;
  tax_percent: string;

  sku: string;
  quantity: number;
  price: string;
  subtotal: string;

  status: OrderStatus;
  otp: string | null;
  otp_verified: number;

  is_user_review_given: boolean;
  user_review: {
    id: number;
    product_id: number;
    rating: number;
    title: string;
    slug: string;
    comment: string;
    review_images: string[];
    user: {
      id: number;
      name: string;
    };
    created_at: string;
  } | null;

  product: OrderProduct;
  variant: OrderVariant;
  store: {
    id: number;
    name: string;
    slug: string;
  };

  return_eligible: boolean;
  return_deadline: string | null;
  returns: OrderItemReturnRequest[];
  attachments: string[];
  addons?: any[];

  created_at: string;
  updated_at: string;
}

export interface OrderProduct {
  id: number;
  name: string | null;
  slug: string;
  image: string;
  requires_otp: number;
  is_returnable: boolean;
  returnable_days: number;
  is_cancelable: boolean;
  cancelable_till: string | null;
}

export interface OrderVariant {
  id: number;
  title: string;
  slug: string;
  image: string;
}

export type SellerFeedbackItem = {
  seller_id: number;
  is_feedback_given: boolean;
  feedback: {
    id: number;
    user_id: number;
    seller_id: number;
    order_id: number;
    rating: number;
    title: string;
    slug: string;
    description: string;
    created_at: string;
    updated_at: string;
  } | null;
};

export type OrderStatus =
  | "awaiting_store_response"
  | "partially_accepted"
  | "ready_for_pickup"
  | "assigned"
  | "out_for_delivery"
  | "delivered"
  | "cancelled"
  | "preparing"
  | "pending";

export type TransactionQueryArgs = {
  page?: number;
  status?: string;
  payment_status?: string;
  transaction_type?: string;
  query?: string;
  search?: string;
};

export type WalletTransaction = {
  id: number;
  wallet_id: number;
  user_id: number;
  order_id: number | null;
  store_id: number | null;
  transaction_type: "deposit" | "withdraw" | string;
  payment_method: string;
  amount: string; // since "3.00" is a string
  currency_code: string;
  status: "pending" | "completed" | "failed" | string;
  transaction_reference: string | null;
  description: string | null;
  created_at: string;
  updated_at: string;
};

export type LoyaltyPointsSummary = {
  is_enabled: boolean;
  points_balance: number;
  redemption_value: number;
  equivalent_monetary_value: number;
  expiring_points_in_30_days: number;
};

export type LoyaltyPointTransaction = {
  id: number;
  order_id?: number | null;
  points: number;
  used_points?: number;
  type: "earned" | "redeemed" | string;
  status: "pending" | "credited" | "completed" | "expired" | string;
  description: string | null;
  expires_at?: string | null;
  created_at: string;
};

// Note: this endpoint does NOT use the standard Laravel paginator shape
// (data.data) — it returns the summary fields alongside the current page of
// transactions, nested under data.transactions + data.pagination.
export type LoyaltyPointsTransactionsData = LoyaltyPointsSummary & {
  transactions: LoyaltyPointTransaction[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type LoyaltyTransactionQueryArgs = {
  page?: number;
  type?: string;
  /** YYYY-MM-DD */
  date_from?: string;
  /** YYYY-MM-DD */
  date_to?: string;
};

export interface Transaction {
  id: number;
  uuid: string;
  order_id: number | null;
  user_id: number;
  transaction_id: string;
  amount: string;
  currency: string;
  payment_method: string;
  payment_status: string;
  message: string;
  payment_details?: PaymentDetails;
  created_at: string;
  updated_at: string;
}

export interface PaymentDetails {
  id: string | null;
  entity: string | null;
  amount: number | null;
  currency: string | null;
  status: string | null;
  order_id: string | null;
  invoice_id: string | null;
  international: boolean | null;
  method: string | null;
  amount_refunded: number | null;
  refund_status: string | null;
  captured: boolean | null;
  description: string | null;
  card_id: string | null;
  bank: string | null;
  wallet: string | null;
  vpa: string | null;
  email: string | null;
  contact: string | null;
  notes: {
    user_id: number | null;
    timeOfPayment: string | null;
  } | null;
  fee: number | null;
  tax: number | null;
  error_code: string | null;
  error_description: string | null;
  error_source: string | null;
  error_step: string | null;
  error_reason: string | null;
  acquirer_data: {
    bank_transaction_id: string | null;
  } | null;
  created_at: number | null;
  reward: string | null;
  base_amount: number | null;
}

export type Address = {
  id: number;
  user_id: number;
  address_line1: string;
  address_line2: string | null;
  city: string;
  landmark: string | null;
  state: string;
  zipcode: string;
  mobile: string;
  address_type: "home" | "work" | string; // Extend with more types if needed
  country: string;
  country_code: string;
  latitude: number;
  longitude: number;
  created_at: string; // or `Date` if parsed
  updated_at: string; // or `Date` if parsed
};

export type VerifyUserData = {
  exists: boolean;
  type: "email" | "mobile";
  value: string;
};

export type userData = {
  id: number;
  mobile: string;
  mobile_verified_at?: string | null;
  otp_verified?: number | boolean | string;
  country: string;
  iso_2: string;
  wallet_balance: string | number;
  referral_code?: string | null;
  friends_code: string | null;
  reward_points?: number;
  profile_image: string;
  status?: boolean;
  new_user?: boolean;
  name: string;
  email: string;
  email_verified_at?: string | null;
  access_panel?: string;
  deleted_at?: string | null;
  created_at?: string;
  updated_at?: string;
};

export type ReferralProgram = {
  status: boolean;
  referrer_bonus_method: "fixed" | "percentage" | string;
  referrer_bonus_value: string;
  referrer_bonus_max_cap: string;
  referee_bonus_method: "fixed" | "percentage" | string;
  referee_bonus_value: string;
  referee_bonus_max_cap: string;
  minimum_order_amount: string;
  max_times_bonus: string;
};

export type ReferralInfo = {
  referral_code: string;
  friends_code: string | null;
  total_referrals: number;
  total_earned: number;
  pending_earnings: number;
  program: ReferralProgram;
};

export interface Brand {
  id: string | number;
  title: string;
  slug: string;
  logo: string;
  description?: string;
  enabled?: boolean;
}

export type Banner = {
  id: number;
  type: "category" | "product" | "custom" | "brand";
  title: string;
  scope_type: "global" | "category";
  scope_id: number | null;
  scope_category_slug: string;
  slug: string;
  custom_url: string | null;
  product_id: number | null;
  product_slug: string;
  category_id: number | null;
  category_slug: string;
  brand_id: number | null;
  brand_slug: string;
  position: "top" | "carousel" | "sidebar";
  visibility_status: "published";
  display_order: number;
  metadata: string | null;
  banner_image: string;
};

export interface BannerData {
  top: Banner[];
  carousel: Banner[];
}

export interface CheckDeliveryZone {
  is_deliverable: boolean;
  zone_count: number;
  zone: string | null;
  zone_id: number | null;
  coordinates: {
    latitude: number;
    longitude: number;
  };
}

export interface Store {
  id: number;
  name: string;
  slug: string;
  product_count: number;
  description: string;
  contact_number: string;
  contact_email: string;
  address: string;
  latitude: string;
  longitude: string;
  lat?: number;
  lng?: number;
  distance: string | number;
  /**
   * Free text, and frequently null. The admin panel emits a canonical
   * "Mon: 09:00-21:00, …" string for newer stores, but legacy rows hold prose
   * ("11 AM – 8 PM (Mon–Sat)") or junk. Parse via `parseStoreTiming`, which
   * returns null when the value isn't the canonical shape.
   */
  timing: string | null;
  logo: string;
  banner: string;
  avg_products_rating: string;
  avg_store_rating: string;
  total_store_feedback: string;
  created_at: string;
  updated_at: string;
  verification_status: "approved" | "pending" | "rejected";
  visibility_status: "visible" | "hidden";
  status: {
    is_open: boolean;
    status: "online" | "offline" | string;
  };
  /** Only present on the store-detail endpoint, not on list responses. */
  scheduled_delivery_info?: ScheduledDeliveryInfo;
}

export interface StoreDeliverySlot {
  /** The backend's `delivery_time_slot_id`. */
  id: number;
  /** "10:00 AM - 12:00 PM" */
  label: string;
  /** "10:00:00" */
  start_time: string;
  end_time: string;
  max_orders: number;
  /**
   * Lowercase day names — but the SHAPE is inconsistent: the API returns a
   * JSON-encoded string ("[\"monday\",\"tuesday\"]") on some rows and a real
   * array ([]) on others, within the same response. Anything reading this must
   * handle both.
   */
  days_of_week: string | string[];
  is_active: boolean;
}

export interface StoreDeliverySlotToday {
  id: number;
  label: string;
  start_time: string;
  end_time: string;
  remaining_capacity: number;
  is_available: boolean;
  /** i18n key, e.g. "messages.slot_cutoff_passed". */
  reason?: string;
}

/**
 * A slot from `/scheduled-delivery/stores/{id}/slots?date=`.
 *
 * This is the authoritative shape — it carries live availability, unlike the
 * `slots[]` embedded on the store record, which only describes the schedule.
 */
export interface ScheduledDeliverySlot {
  /** The `delivery_time_slot_id` to send with createOrder. */
  id: number;
  /** Backend-formatted, e.g. "10:00 AM - 12:00 PM". Display as-is. */
  label: string;
  /** "10:00:00" — 24h local time. */
  start_time: string;
  end_time: string;
  remaining_capacity: number;
  max_orders_reached: boolean;
  /** False when the cutoff has passed or capacity is gone. */
  is_available: boolean;
  /** Human-readable English, e.g. "Cutoff time passed for this slot." */
  reason?: string | null;
}

/** Response of `/scheduled-delivery/stores/{id}/slots?date=YYYY-MM-DD`. */
export interface ScheduledDeliverySlotsData {
  is_enabled: boolean;
  /** Set when the whole date is refused, e.g. beyond the booking window. */
  message?: string | null;
  slots: ScheduledDeliverySlot[];
}

/**
 * Response of `/scheduled-delivery/stores/{id}/dates`.
 *
 * Note the shape differs by state: a DISABLED store returns
 * `{ is_enabled: false, dates: [] }`, while an enabled one returns the config
 * below with no `dates` key at all — the bookable days must be derived from
 * `available_days` + `max_advance_booking_days`.
 */
export interface ScheduledDeliveryDatesData {
  is_enabled: boolean;
  buffer_minutes?: number;
  max_advance_booking_days?: number;
  max_days?: number;
  /** Full day names, e.g. ["Monday", "Tuesday"]. */
  available_days?: string[];
  /** The store's schedule (no live availability — use the slots endpoint). */
  slots?: StoreDeliverySlot[];
  /** Only present when scheduling is disabled. */
  dates?: string[];
}

/** Per-store scheduled-delivery configuration. */
export interface ScheduledDeliveryInfo {
  is_enabled: boolean;
  /** Backend copy, English-only — prefer a translated string for display. */
  message?: string;
  /** Lead time before a slot can be booked. */
  buffer_minutes: number;
  /** How far ahead booking is allowed. The configured value. */
  max_advance_booking_days: number;
  /** The EFFECTIVE window — 0 when scheduling is disabled. */
  max_days: number;
  /** Full day names, e.g. ["Monday", "Thursday"]. Empty when disabled. */
  available_days: string[];
  slots: StoreDeliverySlot[];
  /** Duplicates of `slots` in every observed response. */
  time_slots: StoreDeliverySlot[];
  available_slots: StoreDeliverySlot[];
  available_slots_today: StoreDeliverySlotToday[];
}

/* ------------------------------------------------------------------ *
 * Cart-scoped scheduling (`/cart/dates`, `/cart/slots`)
 *
 * A second, newer contract that sits alongside the per-store one above. The
 * differences are not cosmetic:
 *   - `/cart/dates` returns an explicit `dates[]`; the per-store `/dates`
 *     returns RULES the client expands into days.
 *   - A cart slot has NO `id`. It is identified by date + start/end time, and
 *     an order books it with `scheduled_slot_start_time` / `_end_time` rather
 *     than `delivery_time_slot_id`.
 * Both live here because the storefront must keep working against whichever
 * one the deployed backend actually serves — see `helpers/scheduling.ts`.
 * ------------------------------------------------------------------ */

/** One bookable window from `/cart/slots` or `/slots`. */
export interface CartDeliverySlot {
  /** "14:00:00" — 24h local time. Part of this slot's identity. */
  start_time: string;
  end_time: string;
  /** Backend-formatted for display, e.g. "02:00 PM - 03:00 PM". */
  formatted_time: string;
  remaining_capacity: number;
}

/** Response of `/cart/dates` and `/dates?store_id=`. */
export interface CartDeliveryDatesData {
  is_enabled: boolean;
  /** Explicit bookable days, local YYYY-MM-DD. */
  dates: string[];
}

/** Response of `/cart/slots?date=` and `/slots?store_id=&date=`. */
export interface CartDeliverySlotsData {
  is_enabled: boolean;
  slots: CartDeliverySlot[];
  /** Set when the whole date is refused. */
  message?: string | null;
}

/**
 * Why an order needs the customer's attention before it can be delivered.
 * `needs_reschedule` is the only state that opens the recovery flow.
 */
export type RescheduleStatus =
  | "needs_reschedule"
  | "rescheduled"
  | "cancelled"
  | "none"
  | null;

/**
 * Recovery fields on an order flagged by the backend (stock change, store
 * closure, hours update). All optional: orders predating the feature — and
 * every order from a backend that doesn't serve it — simply omit them.
 */
export interface OrderRescheduleInfo {
  reschedule_status?: RescheduleStatus;
  /** English backend copy, e.g. "Store is temporarily closed". */
  reschedule_reason?: string | null;
  /** ISO-8601 UTC. After this passes the customer can no longer act. */
  reschedule_deadline?: string | null;
}

export type SectionType =
  | "newly_added"
  | "top_rated"
  | "trending"
  | "best_seller"
  | "featured"
  | "on_sale"
  | "recommended";

export interface FeaturedSection {
  id: number;
  title: string;
  slug: string;
  short_description: string;
  style: "without_background" | "with_background";
  section_type: SectionType;
  sort_order: number;
  status: "active" | "inactive" | string;
  scope_type: "global" | "local" | string;
  scope_id: number | null;
  scope_category_slug: string;
  scope_category_title: string;
  background_type: "image" | "color" | string | null;
  background_color: string | null;
  background_image: string;
  desktop_4k_background_image: string;
  desktop_fdh_background_image: string;
  tablet_background_image: string;
  mobile_background_image: string;
  text_color: string;
  categories: Category[];
  products: Product[];
  products_count: number;
  created_at: string;
  updated_at: string;
}

// Home Layout API (GET /home-layout) — the new unified, section-driven homepage.
// NOTE: unlike PaginatedResponse, the paginated array lives at `data.sections`
// (not `data.data`), so this uses its own envelope type.
// Config keys vary by section type (a categories section omits `orientation`,
// a products section includes it), so every field is optional.
export interface HomeLayoutSpacingBox {
  top?: string | number | null;
  bottom?: string | number | null;
  left?: string | number | null;
  right?: string | number | null;
  /** The API uses `sides` for equal left/right inner padding. */
  sides?: string | number | null;
}

export interface HomeLayoutItemGap {
  horizontal?: string | number | null;
  vertical?: string | number | null;
}

export interface HomeLayoutBorderRadius {
  top_left?: string | number | null;
  top_right?: string | number | null;
  bottom_left?: string | number | null;
  bottom_right?: string | number | null;
}

export interface HomeLayoutThemeColor {
  light?: string | null;
  dark?: string | null;
}

export interface HomeLayoutSectionSpacing {
  outer?: HomeLayoutSpacingBox | null;
  inner?: HomeLayoutSpacingBox | null;
  item_gap?: HomeLayoutItemGap | string | number | null;
  border_radius?: HomeLayoutBorderRadius | string | number | null;
}

export interface HomeLayoutSectionConfig {
  source?: string | null; // products: newly_added|top_rated|best_seller|custom_products; categories: custom
  orientation?: "horizontal" | "vertical" | null;
  // How many items the layout response inlines for this section. It is also the
  // page size the backend paginated with, so /home-layout/sections/{id} resumes
  // cleanly at page 2 with the same per_page. Sent as a string (e.g. "12").
  limit?: string | number | null;
  background_type?: string | null;
  /** Image-backed sections: keep the current shallow backdrop (`half`) or
   * cover the complete section surface (`full`). */
  background_image_style?: "half" | "full" | string | null;
  background_color?: string | HomeLayoutThemeColor | null;
  text_color?: string | HomeLayoutThemeColor | null;
  rows_layout?: string | null;
  /** Hero item/card presentation fields supplied by the home-layout builder. */
  item_source?: string | null;
  card_bg_type?: string | null;
  card_bg_color?: string | null;
  card_title_color?: string | null;
  spacing?: HomeLayoutSectionSpacing | null;
  /** `hero` sections: image | video. */
  media_type?: "image" | "video" | string | null;
  /** `hero` sections can render their content items as the media source. */
  layout?: "items" | string | null;
  /** Optional CSS object-position for full-width hero media. */
  media_position?: string | null;
  image_position?: string | null;
  object_position?: string | null;
  /** Optional hero/banner sizing and media presentation controls. */
  height?: string | number | null;
  hero_height?: string | number | null;
  banner_height?: string | number | null;
  mobile_height?: string | number | null;
  tablet_height?: string | number | null;
  desktop_height?: string | number | null;
  height_mobile?: string | number | null;
  height_tablet?: string | number | null;
  height_desktop?: string | number | null;
  max_height?: string | number | null;
  maximum_height?: string | number | null;
  aspect_ratio?: string | number | null;
  ratio?: string | number | null;
  object_fit?:
    | "fill"
    | "cover"
    | "contain"
    | "scale-down"
    | "none"
    | string
    | null;
  media_fit?:
    | "fill"
    | "cover"
    | "contain"
    | "scale-down"
    | "none"
    | string
    | null;
  image_fit?:
    | "fill"
    | "cover"
    | "contain"
    | "scale-down"
    | "none"
    | string
    | null;
  fit?:
    | "fill"
    | "cover"
    | "contain"
    | "scale-down"
    | "none"
    | string
    | null;
  border_radius?: string | number | null;
  radius?: string | number | null;
  foreground_color?: string | null;
  font_color?: string | null;
  background_image?: string | null;
  autoplay?: boolean | string | number | null;
  auto_play?: boolean | string | number | null;
  slider_autoplay?: boolean | string | number | null;
  autoplay_delay?: string | number | null;
  autoplay_speed?: string | number | null;
  slide_delay?: string | number | null;
  interval?: string | number | null;
  pagination?: boolean | string | number | null;
  show_pagination?: boolean | string | number | null;
}

/** Optional future media metadata for a full-width hero section. */
export interface HomeLayoutHeroMedia {
  type?: "image" | "video" | string | null;
  url?: string | null;
  poster?: string | null;
  badge?: string | null;
  title?: string | null;
  description?: string | null;
  cta_label?: string | null;
  cta_url?: string | null;
  media_position?: string | null;
  image_position?: string | null;
  object_position?: string | null;
  object_fit?:
    | "fill"
    | "cover"
    | "contain"
    | "scale-down"
    | "none"
    | string
    | null;
  media_fit?:
    | "fill"
    | "cover"
    | "contain"
    | "scale-down"
    | "none"
    | string
    | null;
  image_fit?:
    | "fill"
    | "cover"
    | "contain"
    | "scale-down"
    | "none"
    | string
    | null;
  height?: string | number | null;
  mobile_height?: string | number | null;
  tablet_height?: string | number | null;
  desktop_height?: string | number | null;
  max_height?: string | number | null;
  aspect_ratio?: string | number | null;
  border_radius?: string | number | null;
  foreground_color?: string | null;
  font_color?: string | null;
  text_color?: string | null;
}

/** A linked card rendered over a hero when `config.layout` is `items`. */
export interface HomeLayoutHeroItem {
  id: string | number;
  title: string | null;
  image: string | null;
  default_image: string | null;
  type: string | null;
  target_id: string | number | null;
  slug: string | null;
}

/**
 * One entry in a `banners` / `sliders` section's `content.items`.
 *
 * NOT the same shape as `Banner` (the legacy /banners endpoint): the target is
 * `link_type` + `link_url`/`slug` here rather than `type` + per-kind `*_slug`
 * fields. Resolve the destination with `resolveSectionBannerHref`.
 */
export interface HomeLayoutBannerItem {
  id: number;
  /** Present for today's image banners and used as a poster for video slides. */
  image: string | null;
  /** Optional forward-compatible media fields from the home builder. */
  media_type?: "image" | "video" | string | null;
  video_url?: string | null;
  // Confirmed live: "custom". The builder also offers product/category/brand/store
  // and "none"; unknown values fall back to `link_url`, then to no link at all.
  link_type: string | null;
  link_url: string | null;
  target_id: number | null;
  slug: string | null;
}

export interface HomeLayoutSection {
  id: number;
  // "hero" | "products" | "categories" | "banners" | "sliders" | "brands" | "stores".
  // Anything else renders nothing (see HomeSectionRenderer's default branch).
  type: string;
  title: string | null; // the API returns null for untitled sections
  // Presentation hint, meaning is per-type. banners/sliders: "full" (one
  // edge-to-edge image per view) | "grid" (static, no swiper) | anything else
  // (a two-up rail). Products/categories ignore it today.
  style: string | null;
  // Sections with nothing configured send `[]` (PHP's empty array), not `{}` —
  // reads still work, but never assume the keys below exist.
  config: HomeLayoutSectionConfig;
  /** Legacy payloads may also expose this beside `config`. */
  background_image_style?: "half" | "full" | string | null;
  background_image: string | null;
  hero_media: HomeLayoutHeroMedia | string | null;
  card_background_image: string | null;
  // Keyed by content kind: { products } | { categories } | { items } (banners
  // and sliders) | { brands } | { stores }. Raw (pre-adapter) shape, so the
  // values are intentionally loose — run them through the `helpers/homeLayout`
  // adapters before rendering.
  content: Record<string, unknown[]>;
}

export interface HomeLayoutData {
  scope_type: string;
  scope_id: number | null;
  current_page: number;
  last_page: number;
  per_page: number;
  total: number; // total SECTIONS (drives section-list scroll pagination)
  sections: HomeLayoutSection[];
}

// Cart API Type

export interface CartApiResponse {
  success: boolean;
  message: string;
  data: CartResponse;
}

export interface CartResponse {
  id: number;
  uuid: string;
  user_id: number;
  items_count: number;
  total_quantity: number;
  items: CartItem[];
  removed_items: {
    product_name: string;
    variant_name: string;
    store_name: string;
    quantity: number;
    reason: string;
  }[];
  removed_count: number;
  payment_summary: PaymentSummary;
  delivery_zone: DeliveryZone;
  notification_set?: boolean;
  created_at: string;
  updated_at: string;
}

export interface FailedCartItem {
  store_id: number;
  product_variant_id: number;
  quantity?: number;
  product: {
    id: number;
    store_id: number;
    sku: string;
    price: number;
    special_price: number;
    cost: string;
    stock: number;
  };
  product_name?: string;
  product_image?: string;
  variant_name?: string;
  store_name?: string;
  reason: string;
}

export interface CartSyncData {
  cart: CartResponse;
  synced_items: {
    store_id: number;
    product_variant_id: number;
    quantity: number;
    product: {
      id: number;
      store_id: number;
      sku: string;
      price: number;
      special_price: number;
      cost: string;
      stock: number;
    };
  }[];
  failed_items: FailedCartItem[];
}

export interface CartItemAddon {
  addon_group_id: number;
  addon_item_id: number;
  title: string;
  price: number;
}

export interface CartItem {
  id: number;
  cart_id: number;
  product_id: number;
  product_variant_id: number;
  store_id: number;
  quantity: number;
  save_for_later: boolean;
  notification_set?: boolean;
  product: {
    id: number;
    name: string | null;
    slug: string;
    image: string;
    minimum_order_quantity: number;
    quantity_step_size: number;
    total_allowed_quantity: number;
    is_attachment_required?: boolean;
    attachment_mode?: string;
  };
  variant: {
    id: number;
    title: string;
    slug: string;
    image: string;
    price: string | number;
    special_price: string | number;
    stock: number;
    sku: string;
    is_addons?: boolean;
  };
  store: {
    id: number;
    name: string;
    slug: string;
    total_products: number;
    status: {
      is_open: boolean;
      status: string;
    };
    is_scheduled_delivery_enabled?: boolean;
    is_scheduled_delivery?: boolean;
  };
  addons?: CartItemAddon[];
  total_item_price?: number;
  total_item_special_price?: number;
  created_at: string;
  updated_at: string;
}

/**
 * Rider-tip rules served inside cart `payment_summary` and on the order
 * detail. `enable_rider_tips` is the master switch — when false (or the
 * config is absent, on older backends) no tip UI renders.
 */
export interface TippingConfig {
  enable_rider_tips: boolean;
  min_tip_amount: number;
  preset_1: number;
  preset_2: number;
  preset_3: number;
  max_tip_amount: number;
}

export interface PaymentSummary {
  items_total: number;
  per_store_drop_off_fee: number;
  is_rush_delivery: boolean;
  is_rush_delivery_available: boolean;
  delivery_charges: number;
  regular_delivery_charge?: number;
  rush_delivery_charge?: number;
  handling_charges: number;
  cod_charges?: number;
  delivery_distance_charges: number;
  delivery_distance_km: number;
  total_stores: number;
  total_delivery_charges: number;
  estimated_delivery_time: number;
  use_wallet: boolean;
  wallet_balance: number;
  wallet_amount_used: number;
  /** Already included in `payable_amount` by the backend. */
  rider_tip_amount?: number;
  tipping_config?: TippingConfig;
  /** Discount already reflected in `payable_amount` when redeeming points. */
  loyalty_points_discount_amount?: number;
  loyalty_points_redeemed?: number;
  loyalty_summary?: CartLoyaltySummary;
  payable_amount: number;
  promo_code: string;
  promo_discount: number;
  promo_applied: PromoCode | [];
  promo_error: string | null;
}

/** Loyalty redemption context the cart endpoint returns per request. */
export type CartLoyaltySummary = {
  is_loyalty_enabled: boolean;
  user_points_balance: number;
  min_order_value_to_redeem: number;
  is_eligible_for_redemption: boolean;
  /** Points cap for THIS order (max_redemption_percentage already applied). */
  max_points_redeemable: number;
  loyalty_points_redeemed: number;
  loyalty_points_discount_amount: number;
  potential_points_to_earn: number;
};

export interface Wishlist {
  id: number;
  title: string;
  slug: string;
  items_count: number;
  items: WishlistItem[];
  created_at: string;
  updated_at: string;
}

export interface WishlistItem {
  id: number;
  wishlist_id: number;
  product: {
    id: number;
    title: string;
    slug: string;
    image: string;
    short_description: string;
  };
  variant: {
    id: number;
    sku: string | null;
    image: string;
    price: number | null;
  };
  store: {
    id: number;
    name: string;
    slug: string;
  };
  created_at: string;
  updated_at: string;
}

export interface WishTitle {
  id: number;
  title: string;
  slug: string;
  items_count: number;
  created_at: string;
}

// sssssssssssssssss

export interface DeliveryLocationResponse {
  delivery_boy: DeliveryBoyLocation;
  route: RouteDetails;
  order: Order;
}

/* ---------- DELIVERY BOY SECTION ---------- */
export interface DeliveryBoyLocation {
  success: boolean;
  message: string;
  data: DeliveryLocationData;
}

export interface DeliveryLocationData {
  id: number;
  delivery_boy_id: number;
  delivery_boy: DeliveryBoy;
  latitude: string;
  longitude: string;
  recorded_at: number;
  created_at: string;
  updated_at: string;
}

export interface DeliveryBoy {
  id: number;
  user_id: number;
  delivery_zone_id: number;
  status: string;
  full_name: string;
  address: string;
  driver_license: string[];
  driver_license_number: string;
  vehicle_type: string;
  vehicle_registration: string[];
  verification_status: string;
  verification_remark: string | null;
  created_at: string;
}

/* ---------- ROUTE SECTION ---------- */
export interface RouteDetails {
  total_distance: number;
  route: number[];
  route_details: RouteStoreDetails[];
}

export interface RouteStoreDetails {
  store_id: number | null;
  store_name: string;
  distance_from_customer?: number;
  distance_from_previous?: number;
  address: string;
  city: string;
  landmark: string;
  state: string;
  zipcode: string | null;
  country: string;
  country_code: string;
  latitude: number;
  longitude: number;
  is_collected: boolean;
}

// sssssssssssssssss

// FAQs
export interface FAQ {
  id: number;
  question: string;
  answer: string;
  status: "active" | "inactive";
  created_at: string;
  updated_at: string;
}

export interface PromoCode {
  id: number;
  code: string;
  description: string;
  start_date: string;
  end_date: string;
  discount_type: "flat" | "percent" | "free_shipping";
  discount_amount: string;
  promo_mode: "instant" | "cashback";
  usage_count: number;
  individual_use: number;
  max_total_usage: number;
  max_usage_per_user: number;
  min_order_total: string;
  max_discount_value: string;
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
}

// Delivery Zone
export interface BoundaryPoint {
  lat: number;
  lng: number;
}

export interface DeliveryZone {
  id: number;
  zone_id: number;
  name: string;
  slug: string;
  center_latitude: string;
  center_longitude: string;
  radius_km: number;
  boundary_json: BoundaryPoint[];
  rush_delivery_enabled: boolean;
  delivery_time_per_km: number;
  rush_delivery_time_per_km: number;
  rush_delivery_charges: number;
  regular_delivery_charges: number;
  free_delivery_amount: number | null;
  distance_based_delivery_charges: number;
  per_store_drop_off_fee: number;
  handling_charges: number;
  buffer_time: number;
  status: "active" | "inactive" | string;
  delivery_boy_base_fee: string;
  delivery_boy_per_store_pickup_fee: string;
  delivery_boy_distance_based_fee: string;
  delivery_boy_per_order_incentive: string;
  created_at: string;
  updated_at: string;
}

export interface firebaseConfigType {
  apiKey: string;
  authDomain: string;
  databaseURL: string;
  projectId: string;
  storageBucket: string;
  messagingSenderId: string;
  appId: string;
  measurementId: string;
}

export interface RazorpayOrderData {
  amount: number;
  amount_due: number;
  amount_paid: number;
  attempts: number;
  created_at: number; // Unix timestamp
  currency: string; // e.g., "INR"
  entity: string; // usually "order"
  id: string; // e.g., "order_RF1iivHkU3Xbsi"
  notes: Record<string, string | number | boolean>[] | [];
  offer_id: string | null;
  receipt: string;
  status: "created" | "paid" | "attempted";
}
/**
 * Response of POST /xendit/create-order. Tolerant on purpose: the hosted
 * checkout URL may arrive under different keys depending on backend version.
 */
export interface XenditCreateOrderResponse {
  invoice_url?: string;
  checkout_url?: string;
  link?: string;
  url?: string;
  payment_response?: {
    link?: string;
    invoice_url?: string;
    checkout_url?: string;
    url?: string;
  };
}

export interface PaystackCreateOrderResponse {
  transaction: {
    transaction_id: string;
    uuid: string;
    order_id: string | null;
    user_id: number;
    amount: string;
    currency: string;
    payment_method: string;
    payment_status: string;
    message: string;
    payment_details: {
      user_id: number;
      amount: number;
      currency: string;
    };
    updated_at: string;
    created_at: string;
    id: number;
  };
  payment_response: {
    authorization_url: string;
    access_code: string;
    reference: string;
  };
}

export interface OrderUser {
  id: number;
  name: string;
  email: string;
}

export interface OrderCheckoutResponse {
  id: number;
  uuid: string;
  slug: string;
  user_id: number;
  email: string;
  currency_code: string;
  currency_rate: number;
  payment_method: string;
  payment_status: string;
  status: string;
  invoice: string;
  fulfillment_type: string;
  estimated_delivery_time: number;
  delivery_time_slot_id: number | null;
  delivery_boy_id: number | null;
  delivery_boy_name: string;
  delivery_boy_phone: number | string;
  delivery_boy_profile: string;
  is_delivery_feedback_given: boolean;
  delivery_feedback: string | null;
  wallet_balance: number;
  promo_code: string | null;
  promo_discount: number;
  gift_card: string | null;
  gift_card_discount: number;
  delivery_charge: number;
  subtotal: number;
  total_payable: number;
  final_total: number;
  shipping_name: string;
  shipping_address_1: string;
  shipping_address_2: string | null;
  shipping_landmark: string | null;
  shipping_zip: string;
  shipping_phone: string;
  shipping_address_type: string;
  shipping_latitude: number;
  shipping_longitude: number;
  shipping_city: string;
  shipping_state: string;
  shipping_country: string;
  shipping_country_code: string;
  order_note: string;
  items: OrderItem[];
  user: OrderUser;
  created_at: string;
  updated_at: string;
  payment_response?: {
    link: string;
  };
}

export interface SidebarFilters {
  categories_count: number;
  brands_count: number;
  attributes_count: number;
  categories: Category[];
  brands: Brand[];
  attributes: FilterAttribute[];
}

export interface FilterAttribute {
  title: string;
  slug: string;
  values: FilterAttributeValue[];
}

export interface FilterAttributeValue {
  id: number;
  title: string;
  swatche_value: string;
  enabled: boolean;
}
