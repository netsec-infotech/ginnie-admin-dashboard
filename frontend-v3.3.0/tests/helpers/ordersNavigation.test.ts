import { describe, expect, it, vi } from "vitest";

import { updateOrdersRoute } from "@/helpers/ordersNavigation";

describe("order list navigation", () => {
  it("updates filters and pagination with shallow routing", async () => {
    const push = vi.fn().mockResolvedValue(true);

    await updateOrdersRoute(
      { push } as Parameters<typeof updateOrdersRoute>[0],
      { status: "pending", page: "2" },
    );

    expect(push).toHaveBeenCalledWith(
      {
        pathname: "/my-account/orders",
        query: { status: "pending", page: "2" },
      },
      undefined,
      { shallow: true },
    );
  });
});
