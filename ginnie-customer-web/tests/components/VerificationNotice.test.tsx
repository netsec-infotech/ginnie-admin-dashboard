import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { Provider } from "react-redux";
import { configureStore } from "@reduxjs/toolkit";

import VerificationNotice from "@/components/Cart/VerificationNotice";
import { userData } from "@/types/ApiResponse";

const push = vi.fn();
vi.mock("next/router", () => ({ useRouter: () => ({ push }) }));

/**
 * Minimal store: the component only reads `auth.user`, so a stub reducer keeps
 * this from depending on the real slice's shape.
 */
const renderWith = (user: Partial<userData> | null) => {
  const store = configureStore({
    reducer: { auth: (state = { user }) => state },
  });
  return render(
    <Provider store={store}>
      <VerificationNotice />
    </Provider>,
  );
};

const VERIFIED_AT = "2026-07-01T10:00:00.000Z";
const base = { id: 1, name: "Test", email: "t@example.com", mobile: "98765" };

describe("VerificationNotice", () => {
  it("renders nothing when the account is fully verified", () => {
    const { container } = renderWith({
      ...base,
      email_verified_at: VERIFIED_AT,
      mobile_verified_at: VERIFIED_AT,
    });
    expect(container).toBeEmptyDOMElement();
  });

  it("renders nothing when nobody is signed in", () => {
    // Otherwise the cart would warn a logged-out visitor about an account
    // they don't have.
    const { container } = renderWith(null);
    expect(container).toBeEmptyDOMElement();
  });

  it("warns when neither email nor mobile is verified", () => {
    renderWith(base);
    expect(screen.getByTestId("cart-verification-notice")).toHaveTextContent(
      /verify your email and mobile/i,
    );
  });

  it("names only the outstanding item — email", () => {
    renderWith({ ...base, mobile_verified_at: VERIFIED_AT });
    const notice = screen.getByTestId("cart-verification-notice");
    expect(notice).toHaveTextContent(/verify your email to place an order/i);
    expect(notice).not.toHaveTextContent(/mobile/i);
  });

  it("names only the outstanding item — mobile", () => {
    renderWith({ ...base, email_verified_at: VERIFIED_AT });
    const notice = screen.getByTestId("cart-verification-notice");
    expect(notice).toHaveTextContent(/verify your mobile to place an order/i);
  });

  it("routes to the account page to complete verification", async () => {
    const { default: userEvent } = await import("@testing-library/user-event");
    push.mockClear();
    renderWith(base);

    await userEvent.setup().click(screen.getByTestId("cart-verification-cta"));
    expect(push).toHaveBeenCalledWith("/my-account");
  });

  it("warns rather than blocks — it renders no disabled control", () => {
    // The server owns this rule and may reject for reasons we can't see, so a
    // client-side gate could lock out a customer the backend would accept.
    renderWith(base);
    const cta = screen.getByTestId("cart-verification-cta");
    expect(cta).toBeEnabled();
  });
});
