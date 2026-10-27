import { createSlice, PayloadAction } from "@reduxjs/toolkit";
import { CartResponse } from "@/types/ApiResponse";
import type { ImportedCart } from "@/types/sharedCart";

interface CartState {
  cartData: CartResponse | null;
  pendingImportedCart?: ImportedCart | null;
  isLoading: boolean;
  error: string | null;
  deliveryLocationNeedsSelection?: boolean;
}

const initialState: CartState = {
  cartData: null,
  pendingImportedCart: null,
  isLoading: false,
  error: null,
  deliveryLocationNeedsSelection: false,
};

const cartSlice = createSlice({
  name: "cart",
  initialState,
  reducers: {
    setCartData: (state, action: PayloadAction<CartResponse>) => {
      state.cartData = action.payload;
      state.pendingImportedCart = null;
      state.error = null;
    },
    setImportedCart: (state, action: PayloadAction<ImportedCart>) => {
      state.pendingImportedCart = action.payload;
      state.cartData = null;
      state.error = null;
      state.deliveryLocationNeedsSelection = true;
    },
    setCartLoading: (state, action: PayloadAction<boolean>) => {
      state.isLoading = action.payload;
    },
    setError: (state, action: PayloadAction<string>) => {
      state.error = action.payload;
    },
    setDeliveryLocationNeedsSelection: (state, action: PayloadAction<boolean>) => {
      state.deliveryLocationNeedsSelection = action.payload;
    },
    clearCart: (state) => {
      state.cartData = null;
      state.pendingImportedCart = null;
      state.error = null;
      state.deliveryLocationNeedsSelection = false;
    },
  },
});

export const { setCartData, setImportedCart, setCartLoading, setError, clearCart, setDeliveryLocationNeedsSelection } =
  cartSlice.actions;
export default cartSlice.reducer;
