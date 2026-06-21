import { apiRequest, buildApiUrl, clientIdentityHeaders, createClientUID } from "@/lib/api";

const SUPPORT_STORAGE_KEY = "kgm-support-conversation";
const SUPPORT_GUEST_KEY = "kgm-support-guest";

export type SupportConversation = {
  id: number;
  token: string;
  status: string;
  subject?: string | null;
  customer_name?: string | null;
  last_message_at?: string | null;
};

export type SupportMessage = {
  id: number;
  sender_type: "customer" | "admin" | "ai" | "system";
  sender_name?: string | null;
  body: string;
  created_at?: string | null;
};

export type StoredSupportConversation = {
  id: number;
  token: string;
};

type ConversationResponse = {
  conversation: SupportConversation;
  messages?: SupportMessage[];
};

export function readStoredSupportConversation(): StoredSupportConversation | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(SUPPORT_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as StoredSupportConversation;
    return parsed?.id && parsed?.token ? parsed : null;
  } catch {
    return null;
  }
}

export function storeSupportConversation(conversation: StoredSupportConversation) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(SUPPORT_STORAGE_KEY, JSON.stringify(conversation));
}

export function supportGuestToken() {
  if (typeof window === "undefined") return null;
  const existing = window.localStorage.getItem(SUPPORT_GUEST_KEY);
  if (existing) return existing;
  const token = createClientUID("cus");
  window.localStorage.setItem(SUPPORT_GUEST_KEY, token);
  return token;
}

export async function createSupportConversation(input: {
  message: string;
  name?: string;
  phone?: string;
  email?: string;
  subject?: string;
  authToken?: string | null;
}) {
  const conversation = await apiRequest<SupportConversation>("/api/v1/support/conversations", {
    method: "POST",
    headers: {
      ...(input.authToken ? { Authorization: `Bearer ${input.authToken}` } : {}),
    },
    body: JSON.stringify({
      message: input.message,
      name: input.name,
      phone: input.phone,
      email: input.email,
      subject: input.subject ?? "Canlı destek",
      guest_token: supportGuestToken(),
    }),
  });
  storeSupportConversation({ id: conversation.id, token: conversation.token });
  return conversation;
}

export async function fetchSupportMessages(conversation: StoredSupportConversation, authToken?: string | null) {
  return apiRequest<ConversationResponse>(`/api/v1/support/conversations/${conversation.id}/messages?token=${encodeURIComponent(conversation.token)}`, {
    headers: {
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
    },
  });
}

export async function sendSupportMessage(conversation: StoredSupportConversation, message: string, authToken?: string | null) {
  return apiRequest<{ conversation: SupportConversation; message: SupportMessage }>(
    `/api/v1/support/conversations/${conversation.id}/messages?token=${encodeURIComponent(conversation.token)}`,
    {
      method: "POST",
      headers: {
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
      },
      body: JSON.stringify({ message }),
    },
  );
}

export function supportStreamUrl(conversation: StoredSupportConversation, afterId: number) {
  const url = new URL(buildApiUrl(`/api/v1/support/conversations/${conversation.id}/stream`), window.location.origin);
  url.searchParams.set("token", conversation.token);
  url.searchParams.set("after_id", String(afterId));

  return url.toString();
}

export function supportEventSourceHeadersFallback() {
  return clientIdentityHeaders();
}
