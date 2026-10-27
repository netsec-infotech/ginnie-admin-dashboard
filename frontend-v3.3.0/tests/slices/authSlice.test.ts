import { describe, it, expect } from "vitest";
import reducer, {
  login,
  logout,
  setUserDataRedux,
} from "@/lib/redux/slices/authSlice";
import type { userData } from "@/types/ApiResponse";

const user = (over: Partial<userData> = {}) =>
  ({ id: 1, name: "Jo", email: "jo@example.com", ...over }) as userData;

const loggedOut = { isLoggedIn: false, access_token: "", user: null };

describe("authSlice", () => {
  it("login sets the session, token and user", () => {
    const state = reducer(
      loggedOut,
      login({ user: user(), access_token: "tok_123" }),
    );
    expect(state.isLoggedIn).toBe(true);
    expect(state.access_token).toBe("tok_123");
    expect(state.user?.id).toBe(1);
  });

  it("logout resets to the anonymous state", () => {
    const loggedIn = {
      isLoggedIn: true,
      access_token: "tok_123",
      user: user(),
    };
    expect(reducer(loggedIn, logout())).toEqual(loggedOut);
  });

  it("setUserDataRedux cannot recreate a user outside a live session", () => {
    const state = reducer(loggedOut, setUserDataRedux(user({ name: "Sam" })));
    expect(state).toEqual(loggedOut);
  });

  it("setUserDataRedux merges into an existing user", () => {
    const start = { isLoggedIn: true, access_token: "t", user: user() };
    const state = reducer(start, setUserDataRedux({ name: "Renamed" }));
    expect(state.user?.name).toBe("Renamed");
    // untouched fields survive the merge
    expect(state.user?.email).toBe("jo@example.com");
  });

  it("setUserDataRedux can repair a live session whose profile is missing", () => {
    const state = reducer(
      { isLoggedIn: true, access_token: "t", user: null },
      setUserDataRedux(user({ name: "Restored" })),
    );
    expect(state.user?.name).toBe("Restored");
  });
});
