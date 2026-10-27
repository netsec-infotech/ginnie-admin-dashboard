import { describe, expect, it } from "vitest";
import { getProductImageZoom } from "@/helpers/productImageZoom";

const frame = {
  surfaceWidth: 400, surfaceHeight: 400,
  naturalWidth: 800, naturalHeight: 800,
  pointerX: 200, pointerY: 200,
  previewWidth: 200, previewHeight: 200,
};

describe("product image zoom geometry", () => {
  it("makes the lens show exactly the preview's crop", () => {
    expect(getProductImageZoom(frame)).toEqual({
      lens: { left: 150, top: 150, width: 100, height: 100 },
      preview: { left: -300, top: -300, width: 800, height: 800 },
    });
  });

  it("keeps the crop inside the image at each corner", () => {
    expect(getProductImageZoom({ ...frame, pointerX: 0, pointerY: 0 })?.lens)
      .toEqual({ left: 0, top: 0, width: 100, height: 100 });
    expect(getProductImageZoom({ ...frame, pointerX: 400, pointerY: 400 })?.lens)
      .toEqual({ left: 300, top: 300, width: 100, height: 100 });
  });

  it("accounts for letterboxing in wide and tall product images", () => {
    const wide = { ...frame, naturalWidth: 1600 };
    expect(getProductImageZoom(wide)?.lens)
      .toEqual({ left: 150, top: 150, width: 100, height: 100 });
    expect(getProductImageZoom({ ...wide, pointerY: 50 })).toBeNull();
    const tall = { ...frame, naturalHeight: 1600 };
    expect(getProductImageZoom(tall)?.lens)
      .toEqual({ left: 150, top: 150, width: 100, height: 100 });
    expect(getProductImageZoom({ ...tall, pointerX: 50 })).toBeNull();
  });

  it("fits a narrow image to the preview without magnifying empty padding", () => {
    const result = getProductImageZoom({ ...frame, naturalHeight: 4000 })!;
    expect(result.lens.width).toBe(80);
    expect(result.lens.left).toBe(160);
    expect(result.preview.width).toBe(200);
    expect(result.preview.height).toBe(1000);
  });

  it("does not magnify unloaded images or invalid measurements", () => {
    expect(getProductImageZoom({ ...frame, naturalWidth: 0 })).toBeNull();
    expect(getProductImageZoom({ ...frame, previewWidth: 0 })).toBeNull();
    expect(getProductImageZoom({ ...frame, pointerX: NaN })).toBeNull();
  });
});
