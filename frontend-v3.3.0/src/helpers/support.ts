import type {
  StartSupportSession,
  SupportDraft,
  SupportMessage,
  SupportThreadData,
  SupportTopic,
} from "@/types/support";

export const SUPPORT_FILE_LIMIT = 5;
export const SUPPORT_FILE_BYTES = 10 * 1024 * 1024;
export const SUPPORT_MESSAGE_LIMIT = 4000;
export const SUPPORT_FILE_ACCEPT = ".jpg,.jpeg,.png,.webp,.pdf";

const SUPPORT_FILE_TYPES: Record<string, string[]> = {
  "image/jpeg": ["jpg", "jpeg"],
  "image/png": ["png"],
  "image/webp": ["webp"],
  "application/pdf": ["pdf"],
};

export async function normalizeSupportAttachment(blob: Blob): Promise<Blob> {
  if (blob.type !== "image/png") return blob;
  // The support media endpoint can emit whitespace before the PNG header.
  // Remove only that prefix when the complete binary signature follows it.
  const header = new Uint8Array(await blob.slice(0, 72).arrayBuffer());
  let offset = 0;
  while (offset < 64 && [0x20, 0x09, 0x0a, 0x0d].includes(header[offset])) {
    offset++;
  }
  const signature = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
  return offset > 0 &&
    signature.every((byte, index) => header[offset + index] === byte)
    ? blob.slice(offset, blob.size, blob.type)
    : blob;
}

export class SupportRequestError extends Error {
  constructor(
    message: string,
    public status?: number,
    public retryAfter = 0,
  ) {
    super(message);
    this.name = "SupportRequestError";
  }
}

export function supportFileError(
  files: File[],
): "fileCount" | "fileSize" | "fileType" | null {
  if (files.length > SUPPORT_FILE_LIMIT) return "fileCount";
  if (files.some((file) => file.size > SUPPORT_FILE_BYTES)) return "fileSize";
  if (
    files.some((file) => {
      const extension = file.name.split(".").at(-1)?.toLowerCase() ?? "";
      const allowedExtensions = SUPPORT_FILE_TYPES[file.type.toLowerCase()];
      return file.type
        ? !allowedExtensions?.includes(extension)
        : !Object.values(SUPPORT_FILE_TYPES).flat().includes(extension);
    })
  )
    return "fileType";
  return null;
}

export function supportFormData(draft: SupportDraft | StartSupportSession) {
  const fileError = supportFileError(draft.attachments);
  if (fileError) throw new Error(`Invalid support attachment: ${fileError}`);
  const form = new FormData();
  form.append("message", draft.message.trim());
  form.append("client_message_id", draft.client_message_id);
  if ("topic_id" in draft) {
    form.append("topic_id", String(draft.topic_id));
    if (draft.order_id != null) form.append("order_id", String(draft.order_id));
  }
  draft.attachments.forEach((file) => form.append("attachments[]", file));
  return form;
}

export function supportTopicsFor(
  topics: SupportTopic[],
  orderId: number | null,
) {
  return topics.filter(
    (topic) =>
      topic.context === "both" ||
      topic.context === (orderId == null ? "general" : "order"),
  );
}

export function mergeSupportMessages(
  existing: SupportMessage[],
  incoming: SupportMessage[],
) {
  const byId = new Map(existing.map((message) => [message.id, message]));
  for (const message of incoming) {
    // Retries may return the same client UUID. Keep only its canonical server row.
    if (message.client_message_id) {
      for (const [id, previous] of byId) {
        if (
          id !== message.id &&
          previous.client_message_id === message.client_message_id
        )
          byId.delete(id);
      }
    }
    byId.set(message.id, message);
  }
  return [...byId.values()].sort((a, b) => a.id - b.id);
}

export function mergeSupportThread(
  previous: SupportThreadData | null,
  next: SupportThreadData,
): SupportThreadData {
  const sameThread = previous?.thread.id === next.thread.id;
  const previousSessions = sameThread ? previous.thread.sessions : [];
  return {
    ...next,
    thread: {
      ...next.thread,
      // Retain already loaded history when the server refreshes only its newest page.
      message_pagination:
        sameThread &&
        previous.thread.message_pagination.oldest_message_id != null &&
        (next.thread.message_pagination.oldest_message_id == null ||
          previous.thread.message_pagination.oldest_message_id <
            next.thread.message_pagination.oldest_message_id)
          ? previous.thread.message_pagination
          : next.thread.message_pagination,
      sessions: next.thread.sessions
        .map((session) => ({
          ...session,
          messages: mergeSupportMessages(
            previousSessions.find((item) => item.id === session.id)?.messages ??
              [],
            session.messages,
          ),
        }))
        .sort((a, b) => a.id - b.id),
    },
  };
}

export function addSupportMessages(
  data: SupportThreadData,
  messages: SupportMessage[],
): SupportThreadData {
  return {
    ...data,
    thread: {
      ...data.thread,
      sessions: data.thread.sessions.map((session) => ({
        ...session,
        messages: mergeSupportMessages(
          session.messages,
          messages.filter((message) => message.session_id === session.id),
        ),
      })),
    },
  };
}
