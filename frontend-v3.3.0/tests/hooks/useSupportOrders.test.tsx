import { act, renderHook, waitFor } from "@testing-library/react";
import { expect, it, vi } from "vitest";
import { useSupportOrders } from "@/hooks/useSupportOrders";

const getOrders = vi.hoisted(() => vi.fn());
vi.mock("@/routes/api", () => ({ getOrders }));

it("loads order details once and continues pagination without losing them", async () => {
  getOrders
    .mockResolvedValueOnce({
      success: true,
      data: {
        total: 11,
        data: [
          {
            id: 12,
            slug: "order-12",
            status: "delivered",
            total_payable: "50",
            currency_code: "INR",
            created_at: "2026-09-12",
            payment_status: "paid",
            payment_method: "card",
            store: { name: "Market" },
            items: [
              {
                title: "Milk",
                quantity: 2,
                variant_title: "1 litre",
                store: { name: "Market" },
              },
            ],
          },
        ],
      },
    })
    .mockResolvedValueOnce({ success: true, data: { total: 11, data: [] } });
  const { result, rerender } = renderHook(() => useSupportOrders());
  await waitFor(() => expect(result.current.orders).toHaveLength(1));
  expect(result.current.orders[0]).toMatchObject({
    payment_status: "paid",
    payment_method: "card",
    store_names: ["Market"],
    items: [{ title: "Milk", quantity: 2, variant_title: "1 litre" }],
  });
  rerender();
  expect(getOrders).toHaveBeenCalledTimes(1);
  await act(async () => {
    await result.current.load();
  });
  expect(getOrders).toHaveBeenLastCalledWith({ page: 2, per_page: 10 });
  expect(result.current.orders).toHaveLength(1);
  expect(result.current.hasMore).toBe(false);
});
