import { beforeEach, describe, expect, it, vi } from "vitest";
import { Blob as NodeBlob } from "node:buffer";
const { get, post } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock("axios", () => ({
  default: {
    create: () => ({
      get,
      post,
      interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
    }),
    isAxiosError: (error: { isAxiosError?: boolean }) =>
      error?.isAxiosError === true,
  },
}));
import {
  authorizeSupportChannel,
  getSupportAttachment,
  getSupportHistory,
  getSupportRealtime,
  getSupportUpdates,
  rateSupportSession,
  requestSupportCallback,
  resolveSupportSession,
  sendSupportMessage,
  startSupportSession,
} from "@/routes/api";

beforeEach(() => {
  vi.clearAllMocks();
  post.mockResolvedValue({ data: { success: true, data: {} } });
});
describe("support API", () => {
  it("authorizes channels with form data through the authenticated client", async () => {
    post.mockResolvedValue({
      data: { auth: "test-signature", channel_data: "{}" },
    });
    const signal = new AbortController().signal;
    const endpoint = "/api/broadcasting/auth";
    const result = await authorizeSupportChannel(
      endpoint,
      "123.456",
      "private-support.thread.test",
      signal,
    );
    expect(result).toEqual({ auth: "test-signature", channel_data: "{}" });
    expect(post.mock.calls[0][0]).toMatch(/\/api\/broadcasting\/auth$/);
    const body = post.mock.calls[0][1];
    expect(body).toBeInstanceOf(URLSearchParams);
    expect(body.get("socket_id")).toBe("123.456");
    expect(body.get("channel_name")).toBe("private-support.thread.test");
    expect(post.mock.calls[0][2]).toEqual({
      signal,
      headers: { Accept: "application/json" },
    });
  });
  it("does not send the account token to an unrelated auth origin", async () => {
    await expect(
      authorizeSupportChannel(
        "https://unrelated.example/auth",
        "1.2",
        "private-support.thread.test",
      ),
    ).rejects.toThrow("Invalid support authorization endpoint");
    expect(post).not.toHaveBeenCalled();
  });
  it("rejects malformed channel auth responses", async () => {
    await expect(
      authorizeSupportChannel(
        "/api/broadcasting/auth",
        "1.2",
        "private-support.thread.test",
      ),
    ).rejects.toThrow("Invalid support authorization response");
  });
  it("loads realtime settings using the existing authenticated API client", async () => {
    const data = { configured: false, realtime: null };
    const controller = new AbortController();
    get.mockResolvedValue({ data: { success: true, data } });
    expect(await getSupportRealtime(controller.signal)).toBe(data);
    expect(get).toHaveBeenCalledWith("/user/support/realtime", {
      signal: controller.signal,
    });
  });
  it("uses the documented multipart session and message routes", async () => {
    const draft = {
      message: "Help",
      attachments: [],
      client_message_id: "uuid",
    };
    await startSupportSession({ ...draft, topic_id: 5, order_id: 3 });
    expect(post.mock.calls[0][0]).toBe("/user/support/sessions");
    expect(post.mock.calls[0][1].get("order_id")).toBe("3");
    await sendSupportMessage("SUP TEST", draft);
    expect(post.mock.calls[1][0]).toBe(
      "/user/support/sessions/SUP%20TEST/messages",
    );
    expect(post.mock.calls[1][1].get("client_message_id")).toBe("uuid");
  });
  it("uses cursor query parameters for updates and older history", async () => {
    get.mockResolvedValue({ data: { success: true, data: {} } });
    await getSupportUpdates(20, 3);
    await getSupportHistory(10);
    expect(get.mock.calls[0]).toEqual([
      "/user/support/thread/updates",
      { params: { after_message_id: 20, after_call_id: 3 }, signal: undefined },
    ]);
    expect(get.mock.calls[1]).toEqual([
      "/user/support/thread/messages",
      { params: { before_message_id: 10, limit: 30 }, signal: undefined },
    ]);
  });
  it("downloads by authenticated media ID without trusting response URLs", async () => {
    const blob = new NodeBlob(["image"], { type: "image/png" });
    get.mockResolvedValue({ data: blob });
    expect(await getSupportAttachment(7)).toBe(blob);
    expect(get).toHaveBeenCalledWith("/user/support/attachments/7", {
      responseType: "blob",
      signal: undefined,
    });
  });
  it("normalizes malformed PNG responses for both previews and downloads", async () => {
    const png = new Uint8Array([0x89, 0x50, 0x4e, 0x47, 13, 10, 26, 10]);
    get.mockResolvedValue({
      data: new NodeBlob(["    ", png], { type: "image/png" }),
    });
    const result = await getSupportAttachment(7);
    expect(new Uint8Array(await result.arrayBuffer())).toEqual(png);
    expect(result.type).toBe("image/png");
  });
  it("accepts null successful action payloads and sends exact callback/rating fields", async () => {
    post.mockResolvedValue({ data: { success: true, data: null } });
    await requestSupportCallback("SUP", " +123 ", "2026-10-01T10:00:00.000Z");
    await rateSupportSession("SUP", 4, " thanks ");
    await expect(resolveSupportSession("SUP")).resolves.toBeUndefined();
    expect(post.mock.calls[0][1]).toEqual({
      phone: "+123",
      scheduled_at: "2026-10-01T10:00:00.000Z",
    });
    expect(post.mock.calls[1][1]).toEqual({ score: 4, feedback: "thanks" });
  });
  it("does not treat a failed send or action as success", async () => {
    post.mockResolvedValue({
      data: { success: false, message: "Conversation closed", data: null },
    });
    await expect(resolveSupportSession("SUP")).rejects.toThrow(
      "Conversation closed",
    );
  });
  it("preserves conflict and Laravel validation errors without exposing server errors", async () => {
    post.mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { message: "Invalid attachment" } },
    });
    await expect(resolveSupportSession("SUP")).rejects.toMatchObject({
      status: 422,
      message: "Invalid attachment",
    });
    post.mockRejectedValue({
      isAxiosError: true,
      response: { status: 500, data: { message: "Private stack trace" } },
    });
    await expect(resolveSupportSession("SUP")).rejects.toMatchObject({
      status: 500,
      message: "",
    });
  });
  it("carries Retry-After so the hook can honor rate limits", async () => {
    post.mockRejectedValue({
      isAxiosError: true,
      response: { status: 429, headers: { "retry-after": "45" } },
    });
    await expect(resolveSupportSession("SUP")).rejects.toMatchObject({
      status: 429,
      retryAfter: 45,
    });
  });
});
