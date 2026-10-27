import type { ReactNode } from "react";
import { act, render, screen } from "@testing-library/react";
import { hydrateRoot, type Root } from "react-dom/client";
import { renderToString } from "react-dom/server";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { GetServerSidePropsContext } from "next";

const mocks = vi.hoisted(() => ({
  state: {
    auth: { isLoggedIn: false, user: { id: 1 } },
    _persist: { rehydrated: false },
  },
  chat: vi.fn(),
  guard: vi.fn(),
  settings: vi.fn(),
  translations: vi.fn(),
}));
vi.mock("react-redux", () => ({
  useSelector: (selector: (state: unknown) => unknown) => selector(mocks.state),
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@/views/SupportChatView", () => ({
  default: () => {
    mocks.chat();
    return <div>Private conversation</div>;
  },
}));
vi.mock("@/layouts/UserLayout", () => ({
  default: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));
vi.mock("@/components/custom/MyBreadcrumbs", () => ({ default: () => null }));
vi.mock("@/SEO/PageHead", () => ({ default: () => null }));
vi.mock("@heroui/react", () => ({
  Button: ({ children }: { children: ReactNode }) => (
    <button>{children}</button>
  ),
  Spinner: () => <div role="status">Loading</div>,
}));
vi.mock("@/helpers/getters", () => ({ isSSR: () => true }));
vi.mock("@/guards/authGuard", () => ({ serverSideAuthGuard: mocks.guard }));
vi.mock("@/routes/api", () => ({ getSettings: mocks.settings }));
vi.mock("../../i18n", () => ({ loadTranslations: mocks.translations }));
import SupportPage, { getServerSideProps } from "@/pages/my-account/support";

beforeEach(() => {
  vi.clearAllMocks();
  mocks.state.auth.isLoggedIn = false;
  mocks.state._persist.rehydrated = false;
});
describe("support account protection", () => {
  it.each([false, true])(
    "hydrates without mismatches when server persistence is %s and the browser has restored a login",
    async (serverPersisted) => {
      mocks.state._persist.rehydrated = serverPersisted;
      const container = document.createElement("div");
      container.innerHTML = renderToString(<SupportPage />);
      mocks.state._persist.rehydrated = true;
      mocks.state.auth.isLoggedIn = true;
      const recoverableErrors: unknown[] = [];
      let root: Root | undefined;

      try {
        await act(async () => {
          root = hydrateRoot(container, <SupportPage />, {
            onRecoverableError: (error) => recoverableErrors.push(error),
          });
        });
        expect(recoverableErrors).toEqual([]);
        expect(container).toHaveTextContent("Private conversation");
      } finally {
        await act(async () => root?.unmount());
      }
    },
  );

  it("always renders a neutral server placeholder even if the server store is already rehydrated", () => {
    mocks.state._persist.rehydrated = true;
    const markup = renderToString(<SupportPage />);
    expect(markup).toContain("Loading");
    expect(markup).not.toContain("supportChat.loginRequired");
    expect(mocks.chat).not.toHaveBeenCalled();
  });

  it("does not mount private chat before hydration or while signed out, and removes it on logout", () => {
    const view = render(<SupportPage />);
    expect(screen.getByRole("status")).toBeInTheDocument();
    expect(mocks.chat).not.toHaveBeenCalled();
    mocks.state._persist.rehydrated = true;
    view.rerender(<SupportPage />);
    expect(screen.getByText("supportChat.loginRequired")).toBeInTheDocument();
    expect(mocks.chat).not.toHaveBeenCalled();
    mocks.state.auth.isLoggedIn = true;
    view.rerender(<SupportPage />);
    expect(screen.getByText("Private conversation")).toBeInTheDocument();
    mocks.state.auth.isLoggedIn = false;
    view.rerender(<SupportPage />);
    expect(screen.queryByText("Private conversation")).not.toBeInTheDocument();
  });
  it("uses the existing server guard without fetching settings for a rejected session", async () => {
    const rejection = {
      redirect: { destination: "/?auth=required", permanent: false },
    };
    mocks.guard.mockResolvedValue(rejection);
    expect(getServerSideProps).toBeTypeOf("function");
    expect(await getServerSideProps?.({} as GetServerSidePropsContext)).toEqual(
      rejection,
    );
    expect(mocks.settings).not.toHaveBeenCalled();
  });
  it("loads SSR settings/translations without serializing private support history", async () => {
    mocks.guard.mockResolvedValue(null);
    mocks.settings.mockResolvedValue({ data: { theme: "existing" } });
    expect(await getServerSideProps?.({} as GetServerSidePropsContext)).toEqual(
      { props: { initialSettings: { theme: "existing" } } },
    );
    expect(mocks.translations).toHaveBeenCalledTimes(1);
  });
});
