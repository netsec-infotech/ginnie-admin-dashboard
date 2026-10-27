import type { ReactNode } from "react";
import { act, fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import FlutterwavePayment from "@/components/PaymentGateway/FlutterwavePayment";
import XenditPayment from "@/components/PaymentGateway/XenditPayment";

const checkout = vi.hoisted(() => vi.fn());
vi.mock("@/helpers/functionalHelpers", () => ({ handleCheckout: checkout }));
vi.mock("@heroui/react", () => ({
  Button: ({
    onPress,
    isDisabled,
    children,
  }: {
    onPress: () => void;
    isDisabled?: boolean;
    children: ReactNode;
  }) => (
    <button disabled={isDisabled} onClick={onPress}>
      {children}
    </button>
  ),
}));
beforeEach(() => vi.clearAllMocks());

describe.each([FlutterwavePayment, XenditPayment])(
  "hosted checkout",
  (Payment) => {
    it("does not replace the gateway redirect with the success route", async () => {
      checkout.mockResolvedValue({ success: true, message: "Redirected" });
      const onSuccess = vi.fn(),
        onError = vi.fn(),
        setIsLoading = vi.fn();
      render(
        <Payment
          onSuccess={onSuccess}
          onError={onError}
          isLoading={false}
          setIsLoading={setIsLoading}
        />,
      );
      await act(async () => {
        fireEvent.click(screen.getByRole("button"));
      });
      expect(onSuccess).not.toHaveBeenCalled();
      expect(onError).not.toHaveBeenCalled();
      expect(setIsLoading).not.toHaveBeenCalledWith(false);
      await act(async () => {
        fireEvent.click(screen.getByRole("button"));
      });
      expect(checkout).toHaveBeenCalledOnce();
    });
    it("reports a rejected checkout and allows retry", async () => {
      checkout.mockResolvedValue({ success: false, message: "Out of stock" });
      const onSuccess = vi.fn(),
        onError = vi.fn(),
        setIsLoading = vi.fn();
      render(
        <Payment
          onSuccess={onSuccess}
          onError={onError}
          isLoading={false}
          setIsLoading={setIsLoading}
        />,
      );
      await act(async () => {
        fireEvent.click(screen.getByRole("button"));
      });
      expect(onSuccess).not.toHaveBeenCalled();
      expect(onError).toHaveBeenCalledOnce();
      expect(setIsLoading).toHaveBeenLastCalledWith(false);
      await act(async () => {
        fireEvent.click(screen.getByRole("button"));
      });
      expect(checkout).toHaveBeenCalledTimes(2);
    });
  },
);
