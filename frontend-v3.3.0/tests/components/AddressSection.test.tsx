import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { PropsWithChildren } from "react";
import AddressSection from "@/components/Cart/AddressSection";

const mocks = vi.hoisted(() => ({
  confirm: vi.fn(), refresh: vi.fn(), dispatch: vi.fn(),
  oldAddress: { id: 98, latitude: 38.5, longitude: -106.9, address_type: "home", address_line1: "Old address" },
  candidate: { id: 175, latitude: 23.23471626, longitude: 69.64707716, address_type: "home", address_line1: "New address" },
}));
vi.mock("@/helpers/cartLocationConfirmation", () => ({ confirmCartLocation: mocks.confirm }));
vi.mock("@/helpers/updators", () => ({ updateCartData: mocks.refresh, isEmptyCartMessage: () => false }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key: string) => key }) }));
vi.mock("react-redux", () => ({
  useDispatch: () => mocks.dispatch,
  useSelector: (select: (state: unknown) => unknown) => select({
    cart: { cartData: { delivery_zone: { zone_id: 1 } } },
    checkout: { selectedAddress: mocks.oldAddress },
  }),
}));
vi.mock("@/lib/cookies", () => ({ getCookie: () => ({ lat: 23, lng: 69 }) }));
vi.mock("swr", () => ({
  default: () => ({ data: { addresses: [mocks.oldAddress], total: 2 }, isLoading: false }),
  mutate: vi.fn(),
}));
vi.mock("@/routes/api", () => ({ getAddresses: async () => ({
  success: true, data: { data: [mocks.oldAddress, mocks.candidate] },
}) }));
vi.mock("@heroui/react", async () => {
  const { useState } = await import("react");
  const Box = ({ children }: PropsWithChildren) => <div>{children}</div>;
  return {
    Card: Box, CardBody: Box, CardHeader: Box, Chip: Box,
    Button: ({ children, onPress, isDisabled }: PropsWithChildren<{ onPress?: () => void; isDisabled?: boolean }>) =>
      <button onClick={onPress} disabled={isDisabled}>{children}</button>,
    useDisclosure: () => {
      const [isOpen, setOpen] = useState(false);
      return { isOpen, onOpen: () => setOpen(true), onClose: () => setOpen(false), onOpenChange: () => setOpen((open) => !open) };
    },
  };
});
vi.mock("@/components/Modals/AddressSelectionModal", () => ({
  default: (props: {
    isOpen: boolean; isLoading: boolean; isConfirming: boolean; error?: string;
    handleModalSelection: (id: string) => void; handleConfirmSelection: () => void;
  }) => props.isOpen ? <div role="dialog">
    <button disabled={props.isLoading} onClick={() => props.handleModalSelection("175")}>Choose new address</button>
    <button disabled={props.isConfirming} onClick={props.handleConfirmSelection}>Confirm selection</button>
    {props.isConfirming && <span>Applying address</span>}
    {props.error && <p role="alert">{props.error}</p>}
  </div> : null,
}));

beforeEach(() => {
  vi.resetAllMocks();
  mocks.confirm.mockResolvedValue(true);
});

async function selectAddress() {
  render(<AddressSection onAddAddressModalOpen={vi.fn()} />);
  fireEvent.click(screen.getByText("address.selectAddress"));
  await waitFor(() => expect(screen.getByText("Choose new address")).toBeEnabled());
  fireEvent.click(screen.getByText("Choose new address"));
  fireEvent.click(screen.getByText("Confirm selection"));
}

describe("address selection", () => {
  it("keeps the confirm button visible and disabled while applying the address", async () => {
    let resolveRefresh!: (result: { success: boolean }) => void;
    mocks.refresh.mockReturnValue(new Promise((resolve) => { resolveRefresh = resolve; }));
    await selectAddress();
    await waitFor(() => expect(mocks.refresh).toHaveBeenCalledOnce());
    expect(screen.getByText("Applying address")).toBeInTheDocument();
    expect(screen.getByText("Confirm selection")).toBeDisabled();
    resolveRefresh({ success: true });
    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
  });

  it("passes the candidate coordinates, updates selection, and closes after success", async () => {
    mocks.refresh.mockResolvedValue({ success: true });
    await selectAddress();
    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
    expect(mocks.refresh).toHaveBeenCalledWith(false, false, {
      id: 175, latitude: 23.23471626, longitude: 69.64707716,
    });
    expect(mocks.dispatch).toHaveBeenCalledWith(expect.objectContaining({
      type: "checkout/setSelectedAddress", payload: mocks.candidate,
    }));
  });

  it.each([undefined, { success: false }])("shows a retry message and preserves the old address when refresh fails: %j", async (result) => {
    mocks.refresh.mockResolvedValue(result);
    await selectAddress();
    expect(await screen.findByRole("alert")).toHaveTextContent("address.selectionFailed");
    expect(screen.getByText("Confirm selection")).toBeEnabled();
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    expect(mocks.dispatch).not.toHaveBeenCalled();
  });

  it("does not change the address after cancelling removal consent", async () => {
    mocks.confirm.mockResolvedValue(false);
    await selectAddress();
    await waitFor(() => expect(screen.getByText("Confirm selection")).toBeEnabled());
    expect(mocks.refresh).not.toHaveBeenCalled();
    expect(mocks.dispatch).not.toHaveBeenCalled();
  });
});
