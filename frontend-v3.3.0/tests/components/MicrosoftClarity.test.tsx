import React from "react";
import { act, render, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import MicrosoftClarity from "@/components/Functional/MicrosoftClarity";

import { setTrackingSettings } from "@/lib/trackingSettings";

const PROJECT_ID = "fjsdkl2314";

vi.mock("@/config/clarity", () => ({
  isMicrosoftClarityEnabled: true,
}));

vi.mock("next/script", () => ({
  default: ({
    children,
    strategy,
    ...props
  }: React.ComponentPropsWithoutRef<"script"> & { strategy?: string }) => (
    <script {...props} data-strategy={strategy}>
      {children}
    </script>
  ),
}));

beforeEach(() => {
  setTrackingSettings({ microsoftClarityId: PROJECT_ID });
});

afterEach(() => {
  setTrackingSettings(null);
  localStorage.clear();
  delete window.clarity;
});

describe("MicrosoftClarity", () => {
  it("loads the project with Consent V2 after stored acceptance", async () => {
    localStorage.setItem("cookie_consent_choice", "accepted");
    const { container } = render(<MicrosoftClarity />);

    await waitFor(() => {
      expect(
        container.querySelector(`#microsoft-clarity-${PROJECT_ID}`),
      ).toBeInTheDocument();
    });

    const script = container.querySelector(`#microsoft-clarity-${PROJECT_ID}`);
    expect(script?.textContent).toContain(PROJECT_ID);
    expect(script?.textContent).toContain('"consentv2"');
    expect(script?.textContent).toContain('analytics_Storage: "granted"');
    expect(script?.textContent).toContain('ad_Storage: "denied"');
  });

  it("does not load before consent or after stored rejection", async () => {
    localStorage.setItem("cookie_consent_choice", "declined");
    const { container } = render(<MicrosoftClarity />);

    await act(async () => {
      await new Promise((resolve) => window.setTimeout(resolve, 0));
    });

    expect(
      container.querySelector(`#microsoft-clarity-${PROJECT_ID}`),
    ).not.toBeInTheDocument();
  });

  it("loads immediately when the shared consent event is accepted", async () => {
    const { container } = render(<MicrosoftClarity />);

    act(() => {
      window.dispatchEvent(
        new CustomEvent("hyperlocal:analytics-consent", { detail: true }),
      );
    });

    await waitFor(() => {
      expect(
        container.querySelector(`#microsoft-clarity-${PROJECT_ID}`),
      ).toBeInTheDocument();
    });
  });
  it("waits for backend settings even when consent is already accepted", async () => {
    setTrackingSettings(null);
    localStorage.setItem("cookie_consent_choice", "accepted");
    const { container } = render(<MicrosoftClarity />);
    await act(async () => {
      await new Promise((resolve) => window.setTimeout(resolve, 0));
    });
    expect(container.querySelector("script")).not.toBeInTheDocument();
    act(() => {
      setTrackingSettings({ microsoftClarityId: PROJECT_ID });
    });
    expect(
      container.querySelector(`#microsoft-clarity-${PROJECT_ID}`),
    ).toBeInTheDocument();
  });
});
