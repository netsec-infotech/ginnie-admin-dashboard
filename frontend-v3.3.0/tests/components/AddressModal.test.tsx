import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { SWRConfig } from "swr";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  addAddress: vi.fn(),
  editAddress: vi.fn(),
  getDeliveryZones: vi.fn(),
  checkDeliveryZone: vi.fn(),
  addToast: vi.fn(),
}));

vi.mock("@/routes/api", () => ({
  addAddress: mocks.addAddress,
  editAddress: mocks.editAddress,
  getDeliveryZones: mocks.getDeliveryZones,
  checkDeliveryZone: mocks.checkDeliveryZone,
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    defaultLocation: { lat: 23, lng: 69 },
    demoMode: false,
  }),
}));
vi.mock("@/lib/cookies", () => ({
  getCookie: () => ({ lat: 23, lng: 69 }),
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@heroui/react", () => ({
  addToast: mocks.addToast,
  Modal: ({ isOpen, children }: any) => (isOpen ? <div>{children}</div> : null),
  ModalContent: ({ children }: any) => <div>{children}</div>,
  ModalHeader: ({ children }: any) => <div>{children}</div>,
  ModalBody: ({ children }: any) => <div>{children}</div>,
  ModalFooter: ({ children }: any) => <div>{children}</div>,
  Input: ({ label, value }: any) => (
    <input aria-label={label} value={value} readOnly />
  ),
  Select: ({ label, children }: any) => (
    <div aria-label={label}>{children}</div>
  ),
  SelectItem: ({ children }: any) => <span>{children}</span>,
  Switch: ({ children }: any) => <span>{children}</span>,
  Button: ({ children, isDisabled, isLoading, onPress }: any) => (
    <button disabled={isDisabled || isLoading} onClick={onPress}>
      {children}
    </button>
  ),
}));
vi.mock("@/components/Location/LocationAutoComplete", () => ({
  default: () => <div data-testid="address-autocomplete" />,
}));
vi.mock("@/components/Location/GoogleMap", () => ({
  default: ({ zones, onLocationUpdate }: any) => (
    <div data-testid="address-map">
      <span>Rendered zones: {zones.length}</span>
      <button onClick={() => onLocationUpdate({ lat: 25, lng: 69 })}>
        Move outside zone
      </button>
    </div>
  ),
}));

import AddressModal from "@/components/Modals/AddressModal";

const deliveryZone = {
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
};

describe("AddressModal delivery zones", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.getDeliveryZones.mockResolvedValue({
      success: true,
      data: { data: [deliveryZone] },
    });
    Object.defineProperty(window, "google", {
      configurable: true,
      value: {
        maps: {
          Geocoder: class {
            geocode = vi.fn().mockResolvedValue({ results: [] });
          },
        },
      },
    });
  });

  it("renders zone geometry and blocks an address marker outside it", async () => {
    render(
      <SWRConfig value={{ dedupingInterval: 0, provider: () => new Map() }}>
        <AddressModal isOpen onOpenChange={vi.fn()} onSave={vi.fn()} />
      </SWRConfig>,
    );

    expect(await screen.findByText("Rendered zones: 1")).toBeInTheDocument();
    expect(mocks.getDeliveryZones).toHaveBeenCalledWith({ per_page: 100 });
    await waitFor(() => expect(screen.getByText("save")).toBeEnabled());

    fireEvent.click(screen.getByText("Move outside zone"));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "address.zoneMismatch",
    );
    expect(screen.getByText("save")).toBeDisabled();
    fireEvent.click(screen.getByText("save"));
    expect(mocks.addAddress).not.toHaveBeenCalled();
    expect(mocks.editAddress).not.toHaveBeenCalled();
  });
});
