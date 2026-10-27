import { describe, expect, it } from "vitest";

import { resolveHomeHeaderAppearance } from "@/helpers/homeHeaderAppearance";
import type { HomeGeneralSettings } from "@/types/ApiResponse";

const makeSettings = (
  over: Partial<HomeGeneralSettings> = {},
): HomeGeneralSettings => ({
  title: "All",
  searchLabels: [],
  backgroundType: "color",
  backgroundColor: "#cfe5fe",
  fontColor: "#3d3d3d",
  ...over,
});

describe("resolveHomeHeaderAppearance", () => {
  it("resolves the configured color surface and foreground", () => {
    expect(resolveHomeHeaderAppearance(makeSettings())).toEqual({
      type: "color",
      backgroundColor: "#cfe5fe",
      backgroundImage: undefined,
      mobileBackgroundImage: undefined,
      backgroundAnimation: undefined,
      fontColor: "#3d3d3d",
    });
  });

  it("uses the web-specific color when no web media is configured", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({ backgroundColorWeb: "#f0e0d0" }),
    );

    expect(appearance.type).toBe("color");
    expect(appearance.backgroundColor).toBe("#f0e0d0");
  });

  it("keeps color mode authoritative when a stale web image is present", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "color",
        backgroundColorWeb: "#f0e0d0",
        backgroundImageWeb: "https://cdn.test/header-web.png",
      }),
    );

    expect(appearance.type).toBe("color");
    expect(appearance.backgroundColor).toBe("#f0e0d0");
    expect(appearance.backgroundImage).toBeUndefined();
  });

  it("resolves the mobile web image separately from the desktop image", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "image",
        backgroundImageWeb: "https://cdn.test/header-web.png",
        backgroundImageWebMobile: "https://cdn.test/header-mobile.png",
      }),
    );

    expect(appearance.type).toBe("image");
    expect(appearance.backgroundImage).toBe("https://cdn.test/header-web.png");
    expect(appearance.mobileBackgroundImage).toBe(
      "https://cdn.test/header-mobile.png",
    );
  });

  it("does not apply the global All mobile image to a category override", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "image",
        backgroundImageWeb: "https://cdn.test/header-web.png",
        backgroundImageWebMobile: "https://cdn.test/header-mobile.png",
      }),
      "https://cdn.test/category.png",
    );

    expect(appearance.backgroundImage).toBe(
      "https://cdn.test/category.png",
    );
    expect(appearance.mobileBackgroundImage).toBeUndefined();
  });

  it("uses separate desktop and mobile images from a category override", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "image",
        backgroundImageWeb: "https://cdn.test/header-web.png",
        backgroundImageWebMobile: "https://cdn.test/header-mobile.png",
      }),
      "https://cdn.test/category-desktop.png",
      null,
      "https://cdn.test/category-mobile.png",
    );

    expect(appearance.type).toBe("image");
    expect(appearance.backgroundImage).toBe(
      "https://cdn.test/category-desktop.png",
    );
    expect(appearance.mobileBackgroundImage).toBe(
      "https://cdn.test/category-mobile.png",
    );
  });

  it("supports a mobile-only category image without leaking the All desktop image", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "image",
        backgroundImageWeb: "https://cdn.test/header-web.png",
      }),
      null,
      null,
      "https://cdn.test/category-mobile.png",
    );

    expect(appearance.type).toBe("image");
    expect(appearance.backgroundImage).toBeUndefined();
    expect(appearance.mobileBackgroundImage).toBe(
      "https://cdn.test/category-mobile.png",
    );
  });

  it("keeps color mode authoritative when a stale web animation is present", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "color",
        backgroundColorWeb: "#f0e0d0",
        backgroundAnimationWeb: "https://cdn.test/header-web.json",
      }),
    );

    expect(appearance.type).toBe("color");
    expect(appearance.backgroundColor).toBe("#f0e0d0");
    expect(appearance.backgroundAnimation).toBeUndefined();
  });

  it("uses a valid image URL when image mode is selected", () => {
    expect(
      resolveHomeHeaderAppearance(
        makeSettings({
          backgroundType: "image",
          backgroundImage: "https://cdn.test/header.png",
        }),
      ).type,
    ).toBe("image");
  });

  it("keeps a selected color surface even when stale asset fields are present", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "color",
        backgroundImage: "https://cdn.test/header.png",
        backgroundAnimation: "https://cdn.test/header.json",
      }),
    );

    expect(appearance.type).toBe("color");
  });

  it("uses a selected category image over the configured header surface", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "animation",
        backgroundAnimation: "https://cdn.test/header.json",
      }),
      "https://cdn.test/category.png",
    );

    expect(appearance.type).toBe("image");
    expect(appearance.backgroundImage).toBe(
      "https://cdn.test/category.png",
    );
    expect(appearance.backgroundColor).toBe("#ffffff");
  });

  it("uses a category colour beneath a selected category image", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({ backgroundColor: "#006bd5" }),
      "https://cdn.test/category.png",
      "#fee7cb",
    );

    expect(appearance.backgroundColor).toBe("#fee7cb");
  });

  it("ignores an unsafe category image override", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "image",
        backgroundImage: "https://cdn.test/header.png",
      }),
      "javascript:alert(1)",
    );

    expect(appearance.type).toBe("image");
    expect(appearance.backgroundImage).toBe("https://cdn.test/header.png");
  });

  it("supports lottie as an animation-mode alias", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "lottie",
        backgroundAnimation: "https://cdn.test/header.json",
      }),
    );

    expect(appearance.type).toBe("animation");
    expect(appearance.backgroundAnimation).toBe(
      "https://cdn.test/header.json",
    );
  });

  it("uses the animation selected by settings when other media is present", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "animation",
        backgroundImage: "https://cdn.test/header.png",
        backgroundAnimation: "https://cdn.test/header.json",
      }),
    );

    expect(appearance.type).toBe("animation");
  });

  it("falls back to color when the selected image is unavailable", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "image",
        backgroundImage: "javascript:alert(1)",
      }),
    );

    expect(appearance.type).toBe("color");
    expect(appearance.backgroundColor).toBe("#cfe5fe");
  });

  it("falls back to color when the selected lottie asset is unavailable", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "lottie",
        backgroundAnimation: "data:application/json,{}",
      }),
    );

    expect(appearance.type).toBe("color");
    expect(appearance.backgroundColor).toBe("#cfe5fe");
  });

  it("falls back to the configured color when an asset URL is unsafe", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "image",
        backgroundImage: "javascript:alert(1)",
      }),
    );

    expect(appearance.type).toBe("color");
    expect(appearance.backgroundImage).toBeUndefined();
    expect(appearance.backgroundColor).toBe("#cfe5fe");
  });
});
