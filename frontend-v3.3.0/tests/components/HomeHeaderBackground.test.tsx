import { render, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import HomeHeaderBackground from "@/components/Functional/HomeHeaderBackground";
import type { HomeHeaderAppearance } from "@/helpers/homeHeaderAppearance";

const { loadAnimation } = vi.hoisted(() => ({
  loadAnimation: vi.fn(),
}));

vi.mock("lottie-web", () => ({
  default: { loadAnimation },
}));

const colorAppearance: HomeHeaderAppearance = {
  type: "color",
  backgroundColor: "#cfe5fe",
  fontColor: "#3d3d3d",
};

describe("HomeHeaderBackground", () => {
  beforeEach(() => {
    loadAnimation.mockReset();
    loadAnimation.mockReturnValue({
      addEventListener: vi.fn(),
      destroy: vi.fn(),
      goToAndStop: vi.fn(),
      pause: vi.fn(),
      play: vi.fn(),
    });

    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn(() => ({
        matches: false,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    });
  });

  it("always renders the color layer as a media fallback", () => {
    const { container } = render(<HomeHeaderBackground appearance={colorAppearance} />);
    const background = container.firstElementChild as HTMLDivElement;
    const colorLayer = background.firstElementChild as HTMLDivElement;

    expect(colorLayer).toHaveStyle({ backgroundColor: "#cfe5fe" });
  });

  it("adds a full-size image layer above the color fallback", () => {
    const { container } = render(
      <HomeHeaderBackground
        appearance={{
          ...colorAppearance,
          type: "image",
          backgroundImage: "https://cdn.test/header.png",
        }}
      />,
    );
    const background = container.firstElementChild as HTMLDivElement;
    const imageLayer = background.children[1] as HTMLDivElement;

    expect(imageLayer).toHaveStyle({
      backgroundImage: 'url("https://cdn.test/header.png")',
      backgroundSize: "100% 100%",
    });
    expect(imageLayer).toHaveAttribute("data-home-header-desktop-background");
  });

  it("renders the mobile image only below the desktop breakpoint", () => {
    const { container } = render(
      <HomeHeaderBackground
        appearance={{
          ...colorAppearance,
          type: "image",
          backgroundImage: "https://cdn.test/header.png",
          mobileBackgroundImage: "https://cdn.test/header-mobile.png",
        }}
      />,
    );

    const desktopLayer = container.querySelector(
      "[data-home-header-desktop-background]",
    );
    const mobileLayer = container.querySelector(
      "[data-home-header-mobile-background]",
    );

    expect(desktopLayer).toHaveClass("hidden", "md:block");
    expect(mobileLayer).toHaveClass("md:hidden");
    expect(mobileLayer).toHaveStyle({
      backgroundImage: 'url("https://cdn.test/header-mobile.png")',
      backgroundSize: "100% 100%",
    });
  });

  it("loads Lottie over the color fallback and destroys it if its data fails", async () => {
    const animation = {
      addEventListener: vi.fn(),
      destroy: vi.fn(),
      goToAndStop: vi.fn(),
      pause: vi.fn(),
      play: vi.fn(),
    };
    loadAnimation.mockReturnValue(animation);

    const { container } = render(
      <HomeHeaderBackground
        appearance={{
          ...colorAppearance,
          type: "animation",
          backgroundAnimation: "https://cdn.test/header.json",
        }}
      />,
    );
    const background = container.firstElementChild as HTMLDivElement;
    const colorLayer = background.firstElementChild as HTMLDivElement;

    expect(colorLayer).toHaveStyle({ backgroundColor: "#cfe5fe" });
    await waitFor(() =>
      expect(loadAnimation).toHaveBeenCalledWith(
        expect.objectContaining({
          container: expect.any(HTMLDivElement),
          path: "https://cdn.test/header.json",
          renderer: "svg",
        }),
      ),
    );

    const failureListener = animation.addEventListener.mock.calls.find(
      ([eventName]) => eventName === "data_failed",
    )?.[1] as (() => void) | undefined;
    expect(failureListener).toEqual(expect.any(Function));

    failureListener?.();
    expect(animation.destroy).toHaveBeenCalledOnce();
  });
});
