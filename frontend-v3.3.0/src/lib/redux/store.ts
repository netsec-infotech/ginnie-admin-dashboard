import { combineReducers, configureStore } from "@reduxjs/toolkit";
import { persistStore, persistReducer } from "redux-persist";
import storage from "redux-persist/lib/storage";
import authSlice from "./slices/authSlice";
import cartSlice from "./slices/cartSlice";
import checkoutSlice from "./slices/checkoutSlice";
import offlineCartSlice from "./slices/offlineCartSlice";
import recentlyViewedSlice from "./slices/recentlyViewedSlice";
import localizationSlice from "./slices/localizationSlice";
import type { LocalizationSnapshot, WebLanguage } from "@/types/localization";
import { activateLanguage, languagesReceived } from "./slices/localizationSlice";

const persistConfig = {
  key: "root",
  storage,
  whitelist: ["auth", "cart", "checkout", "offlineCart", "recentlyViewed"],
};

const rootReducer = combineReducers({
  auth: authSlice,
  cart: cartSlice,
  checkout: checkoutSlice,
  offlineCart: offlineCartSlice,
  recentlyViewed: recentlyViewedSlice,
  localization: localizationSlice,
});

export function createServerStore(
  snapshot: LocalizationSnapshot,
  languages: WebLanguage[],
  defaultLocale: string,
) {
  const serverStore = configureStore({ reducer: rootReducer });
  serverStore.dispatch(languagesReceived({ languages, defaultLocale }));
  serverStore.dispatch(activateLanguage(snapshot));
  return serverStore;
}

const persistedReducer = persistReducer(persistConfig, rootReducer);

export const store = configureStore({
  reducer: persistedReducer,
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: {
        ignoredActions: ["persist/PERSIST"],
        ignoredPaths: ["register"],
      },
    }),
});

export const persistor = persistStore(store);

export type AppStore = typeof store;
// Infer the `RootState` and `AppDispatch` types from the store itself
export type RootState = ReturnType<AppStore["getState"]>;
export type AppDispatch = AppStore["dispatch"];
