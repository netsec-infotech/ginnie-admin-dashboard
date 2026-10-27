import { render, waitFor } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import type { DeliveryLocationResponse } from "@/types/ApiResponse";
vi.mock("leaflet/dist/leaflet.css", () => ({}));

const { popups, fitBounds } = vi.hoisted(() => ({
  popups: [] as Array<string | HTMLElement>,
  fitBounds: vi.fn(),
}));
vi.mock("leaflet", () => {
  const layer = () => ({
    addTo() { return this; },
    remove: vi.fn(), setLatLng: vi.fn(), setIcon: vi.fn(), setOpacity: vi.fn(),
    bindPopup(content: string | HTMLElement) { popups.push(content); return this; },
  });
  return { default: {
    map: () => ({ fitBounds }), marker: layer, tileLayer: layer,
    polyline: layer, divIcon: vi.fn(), latLngBounds: vi.fn(),
  } };
});
import OpenStreetMapTracking from "@/components/Location/OpenStreetMapTracking";

afterEach(() => { vi.unstubAllGlobals(); popups.length = 0; vi.clearAllMocks(); });

it("renders rider, store and address as literal text while creating route markers", async () => {
  vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false }));
  const payload = '<img src=x onerror="alert(1)"> & Main';
  const data = {
    order: { id: 1, shipping_latitude: "12", shipping_longitude: "77", shipping_address_1: payload },
    delivery_boy: { data: { latitude: "12.1", longitude: "77.1", delivery_boy: { full_name: payload } } },
    route: { route_details: [{ store_id: 1, store_name: payload, latitude: 12.2, longitude: 77.2, is_collected: false }] },
  } as unknown as DeliveryLocationResponse;
  render(<OpenStreetMapTracking data={data} useTransportLayer={false} />);
  await waitFor(() => expect(popups).toHaveLength(3));
  for (const content of popups) {
    const root = document.createElement("div");
    if (typeof content === "string") root.innerHTML = content;
    else root.append(content);
    expect(root.querySelector("img")).toBeNull();
    expect(root.querySelector("p")?.textContent).toBe(payload);
    expect(root.querySelector("strong")).not.toBeNull();
  }
  expect(fitBounds).toHaveBeenCalled();
});
