export interface SupportTopic {
  id: number;
  slug: string;
  title: string;
  context: "order" | "general" | "both";
  guidance: string | null;
  quick_replies: string[];
}

export interface SupportOrder {
  id: number;
  slug: string;
  number: string;
  status: string;
  payment_status?: string;
  payment_method?: string;
  store_names?: string[];
  items?: { title: string; quantity: number; variant_title?: string }[];
  currency_code: string;
  total: string | number;
  created_at: string;
}

export interface SupportAttachment {
  id: number;
  name: string;
  mime_type: string;
  size: number;
  is_image: boolean;
}

export interface SupportMessage {
  id: number;
  session_id: number;
  client_message_id: string | null;
  sender_role: string;
  sender_name: string | null;
  type: string;
  text: string | null;
  attachments: SupportAttachment[];
  created_at: string;
}

export interface SupportCall {
  id: number;
  status: string;
  scheduled_at?: string | null;
  created_at?: string;
}

export interface SupportSession {
  id: number;
  slug: string;
  subject: string;
  status: string;
  topic: Pick<SupportTopic, "id" | "slug" | "title" | "quick_replies"> | null;
  order: SupportOrder | null;
  assignee: { id: number; name: string } | null;
  resolution_summary: string | null;
  resolved_at: string | null;
  closed_at: string | null;
  created_at: string;
  messages: SupportMessage[];
  calls: SupportCall[];
  // A non-null rating means the server has recorded feedback. Its internal
  // representation is not needed to render or submit the customer rating form.
  rating: unknown | null;
}

export interface SupportHistory {
  messages: SupportMessage[];
  has_more: boolean;
  oldest_message_id: number | null;
}

export interface SupportThreadData {
  thread: {
    id: number;
    uuid: string;
    active_ticket_id: number | null;
    last_message_at: string | null;
    customer: { id: number; name: string; phone: string | null };
    sessions: SupportSession[];
    message_pagination: Omit<SupportHistory, "messages">;
  };
  recent_orders: SupportOrder[];
  poll_interval_ms: number;
  polling_enabled: boolean;
}

export interface SupportUpdates {
  messages: SupportMessage[];
  calls: SupportCall[];
  active_ticket_id: number | null;
  message_cursor: number;
  call_cursor: number;
}

export interface SupportRealtimeData {
  user_id: number;
  thread_uuid: string;
  configured: boolean;
  realtime: {
    driver: string;
    key: string | null;
    host: string | null;
    port: number | null;
    scheme: string | null;
    cluster: string | null;
    auth_endpoint: string | null;
  } | null;
}

export interface SupportDraft {
  message: string;
  attachments: File[];
  client_message_id: string;
}

export interface StartSupportSession extends SupportDraft {
  topic_id: number;
  order_id?: number;
}

export type SupportAction =
  | "send"
  | "start"
  | "callback"
  | "resolve"
  | "rating";
