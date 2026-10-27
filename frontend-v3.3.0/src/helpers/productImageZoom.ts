interface ProductImageZoomInput {
  surfaceWidth: number;
  surfaceHeight: number;
  naturalWidth: number;
  naturalHeight: number;
  pointerX: number;
  pointerY: number;
  previewWidth: number;
  previewHeight: number;
}

/** Match the lens to the actual object-contain image and the preview's crop. */
export function getProductImageZoom(input: ProductImageZoomInput) {
  const {
    surfaceWidth, surfaceHeight, naturalWidth, naturalHeight,
    pointerX, pointerY, previewWidth, previewHeight,
  } = input;
  if (
    !Object.values(input).every(Number.isFinite) ||
    Math.min(surfaceWidth, surfaceHeight, naturalWidth, naturalHeight,
      previewWidth, previewHeight) <= 0
  ) return null;

  const fit = Math.min(surfaceWidth / naturalWidth, surfaceHeight / naturalHeight);
  const imageWidth = naturalWidth * fit;
  const imageHeight = naturalHeight * fit;
  const offsetX = (surfaceWidth - imageWidth) / 2;
  const offsetY = (surfaceHeight - imageHeight) / 2;
  const x = pointerX - offsetX;
  const y = pointerY - offsetY;
  // The empty space around a contained image has no image detail to magnify.
  if (x < 0 || y < 0 || x > imageWidth || y > imageHeight) return null;

  const scale = Math.max(2, previewWidth / imageWidth, previewHeight / imageHeight);
  const width = previewWidth / scale;
  const height = previewHeight / scale;
  const left = Math.max(0, Math.min(x - width / 2, imageWidth - width));
  const top = Math.max(0, Math.min(y - height / 2, imageHeight - height));

  return {
    lens: { left: offsetX + left, top: offsetY + top, width, height },
    preview: {
      left: -left * scale,
      top: -top * scale,
      width: imageWidth * scale,
      height: imageHeight * scale,
    },
  };
}
