export function isSharedCartCode(code: unknown): code is string {
  return typeof code === "string" && code !== "__shared-cart__" && /^[A-Za-z0-9_-]{1,200}$/.test(code);
}

// Both the dynamic route and the exported fallback shell use the visible URL.
export function getSharedCartCode(asPath: string): string | null {
  const match = /^\/cart\/shared\/([^/?#]+)\/?(?:[?#].*)?$/.exec(asPath);
  if (!match) return null;
  try {
    const code = decodeURIComponent(match[1]);
    return isSharedCartCode(code) ? code : null;
  } catch {
    return null;
  }
}
