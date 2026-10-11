// Explicitly load the global `google.maps` ambient types.
// TypeScript 6+ no longer auto-includes this `@types/*` package's global
// namespace the way 5.x did, so we reference it directly. This restores the
// global `google` namespace (and `window.google`) used by the Location components.
/// <reference types="google.maps" />
