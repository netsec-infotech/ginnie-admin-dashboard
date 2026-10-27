import { createSlice, type PayloadAction } from "@reduxjs/toolkit";
import { getLocalSnapshot, localLanguages, LOCAL_DEFAULT } from "@/lib/localization/data";
import type { LocalizationSnapshot, WebLanguage } from "@/types/localization";

const initialSnapshot = getLocalSnapshot(LOCAL_DEFAULT)!;

export type LocalizationState = {
  languages: WebLanguage[];
  defaultLocale: string;
  active: LocalizationSnapshot;
  status: "local" | "api";
  switchStatus: "idle" | "loading";
  pendingLocale: string | null;
  error: boolean;
  requestId: number;
};

const initialState: LocalizationState = {
  languages: localLanguages,
  defaultLocale: LOCAL_DEFAULT,
  active: initialSnapshot,
  status: "local",
  switchStatus: "idle",
  pendingLocale: null,
  error: false,
  requestId: 0,
};

const slice = createSlice({
  name: "localization",
  initialState,
  reducers: {
    languagesReceived(state, action: PayloadAction<{ languages: WebLanguage[]; defaultLocale: string }>) {
      state.languages = action.payload.languages;
      state.defaultLocale = action.payload.defaultLocale;
    },
    activateLanguage(state, action: PayloadAction<LocalizationSnapshot>) {
      state.active = action.payload;
      state.status = action.payload.source;
      state.error = false;
    },
    switchStarted(state, action: PayloadAction<{ locale: string; requestId: number }>) {
      state.switchStatus = "loading";
      state.pendingLocale = action.payload.locale;
      state.requestId = action.payload.requestId;
      state.error = false;
    },
    switchFinished(state, action: PayloadAction<{ requestId: number; snapshot?: LocalizationSnapshot }>) {
      if (state.requestId !== action.payload.requestId) return;
      if (action.payload.snapshot) {
        state.active = action.payload.snapshot;
        state.status = action.payload.snapshot.source;
        state.error = false;
      } else {
        state.error = true;
      }
      state.switchStatus = "idle";
      state.pendingLocale = null;
    },
    localizationFailed(state) {
      state.error = true;
    },
  },
});

export const {
  languagesReceived,
  activateLanguage,
  switchStarted,
  switchFinished,
  localizationFailed,
} = slice.actions;
export default slice.reducer;
