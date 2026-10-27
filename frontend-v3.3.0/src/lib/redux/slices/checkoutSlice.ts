import { createSlice, PayloadAction } from "@reduxjs/toolkit";
import { Address } from "@/types/ApiResponse";
import { logout } from "@/lib/redux/slices/authSlice";

/**
 * A chosen delivery slot, as returned by
 * `/scheduled-delivery/stores/{id}/slots`.
 */
export interface ScheduledDelivery {
  /** Local YYYY-MM-DD. */
  date: string;
  /** String form of the slot id, kept for React keys and existing comparisons. */
  slotId: string;
  /**
   * The backend's `delivery_time_slot_id` — what createOrder must send.
   *
   * Optional because this state is persisted to localStorage: a session saved
   * before real slots existed has a generated `slotId` like "2026-07-19-08" and
   * no numeric id at all. Consumers must treat a missing value as "re-pick
   * required" rather than coercing the old string.
   */
  slotDbId?: number;
  /**
   * Raw "HH:MM:SS" bounds, set only on the cart contract, where slots have no
   * id and an order books a window by its times instead. Kept separate from
   * `startHour`/`endHour` (display-derived integers) because those lose the
   * minutes — a 14:30 slot would submit as "14:00:00" and book the wrong window.
   */
  slotStartTime?: string;
  slotEndTime?: string;
  startHour: number;
  endHour: number;
  /** Backend-formatted for display, e.g. "10:00 AM - 12:00 PM". */
  label: string;
  /** Store the slot belongs to — a slot is one store's promise, not portable. */
  storeId?: number;
}

export interface CheckoutState {
  selectedAddress: Address | null;
  orderNote: string;
  useWallet: boolean;
  useLoyaltyPoints: boolean;
  rushDelivery: boolean;
  promoCode: string;
  scheduledDelivery: ScheduledDelivery | null;
}

const initialState: CheckoutState = {
  selectedAddress: null,
  orderNote: "",
  useWallet: false,
  useLoyaltyPoints: false,
  rushDelivery: false,
  promoCode: "",
  scheduledDelivery: null,
};

const checkoutSlice = createSlice({
  name: "checkout",
  initialState,
  reducers: {
    setSelectedAddress: (state, action: PayloadAction<Address | null>) => {
      state.selectedAddress = action.payload;
    },
    setOrderNote: (state, action: PayloadAction<string>) => {
      state.orderNote = action.payload;
    },
    setUseWallet: (state, action: PayloadAction<boolean>) => {
      state.useWallet = action.payload;
    },
    setUseLoyaltyPoints: (state, action: PayloadAction<boolean>) => {
      state.useLoyaltyPoints = action.payload;
    },
    /**
     * Rush and a booked slot are mutually exclusive — "as soon as possible"
     * contradicts "at 1–3 PM on Thursday". Enforced in the reducers rather than
     * only in the UI: this is the one place neither can be bypassed, and the
     * backend accepts `rush_delivery` and `delivery_time_slot_id` independently.
     */
    setRusDelivery: (state, action: PayloadAction<boolean>) => {
      state.rushDelivery = action.payload;
      if (action.payload) state.scheduledDelivery = null;
    },
    setPromoCode: (state, action: PayloadAction<string>) => {
      state.promoCode = action.payload;
    },
    setScheduledDelivery: (
      state,
      action: PayloadAction<ScheduledDelivery | null>,
    ) => {
      state.scheduledDelivery = action.payload;
      if (action.payload) state.rushDelivery = false;
    },
    clearScheduledDelivery: (state) => {
      state.scheduledDelivery = null;
    },
  },
  extraReducers: (builder) => {
    builder.addCase(logout, () => initialState);
  },
});

export const {
  setSelectedAddress,
  setOrderNote,
  setUseWallet,
  setUseLoyaltyPoints,
  setRusDelivery,
  setPromoCode,
  setScheduledDelivery,
  clearScheduledDelivery,
} = checkoutSlice.actions;
export default checkoutSlice.reducer;
