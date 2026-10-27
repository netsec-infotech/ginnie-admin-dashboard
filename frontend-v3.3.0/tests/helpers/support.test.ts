import { describe, expect, it } from "vitest";
import { Blob as NodeBlob } from "node:buffer";
import {
  addSupportMessages,
  mergeSupportMessages,
  mergeSupportThread,
  normalizeSupportAttachment,
  SUPPORT_FILE_BYTES,
  supportFileError,
  supportFormData,
  supportTopicsFor,
} from "@/helpers/support";
import {
  supportMessage,
  supportThread,
  supportTopics,
} from "../fixtures/support";

// jsdom does not implement Blob.arrayBuffer; use the native binary Blob.
const BinaryBlob = NodeBlob as unknown as typeof Blob;

describe("support attachment bytes", () => {
  const png = new Uint8Array([0x89, 0x50, 0x4e, 0x47, 13, 10, 26, 10, 0, 0]);
  it("removes accidental server whitespace before a PNG signature", async () => {
    const blob = new BinaryBlob(["    ", png], { type: "image/png" });
    const result = await normalizeSupportAttachment(blob);
    expect(result.type).toBe("image/png");
    expect(new Uint8Array(await result.arrayBuffer())).toEqual(png);
  });
  it("preserves valid PNGs and never strips arbitrary file contents", async () => {
    for (const blob of [
      new BinaryBlob([png], { type: "image/png" }),
      new BinaryBlob(["    not a PNG"], { type: "image/png" }),
      new BinaryBlob(["    ", png], { type: "application/octet-stream" }),
      new BinaryBlob(["unexpected output", png], { type: "image/png" }),
    ]) {
      expect(await normalizeSupportAttachment(blob)).toBe(blob);
    }
  });
});

describe("support contracts", () => {
  it("filters API topics for order versus general help", () => {
    expect(
      supportTopicsFor(supportTopics, 12).map((topic) => topic.id),
    ).toEqual([1, 3]);
    expect(
      supportTopicsFor(supportTopics, null).map((topic) => topic.id),
    ).toEqual([2, 3]);
  });
  it("sends multipart files and reuses the caller's UUID without inventing an order", () => {
    const file = new File(["test"], "receipt.png", { type: "image/png" });
    const draft = {
      topic_id: 2,
      message: " hello ",
      attachments: [file],
      client_message_id: "stable-id",
    };
    const body = supportFormData(draft);
    expect(body.get("message")).toBe("hello");
    expect(body.get("topic_id")).toBe("2");
    expect(body.has("order_id")).toBe(false);
    expect(body.getAll("attachments[]")).toEqual([file]);
    expect(supportFormData(draft).get("client_message_id")).toBe("stable-id");
  });
  it("enforces the documented file count and inclusive size limit", () => {
    expect(
      supportFileError(Array.from({ length: 6 }, () => new File([], "test"))),
    ).toBe("fileCount");
    const file = new File([], "test.png", { type: "image/png" });
    Object.defineProperty(file, "size", {
      value: SUPPORT_FILE_BYTES,
      configurable: true,
    });
    expect(supportFileError([file])).toBeNull();
    Object.defineProperty(file, "size", { value: SUPPORT_FILE_BYTES + 1 });
    expect(supportFileError([file])).toBe("fileSize");
  });
  it("allows only image evidence and PDFs, even when the MIME and extension disagree", () => {
    expect(
      supportFileError([new File([], "photo.jpg", { type: "image/jpeg" })]),
    ).toBeNull();
    expect(
      supportFileError([
        new File([], "report.pdf", { type: "application/pdf" }),
      ]),
    ).toBeNull();
    expect(supportFileError([new File([], "script.exe")])).toBe("fileType");
    expect(
      supportFileError([
        new File([], "photo.jpg", { type: "application/pdf" }),
      ]),
    ).toBe("fileType");
    expect(() =>
      supportFormData({
        message: "Help",
        attachments: [new File([], "script.exe")],
        client_message_id: "unsafe",
      }),
    ).toThrow("fileType");
  });
  it("reconciles duplicate server rows and duplicate client UUIDs", () => {
    const sent = { ...supportMessage(10), client_message_id: "same" };
    const canonical = {
      ...supportMessage(11),
      client_message_id: "same",
      text: "accepted",
    };
    expect(
      mergeSupportMessages([sent, supportMessage(12)], [canonical, canonical]),
    ).toEqual([canonical, supportMessage(12)]);
  });
  it("preserves loaded history and its cursor while refreshing recent messages", () => {
    const previous = supportThread();
    previous.thread.sessions[0].messages = [
      supportMessage(1),
      supportMessage(10),
    ];
    previous.thread.message_pagination = {
      has_more: false,
      oldest_message_id: 1,
    };
    const next = supportThread();
    next.thread.message_pagination.has_more = true;
    next.thread.sessions[0].messages = [supportMessage(10), supportMessage(11)];
    const merged = mergeSupportThread(previous, next);
    expect(merged.thread.sessions[0].messages.map((item) => item.id)).toEqual([
      1, 10, 11,
    ]);
    expect(merged.thread.message_pagination).toEqual({
      has_more: false,
      oldest_message_id: 1,
    });
  });
  it("does not carry another account's history into a different thread", () => {
    const previous = supportThread();
    const next = supportThread(false);
    next.thread.id = 2;
    expect(mergeSupportThread(previous, next).thread.sessions).toEqual([]);
  });
  it("merges messages only into their own session without changing the source", () => {
    const data = supportThread();
    const next = addSupportMessages(data, [
      supportMessage(12),
      supportMessage(13, 2),
    ]);
    expect(next.thread.sessions[0].messages.map((item) => item.id)).toEqual([
      10, 12,
    ]);
    expect(data.thread.sessions[0].messages).toHaveLength(1);
  });
});
