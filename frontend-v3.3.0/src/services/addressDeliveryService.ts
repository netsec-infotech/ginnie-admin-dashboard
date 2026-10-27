import { getCookie } from "@/lib/cookies";
import { checkDeliveryZone } from "@/routes/api";
import type { DeliveryZone } from "@/types/ApiResponse";

type Coordinates = { latitude: string | number; longitude: string | number };
export type DeliveryCoordinates = { lat: number; lng: number };
export type AddressDeliveryResult =
  | { success: true }
  | {
      success: false;
      messageKey:
        | "address.zoneMismatch"
        | "address.zoneCheckFailed"
        | "address.selectShoppingLocation";
    };

const validCoordinates = ({ latitude, longitude }: Coordinates) =>
  (typeof latitude === "number" || typeof latitude === "string") &&
  (typeof longitude === "number" || typeof longitude === "string") &&
  String(latitude).trim() !== "" &&
  String(longitude).trim() !== "" &&
  Number.isFinite(Number(latitude)) &&
  Math.abs(Number(latitude)) <= 90 &&
  Number.isFinite(Number(longitude)) &&
  Math.abs(Number(longitude)) <= 180;

const normalizeBoundary = (zone: DeliveryZone): DeliveryCoordinates[] => {
  const rawBoundary = zone.boundary_json as unknown;
  try {
    const boundary = Array.isArray(rawBoundary)
      ? rawBoundary
      : typeof rawBoundary === "string"
        ? JSON.parse(rawBoundary)
        : [];
    if (!Array.isArray(boundary)) return [];

    return boundary.flatMap((point) => {
      const lat = Number((point as { lat?: unknown }).lat);
      const lng = Number((point as { lng?: unknown }).lng);
      return Number.isFinite(lat) && Number.isFinite(lng) ? [{ lat, lng }] : [];
    });
  } catch {
    return [];
  }
};

const isPointOnSegment = (
  point: DeliveryCoordinates,
  start: DeliveryCoordinates,
  end: DeliveryCoordinates,
) => {
  const cross =
    (point.lat - start.lat) * (end.lng - start.lng) -
    (point.lng - start.lng) * (end.lat - start.lat);
  if (Math.abs(cross) > 1e-9) return false;

  return (
    point.lat >= Math.min(start.lat, end.lat) - 1e-9 &&
    point.lat <= Math.max(start.lat, end.lat) + 1e-9 &&
    point.lng >= Math.min(start.lng, end.lng) - 1e-9 &&
    point.lng <= Math.max(start.lng, end.lng) + 1e-9
  );
};

const isPointInPolygon = (
  point: DeliveryCoordinates,
  boundary: DeliveryCoordinates[],
) => {
  let inside = false;
  for (
    let index = 0, previous = boundary.length - 1;
    index < boundary.length;
    previous = index++
  ) {
    const start = boundary[previous];
    const end = boundary[index];
    if (isPointOnSegment(point, start, end)) return true;

    const intersects =
      start.lat > point.lat !== end.lat > point.lat &&
      point.lng <
        ((end.lng - start.lng) * (point.lat - start.lat)) /
          (end.lat - start.lat) +
          start.lng;
    if (intersects) inside = !inside;
  }
  return inside;
};

const distanceInKilometers = (
  start: DeliveryCoordinates,
  end: DeliveryCoordinates,
) => {
  const toRadians = (value: number) => (value * Math.PI) / 180;
  const earthRadiusKm = 6371;
  const latitudeDelta = toRadians(end.lat - start.lat);
  const longitudeDelta = toRadians(end.lng - start.lng);
  const startLatitude = toRadians(start.lat);
  const endLatitude = toRadians(end.lat);
  const haversine =
    Math.sin(latitudeDelta / 2) ** 2 +
    Math.cos(startLatitude) *
      Math.cos(endLatitude) *
      Math.sin(longitudeDelta / 2) ** 2;
  return (
    earthRadiusKm *
    2 *
    Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine))
  );
};

/** Mirrors the polygon/circle rendered by GoogleMap for immediate client feedback. */
export const deliveryZoneContainsLocation = (
  zone: DeliveryZone,
  location: DeliveryCoordinates,
) => {
  if (zone.status === "inactive") return false;

  const boundary = normalizeBoundary(zone);
  if (boundary.length >= 3) return isPointInPolygon(location, boundary);

  const center = {
    lat: Number(zone.center_latitude),
    lng: Number(zone.center_longitude),
  };
  const radiusKm = Number(zone.radius_km);
  if (
    !Number.isFinite(center.lat) ||
    !Number.isFinite(center.lng) ||
    !Number.isFinite(radiusKm) ||
    radiusKm <= 0
  )
    return false;

  return distanceInKilometers(center, location) <= radiusKm;
};

export const findDeliveryZoneForLocation = (
  zones: readonly DeliveryZone[],
  location: DeliveryCoordinates,
) => zones.find((zone) => deliveryZoneContainsLocation(zone, location)) ?? null;

export const getDeliveryZoneIdentity = (zone: DeliveryZone) =>
  String(zone.zone_id ?? zone.id);

/** Compare actual coverage, not city names: two deliverable addresses can be in different zones. */
export const validateAddressDelivery = async (
  address: Coordinates,
  expectedZoneId?: string | number | null,
): Promise<AddressDeliveryResult> => {
  if (!validCoordinates(address))
    return { success: false, messageKey: "address.zoneCheckFailed" };
  let zoneId = expectedZoneId;
  if (!zoneId) {
    const location = getCookie("userLocation") as
      | { lat?: string | number; lng?: string | number }
      | undefined;
    const reference = {
      latitude: location?.lat ?? "",
      longitude: location?.lng ?? "",
    };
    if (!validCoordinates(reference))
      return { success: false, messageKey: "address.selectShoppingLocation" };
    const current = await checkDeliveryZone(reference);
    if (
      !current.success ||
      !current.data?.is_deliverable ||
      !current.data.zone_id
    ) {
      return { success: false, messageKey: "address.zoneCheckFailed" };
    }
    zoneId = current.data.zone_id;
  }
  const result = await checkDeliveryZone({
    latitude: address.latitude,
    longitude: address.longitude,
  });
  if (!result.success || !result.data)
    return { success: false, messageKey: "address.zoneCheckFailed" };
  if (
    !result.data.is_deliverable ||
    String(result.data.zone_id) !== String(zoneId)
  ) {
    return { success: false, messageKey: "address.zoneMismatch" };
  }
  return { success: true };
};
