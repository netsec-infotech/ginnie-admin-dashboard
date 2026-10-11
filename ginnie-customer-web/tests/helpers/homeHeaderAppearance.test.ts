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

  it("prioritizes a web image over the web color regardless of background type", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "color",
        backgroundColorWeb: "#f0e0d0",
        backgroundImageWeb: "https://cdn.test/header-web.png",
      }),
    );

    expect(appearance.type).toBe("image");
    expect(appearance.backgroundImage).toBe(
      "https://cdn.test/header-web.png",
    );
  });

  it("prioritizes a web animation over the web color regardless of background type", () => {
    const appearance = resolveHomeHeaderAppearance(
      makeSettings({
        backgroundType: "color",
        backgroundColorWeb: "#f0e0d0",
        backgroundAnimationWeb: "https://cdn.test/header-web.json",
      }),
    );

    expect(appearance.type).toBe("animation");
    expect(appearance.backgroundAnimation).toBe(
      "https://cdn.test/header-web.json",
    );
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
