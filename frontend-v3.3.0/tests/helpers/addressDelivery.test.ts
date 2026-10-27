import { beforeEach, describe, expect, it, vi } from "vitest";
const mocks = vi.hoisted(() => ({ check: vi.fn(), cookie: vi.fn() }));
vi.mock("@/routes/api", () => ({ checkDeliveryZone: mocks.check }));
vi.mock("@/lib/cookies", () => ({ getCookie: mocks.cookie }));
import { validateAddressDelivery } from "@/services/addressDeliveryService";
import {
  deliveryZoneContainsLocation,
  findDeliveryZoneForLocation,
} from "@/services/addressDeliveryService";
import type { DeliveryZone } from "@/types/ApiResponse";

const address = { latitude: 23.25, longitude: 69.66 };
const covered = (zone_id: number) => ({
  success: true,
  data: { is_deliverable: true, zone_id },
});
beforeEach(() => vi.resetAllMocks());

describe("delivery-zone validation", () => {
  it("checks only coordinates and ignores stale saved-address delivery flags", async () => {
    mocks.check.mockResolvedValue(covered(1));
    const savedAddress = {
      id: 175,
      latitude: 23.23471626,
      longitude: 69.64707716,
      is_deliverable: false,
      address_line1: "Saved address",
    };
    expect(await validateAddressDelivery(savedAddress, 1)).toEqual({
      success: true,
    });
    expect(mocks.check).toHaveBeenCalledExactlyOnceWith({
      latitude: savedAddress.latitude,
      longitude: savedAddress.longitude,
    });
  });
  it("accepts an address in the cart's zone", async () => {
    mocks.check.mockResolvedValue(covered(7));
    expect(await validateAddressDelivery(address, "7")).toEqual({
      success: true,
    });
    expect(mocks.check).toHaveBeenCalledOnce();
  });
  it("rejects an address that is deliverable in a different zone", async () => {
    mocks.check.mockResolvedValue(covered(8));
    expect(await validateAddressDelivery(address, 7)).toEqual({
      success: false,
      messageKey: "address.zoneMismatch",
    });
  });
  it("compares new addresses with the shopping location", async () => {
    mocks.cookie.mockReturnValue({ lat: 23.2, lng: 69.6 });
    mocks.check
      .mockResolvedValueOnce(covered(7))
      .mockResolvedValueOnce(covered(8));
    expect((await validateAddressDelivery(address)).success).toBe(false);
    expect(mocks.check).toHaveBeenCalledTimes(2);
  });
  it("blocks uncovered locations and failed checks", async () => {
    mocks.check.mockResolvedValue({
      success: true,
      data: { is_deliverable: false, zone_id: null },
    });
    expect((await validateAddressDelivery(address, 7)).success).toBe(false);
    mocks.check.mockResolvedValue({ success: false });
    expect(await validateAddressDelivery(address, 7)).toEqual({
      success: false,
      messageKey: "address.zoneCheckFailed",
    });
  });
  it("does not send invalid coordinates or assume a missing shopping location", async () => {
    expect(
      (await validateAddressDelivery({ latitude: "", longitude: 0 }, 7))
        .success,
    ).toBe(false);
    expect(await validateAddressDelivery(address)).toEqual({
      success: false,
      messageKey: "address.selectShoppingLocation",
    });
    expect(mocks.check).not.toHaveBeenCalled();
  });
});

const zone = (overrides: Partial<DeliveryZone> = {}) =>
  ({
    id: 7,
    zone_id: 7,
    name: "Test zone",
    slug: "test-zone",
    center_latitude: "23",
    center_longitude: "69",
    radius_km: 2,
    boundary_json: [
      { lat: 22, lng: 68 },
      { lat: 22, lng: 70 },
      { lat: 24, lng: 70 },
      { lat: 24, lng: 68 },
    ],
    status: "active",
    ...overrides,
  }) as DeliveryZone;

describe("client delivery-zone geometry", () => {
  it("accepts points inside or on the edge of a polygon", () => {
    expect(deliveryZoneContainsLocation(zone(), { lat: 23, lng: 69 })).toBe(
      true,
    );
    expect(deliveryZoneContainsLocation(zone(), { lat: 22, lng: 69 })).toBe(
      true,
    );
  });

  it("rejects points outside polygons and inactive zones", () => {
    expect(deliveryZoneContainsLocation(zone(), { lat: 25, lng: 69 })).toBe(
      false,
    );
    expect(
      deliveryZoneContainsLocation(zone({ status: "inactive" }), {
        lat: 23,
        lng: 69,
      }),
    ).toBe(false);
  });

  it("uses circular coverage when a polygon is unavailable", () => {
    const circularZone = zone({ boundary_json: [], radius_km: 2 });
    expect(
      deliveryZoneContainsLocation(circularZone, { lat: 23.01, lng: 69 }),
    ).toBe(true);
    expect(
      deliveryZoneContainsLocation(circularZone, { lat: 23.03, lng: 69 }),
    ).toBe(false);
  });

  it("finds the matching zone for an address marker", () => {
    const matching = zone({ id: 8, zone_id: 8 });
    expect(
      findDeliveryZoneForLocation([zone({ status: "inactive" }), matching], {
        lat: 23,
        lng: 69,
      }),
    ).toBe(matching);
  });
});
