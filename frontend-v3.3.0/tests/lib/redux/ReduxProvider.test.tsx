import type { ReactElement, ReactNode } from "react";
import { act } from "react";
import { hydrateRoot, type Root } from "react-dom/client";
import { renderToString } from "react-dom/server";
import { describe, expect, it, vi } from "vitest";

import ReduxProvider from "@/lib/redux/ReduxProvider";

// Keep the persistence gate in its pre-rehydration state. The HTML it returns
// here is the browser's first render and therefore must match the server HTML.
vi.mock("redux-persist/integration/react", () => ({
  PersistGate: ({ loading }: { loading?: ReactNode }) => <>{loading}</>,
}));

function renderWithoutWindow(element: ReactElement) {
  const browserWindow = globalThis.window;

  vi.stubGlobal("window", undefined);
  try {
    return renderToString(element);
  } finally {
    vi.stubGlobal("window", browserWindow);
  }
}

describe("ReduxProvider", () => {
  it("hydrates the server-rendered page while persisted state is loading", async () => {
    const page = <main data-testid="page-content">Product details</main>;
    const container = document.createElement("div");
    const recoverableErrors: unknown[] = [];
    let root: Root | undefined;
    const provider = <ReduxProvider>{page}</ReduxProvider>;
    const serverHtml = renderWithoutWindow(provider);
    const firstClientHtml = renderToString(provider);

    container.innerHTML = serverHtml;

    expect(container).toHaveTextContent("Product details");
    expect(firstClientHtml).toBe(serverHtml);

    try {
      await act(async () => {
        root = hydrateRoot(container, provider, {
          onRecoverableError: (error) => recoverableErrors.push(error),
        });
      });

      expect(recoverableErrors).toEqual([]);
      expect(container).toHaveTextContent("Product details");
    } finally {
      if (root) {
        await act(async () => root?.unmount());
      }
      vi.unstubAllGlobals();
    }
  });
});
