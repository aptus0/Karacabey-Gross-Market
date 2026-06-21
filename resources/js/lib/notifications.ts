import { ApiRequestError, buildApiUrl, clientIdentityHeaders } from "@/lib/api";

export type StorefrontNotificationItem = {
  id: string;
  type: string;
  title: string;
  body: string;
  action_url?: string | null;
  image_url?: string | null;
  payload?: Record<string, unknown> | null;
  broadcast_id?: number | null;
  read_at?: string | null;
  created_at?: string | null;
};

type NotificationIndexPayload = {
  data: StorefrontNotificationItem[];
  meta?: {
    unread_count?: number;
  };
};

function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
  };
}

async function notificationRequest<T>(path: string, init: RequestInit = {}) {
  const response = await fetch(buildApiUrl(path), {
    ...init,
    credentials: init.credentials ?? "include",
    headers: {
      Accept: "application/json",
      ...(init.body ? { "Content-Type": "application/json" } : {}),
      ...clientIdentityHeaders(),
      ...(init.headers ?? {}),
    },
  });

  const payload = (await response.json().catch(() => null)) as
    | ({ message?: string; errors?: Record<string, string | string[]> } & T)
    | null;

  if (!response.ok) {
    throw new ApiRequestError(
      payload?.message ?? `İstek başarısız oldu (${response.status}).`,
      response.status,
      payload?.errors,
      payload,
    );
  }

  return payload as T;
}

export async function fetchUserNotifications(token: string, limit = 25) {
  return notificationRequest<NotificationIndexPayload>(`/api/v1/notifications?limit=${limit}`, {
    headers: authHeaders(token),
  });
}

export async function markNotificationRead(token: string, notificationId: string) {
  const payload = await notificationRequest<{ data: StorefrontNotificationItem }>(
    `/api/v1/notifications/${notificationId}/read`,
    {
      method: "POST",
      headers: authHeaders(token),
    },
  );

  return payload.data;
}

export async function markAllNotificationsRead(token: string) {
  const payload = await notificationRequest<{ data: { status: string } }>(
    "/api/v1/notifications/read-all",
    {
      method: "POST",
      headers: authHeaders(token),
    },
  );

  return payload.data;
}
