import { act, render, screen } from "@testing-library/react";
import { renderToString } from "react-dom/server";
import { hydrateRoot, type Root } from "react-dom/client";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  state: { auth: { isLoggedIn: false }, _persist: { rehydrated: false } },
  router: { pathname: "/my-account", isReady: true, replace: vi.fn() },
  toast: vi.fn(),
}));
vi.mock("react-redux", () => ({
  useSelector: (select: (state: typeof mocks.state) => unknown) =>
    select(mocks.state),
}));
vi.mock("next/router", () => ({ useRouter: () => mocks.router }));
vi.mock("@heroui/react", () => ({ addToast: mocks.toast }));
vi.mock("@/helpers/auth", () => ({ getAccessTokenFromContext: vi.fn() }));
import { withAuth } from "@/guards/withAuth";
import { isProtectedRoute } from "@/guards/authGuard";

const ProtectedCart = withAuth(() => <div>Customer cart</div>);

beforeEach(() => {
  vi.clearAllMocks();
  mocks.state.auth.isLoggedIn = false;
  mocks.state._persist.rehydrated = false;
  mocks.router.pathname = "/my-account";
  mocks.router.isReady = true;
});

describe("protected route hydration", () => {
  it("waits for the saved login without redirecting or mounting the cart", () => {
    const view = render(<ProtectedCart />);
    expect(mocks.router.replace).not.toHaveBeenCalled();
    expect(screen.queryByText("Customer cart")).not.toBeInTheDocument();
    mocks.state._persist.rehydrated = true;
    mocks.state.auth.isLoggedIn = true;
    view.rerender(<ProtectedCart />);
    expect(screen.getByText("Customer cart")).toBeInTheDocument();
    expect(mocks.router.replace).not.toHaveBeenCalled();
    mocks.state.auth.isLoggedIn = false;
    view.rerender(<ProtectedCart />);
    expect(screen.queryByText("Customer cart")).not.toBeInTheDocument();
    expect(mocks.router.replace).toHaveBeenCalledWith("/");
  });

  it("waits for the router before redirecting a restored guest session", () => {
    mocks.state._persist.rehydrated = true;
    mocks.router.isReady = false;
    const view = render(<ProtectedCart />);
    expect(mocks.router.replace).not.toHaveBeenCalled();
    mocks.router.isReady = true;
    view.rerender(<ProtectedCart />);
    expect(mocks.router.replace).toHaveBeenCalledOnce();
  });

  it("hydrates a restored login without a server/client mismatch", async () => {
    const container = document.createElement("div");
    container.innerHTML = renderToString(<ProtectedCart />);
    mocks.state.auth.isLoggedIn = true;
    mocks.state._persist.rehydrated = true;
    const errors: unknown[] = [];
    let root: Root | undefined;
    try {
      await act(async () => {
        root = hydrateRoot(container, <ProtectedCart />, {
          onRecoverableError: (error) => errors.push(error),
        });
      });
      expect(errors).toEqual([]);
      expect(container).toHaveTextContent("Customer cart");
      expect(mocks.router.replace).not.toHaveBeenCalled();
    } finally {
      await act(async () => root?.unmount());
    }
  });

  it.each([
    "/my-account/",
    "/my-account/orders/123",
    "/my-account/notifications/",
  ])("protects %s", (path) => {
    expect(isProtectedRoute(path)).toBe(true);
  });
  it.each(["/", "/cart/", "/cart/?coupon=abc", "/cart/shared/abc/", "/products/cart", "/cartoon", "/my-accounting"])(
    "does not block public route %s",
    (path) => {
      expect(isProtectedRoute(path)).toBe(false);
    },
  );

  it("allows a guest to view the cart without prompting for login", () => {
    mocks.router.pathname = "/cart";
    mocks.state._persist.rehydrated = true;
    render(<ProtectedCart />);
    expect(screen.getByText("Customer cart")).toBeInTheDocument();
    expect(mocks.router.replace).not.toHaveBeenCalled();
    expect(mocks.toast).not.toHaveBeenCalled();
  });
});
