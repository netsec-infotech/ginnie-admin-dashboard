import { act, renderHook, waitFor } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
import { SWRConfig } from "swr";
import type { PropsWithChildren } from "react";
import type { Product, ProductVariant } from "@/types/ApiResponse";
import { useStorePriceRefresh } from "@/hooks/useStorePriceRefresh";

const api = vi.hoisted(() => ({ getProduct: vi.fn() }));
vi.mock("@/routes/api", () => ({ getProductBySlug: api.getProduct }));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ settings: [] }),
}));
vi.mock("@/helpers/getters", () => ({
  getWebSettings: () => ({}),
  getConfiguredDefaultLocation: () => ({ lat: 1, lng: 2 }),
}));
vi.mock("@/lib/cookies", () => ({ getCookie: () => undefined }));

const first = {
  id: 1,
  store_id: 10,
  price: 100,
  currency_code: "AED",
} as ProductVariant;
const second = {
  id: 2,
  store_id: 20,
  price: 50,
  currency_code: "USD",
} as ProductVariant;
const product = { slug: "rice", variants: [first, second] } as Product;
const wrapper = ({ children }: PropsWithChildren) => (
  <SWRConfig value={{ provider: () => new Map() }}>{children}</SWRConfig>
);
beforeEach(() => api.getProduct.mockReset());

it("refreshes the quote for the selected store and keeps its currency", async () => {
  const onRefresh = vi.fn();
  const quote = {
    ...second,
    price: 60,
    formatted_display_price: "USD $ 60.00",
  };
  api.getProduct.mockResolvedValue({
    success: true,
    data: { ...product, variants: [quote] },
  });
  const hook = renderHook(
    ({ variant }) => useStorePriceRefresh(product, variant, onRefresh),
    {
      initialProps: { variant: first },
      wrapper,
    },
  );
  expect(api.getProduct).not.toHaveBeenCalled();
  hook.rerender({ variant: second });
  await waitFor(() => expect(onRefresh).toHaveBeenCalledWith(quote));
  expect(api.getProduct).toHaveBeenCalledWith({
    slug: "rice",
    store_id: 20,
    latitude: 1,
    longitude: 2,
  });
});

it("ignores a delayed quote after the customer switches to another store", async () => {
  const onRefresh = vi.fn();
  let resolveSecond!: (response: { success: boolean; data: Product }) => void;
  api.getProduct.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        resolveSecond = resolve;
      }),
  );
  api.getProduct.mockResolvedValueOnce({ success: true, data: product });
  const hook = renderHook(
    ({ variant }) => useStorePriceRefresh(product, variant, onRefresh),
    {
      initialProps: { variant: first },
      wrapper,
    },
  );
  hook.rerender({ variant: second });
  await waitFor(() => expect(api.getProduct).toHaveBeenCalledTimes(1));
  hook.rerender({ variant: first });
  await waitFor(() => expect(onRefresh).toHaveBeenCalledWith(first));
  await act(async () => {
    resolveSecond({ success: true, data: product });
  });
  expect(onRefresh).not.toHaveBeenCalledWith(second);
});
