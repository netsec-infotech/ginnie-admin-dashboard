import { afterEach, describe, expect, it } from "vitest";
import {
  getTrackingSettings,
  setTrackingSettings,
} from "@/lib/trackingSettings";

afterEach(() => setTrackingSettings(null));

describe("backend tracking settings", () => {
  it("uses and trims backend identifiers", () => {
    setTrackingSettings({
      googleAnalyticsId: " G-XGJSKGJLKJ ",
      microsoftClarityId: " fjsdkl2314 ",
    });
    expect(getTrackingSettings()).toEqual({
      googleAnalyticsId: "G-XGJSKGJLKJ",
      microsoftClarityId: "fjsdkl2314",
    });
  });

  it("disables tracking for missing, empty, or unsafe identifiers", () => {
    for (const settings of [
      null,
      {},
      {
        googleAnalyticsId: "G-';alert(1)",
        microsoftClarityId: "</script>",
      },
      { googleAnalyticsId: "", microsoftClarityId: "" },
    ]) {
      setTrackingSettings(settings);
      expect(getTrackingSettings()).toEqual({
        googleAnalyticsId: "",
        microsoftClarityId: "",
      });
    }
  });
});
