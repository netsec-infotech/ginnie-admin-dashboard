import { describe, expect, it } from "vitest";
import {
  getActiveReturnRequest,
  getItemDisplayStatus,
  getReturnStatusColor,
} from "@/config/constants";
import type { OrderItem, OrderItemReturnRequest } from "@/types/ApiResponse";

/** Minimal return request — only the fields these helpers read. */
const makeReturn = (
  over: Partial<OrderItemReturnRequest> = {},
): OrderItemReturnRequest =>
  ({
    id: 1,
    return_status: "pending",
    created_at: "2026-07-28 10:00:00",
    updated_at: "2026-07-28 10:00:00",
    ...over,
  }) as OrderItemReturnRequest;

const makeItem = (
  status: string,
  returns: OrderItemReturnRequest[] = [],
): Pick<OrderItem, "status" | "returns"> =>
  ({ status, returns }) as Pick<OrderItem, "status" | "returns">;

describe("getItemDisplayStatus", () => {
  it("uses the item's own status when there are no returns", () => {
    const out = getItemDisplayStatus(makeItem("delivered"));
    expect(out).toMatchObject({ status: "delivered", isReturn: false });
  });

  it("lets a return supersede the item status", () => {
    // An item stays "delivered" forever, so without this the customer sees
    // nothing about the return they're actually waiting on.
    const out = getItemDisplayStatus(
      makeItem("delivered", [makeReturn({ return_status: "seller_approved" })]),
    );
    expect(out).toMatchObject({ status: "seller_approved", isReturn: true });
  });

  it.each([[[]], [undefined], [null]])(
    "falls back to the item status for %j returns",
    (returns) => {
      const item = {
        status: "delivered",
        returns,
      } as unknown as Pick<OrderItem, "status" | "returns">;
      expect(getItemDisplayStatus(item).isReturn).toBe(false);
    },
  );

  it("ignores a return with no status", () => {
    const out = getItemDisplayStatus(
      makeItem("delivered", [makeReturn({ return_status: "" })]),
    );
    expect(out).toMatchObject({ status: "delivered", isReturn: false });
  });
});

describe("getActiveReturnRequest", () => {
  it("picks the most recently updated, not the first", () => {
    // Array order isn't guaranteed, and an item can accumulate several
    // requests — a rejected one followed by a fresh attempt.
    const older = makeReturn({ id: 1, updated_at: "2026-07-20 09:00:00" });
    const newer = makeReturn({ id: 2, updated_at: "2026-07-28 15:59:19" });

    expect(getActiveReturnRequest(makeItem("delivered", [older, newer]))?.id).toBe(2);
    expect(getActiveReturnRequest(makeItem("delivered", [newer, older]))?.id).toBe(2);
  });

  it("falls back to created_at when updated_at is missing", () => {
    const a = makeReturn({ id: 1, updated_at: "", created_at: "2026-07-20 09:00:00" });
    const b = makeReturn({ id: 2, updated_at: "", created_at: "2026-07-28 09:00:00" });
    expect(getActiveReturnRequest(makeItem("delivered", [a, b]))?.id).toBe(2);
  });

  it("survives unparseable dates rather than returning nothing", () => {
    const bad = makeReturn({ id: 1, updated_at: "not-a-date", created_at: "" });
    const good = makeReturn({ id: 2, updated_at: "2026-07-28 15:59:19" });
    expect(getActiveReturnRequest(makeItem("delivered", [bad, good]))?.id).toBe(2);
    expect(getActiveReturnRequest(makeItem("delivered", [good, bad]))?.id).toBe(2);
  });

  it("returns null when there is nothing to show", () => {
    expect(getActiveReturnRequest(makeItem("delivered"))).toBeNull();
    expect(getActiveReturnRequest(null)).toBeNull();
  });
});

describe("getReturnStatusColor", () => {
  it.each([
    ["pending", "warning"],
    ["seller_approved", "primary"],
    ["refund_processed", "success"],
    ["store_rejected", "danger"],
  ])("maps the real backend status %j to %j", (status, expected) => {
    expect(getReturnStatusColor(status)).toBe(expected);
  });

  it("is case-insensitive", () => {
    expect(getReturnStatusColor("SELLER_APPROVED")).toBe("primary");
  });

  it("greys out anything unrecognised instead of throwing", () => {
    expect(getReturnStatusColor("brand_new_status")).toBe("default");
    expect(getReturnStatusColor(undefined)).toBe("default");
    expect(getReturnStatusColor(null)).toBe("default");
  });
});
