// Extends Vitest's `expect` with jest-dom matchers (toBeInTheDocument, etc.)
// and its type augmentation.
import "@testing-library/jest-dom/vitest";
import { afterEach } from "vitest";
import { cleanup } from "@testing-library/react";

// globals are disabled, so register React Testing Library's DOM cleanup manually.
afterEach(() => {
  cleanup();
});
