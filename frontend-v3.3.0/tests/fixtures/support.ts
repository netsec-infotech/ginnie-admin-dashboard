import type {
  SupportMessage,
  SupportSession,
  SupportThreadData,
  SupportTopic,
} from "@/types/support";

// Sanitized fixtures model the authenticated live responses verified for this task.
export const supportMessage = (id: number, sessionId = 1): SupportMessage => ({
  id,
  session_id: sessionId,
  client_message_id: null,
  sender_role: "user",
  sender_name: "Customer",
  type: "text",
  text: `Message ${id}`,
  attachments: [],
  created_at: "2026-09-08T09:00:00.000000Z",
});
export const supportSession = (): SupportSession => ({
  id: 1,
  slug: "SUP-TEST",
  subject: "Account help",
  status: "open",
  topic: null,
  order: null,
  assignee: null,
  resolution_summary: null,
  resolved_at: null,
  closed_at: null,
  created_at: "2026-09-08T09:00:00.000000Z",
  messages: [supportMessage(10)],
  calls: [],
  rating: null,
});
export const supportThread = (active = true): SupportThreadData => ({
  thread: {
    id: 1,
    uuid: "test-thread",
    active_ticket_id: active ? 1 : null,
    last_message_at: null,
    customer: { id: 1, name: "Customer", phone: null },
    sessions: active ? [supportSession()] : [],
    message_pagination: {
      has_more: false,
      oldest_message_id: active ? 10 : null,
    },
  },
  recent_orders: [],
  polling_enabled: true,
  poll_interval_ms: 15000,
});
export const supportTopics: SupportTopic[] = [
  {
    id: 1,
    slug: "delivery",
    title: "Delivery",
    context: "order",
    guidance: "Check tracking",
    quick_replies: ["My order is late"],
  },
  {
    id: 2,
    slug: "account",
    title: "Account",
    context: "general",
    guidance: "Describe your account issue",
    quick_replies: ["Account question"],
  },
  {
    id: 3,
    slug: "payment",
    title: "Payment",
    context: "both",
    guidance: null,
    quick_replies: [],
  },
];
