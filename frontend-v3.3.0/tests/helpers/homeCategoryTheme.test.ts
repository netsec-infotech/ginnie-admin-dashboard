import { describe, expect, it } from "vitest";
import {
  applyHomeCategoryTheme,
  resolveHomeCategoryTheme,
} from "@/helpers/homeCategoryTheme";

describe("resolveHomeCategoryTheme", () => {
  it("uses dark controls on light category colours", () => {
    const theme = resolveHomeCategoryTheme("#f5dbe4", "#0094d9");

    expect(theme.foreground).toBe("#111827");
    expect(theme.controlBackground).toBe("rgba(17, 24, 39, 0.06)");
    expect(theme.controlBorder).toBe("rgba(17, 24, 39, 0.14)");
    expect(theme.focusRing).toBe("rgba(17, 24, 39, 0.55)");
  });

  it("uses light controls on dark category colours", () => {
    const theme = resolveHomeCategoryTheme("#111111", "#0094d9");

    expect(theme.foreground).toBe("#ffffff");
    expect(theme.controlBackground).toBe("rgba(255, 255, 255, 0.18)");
    expect(theme.controlBorder).toBe("rgba(255, 255, 255, 0.32)");
    expect(theme.focusRing).toBe("rgba(255, 255, 255, 0.9)");
  });

  it("uses the primary colour when no category colour is provided", () => {
    const theme = resolveHomeCategoryTheme(null, "#0094d9");

    expect(theme.color).toBe("#1a9fdd");
    expect(theme.foreground).toBe("#111827");
  });
});

describe("applyHomeCategoryTheme", () => {
  it("writes all shared home header CSS variables", () => {
    const theme = resolveHomeCategoryTheme("#f5dbe4", "#0094d9");

    applyHomeCategoryTheme(theme);

    expect(document.documentElement.style.getPropertyValue("--home-category-color")).toBe(
      theme.color,
    );
    expect(
      document.documentElement.style.getPropertyValue(
        "--home-category-control-bg",
      ),
    ).toBe(theme.controlBackground);
    expect(
      document.documentElement.style.getPropertyValue(
        "--home-category-control-hover-bg",
      ),
    ).toBe(theme.controlHoverBackground);
    expect(
      document.documentElement.style.getPropertyValue(
        "--home-category-control-border",
      ),
    ).toBe(theme.controlBorder);
    expect(
      document.documentElement.style.getPropertyValue(
        "--home-category-control-selected-bg",
      ),
    ).toBe(theme.controlSelectedBackground);
    expect(
      document.documentElement.style.getPropertyValue(
        "--home-category-control-icon-bg",
      ),
    ).toBe(theme.controlIconBackground);
    expect(
      document.documentElement.style.getPropertyValue(
        "--home-category-control-icon-selected-bg",
      ),
    ).toBe(theme.controlIconSelectedBackground);
    expect(
      document.documentElement.style.getPropertyValue("--home-category-focus-ring"),
    ).toBe(theme.focusRing);
  });
});
