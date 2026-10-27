// src/lib/redux/reducers/authSlice.ts
import { userData } from "@/types/ApiResponse";
import { createSlice, PayloadAction } from "@reduxjs/toolkit";

interface AuthState {
  isLoggedIn: boolean;
  access_token: string;
  user: null | userData;
}

const initialState: AuthState = {
  isLoggedIn: false,
  access_token: "",
  user: null,
};

interface LoginPayload {
  user: userData;
  access_token: string;
}

const authSlice = createSlice({
  name: "auth",
  initialState,
  reducers: {
    login: (state, action: PayloadAction<LoginPayload>) => {
      state.isLoggedIn = true;
      state.user = action.payload.user;
      state.access_token = action.payload.access_token;
    },
    logout: (state) => {
      state.isLoggedIn = false;
      state.user = null;
      state.access_token = "";
    },
    setUserDataRedux: (state, action: PayloadAction<Partial<userData>>) => {
      // Profile patches are valid only inside an established session. This is
      // a final state-boundary guard against delayed requests recreating user
      // data after logout.
      if (!state.isLoggedIn || !state.access_token) return;
      state.user = state.user
        ? { ...state.user, ...action.payload }
        : (action.payload as userData);
    },
  },
});

export const { login, logout, setUserDataRedux } = authSlice.actions;
export default authSlice.reducer;
