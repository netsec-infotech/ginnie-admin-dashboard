import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import DeliveryBanner from "@/views/homePage/DeliveryBanner";

const settings: Record<string, unknown> = {};
vi.mock("next/router", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ webSettings: settings }),
}));

describe("DeliveryBanner order-now toggle", () => {
  it("hides when orderNowSection is false", () => {
    settings.orderNowSection = false;
    const { container } = render(<DeliveryBanner />);
    expect(container).toBeEmptyDOMElement();
  });

  it("renders admin content when enabled", () => {
    Object.assign(settings, {
      orderNowSection: true,
      orderNowSectionTagline: "Fast & Reliable",
      orderNowSectionTitle: "Fast & Reliable",
      orderNowSectionTitleHighlight: "Delivery",
      orderNowSectionDescription: "Safe, secure, and timely shipping",
      orderNowButtonText: "Order Now",
      orderNowFeatures: ["Real-time tracking", "Secure packaging"],
    });
    render(<DeliveryBanner />);
    expect(screen.getByText("Order Now")).toBeInTheDocument();
    expect(screen.getByText("Real-time tracking")).toBeInTheDocument();
    expect(screen.getByText("Delivery")).toBeInTheDocument();
  });
});
