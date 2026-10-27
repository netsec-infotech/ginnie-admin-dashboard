/**
 * Watch & Buy feeds are stable for the lifetime of the mounted page. Avoid
 * replaying the full config/feed/tray request set whenever the browser regains
 * focus or the device reconnects.
 */
export const WATCH_AND_BUY_SWR_CONFIG = {
  revalidateOnFocus: false,
  revalidateOnReconnect: false,
} as const;
