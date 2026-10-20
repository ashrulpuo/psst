import { http, request } from "./http";
import type {
  CursorPage,
  InboxThread,
  Me,
  Message,
  Provider,
  PublicProfile,
  SentThread
} from "./types";

export const authApi = {
  me: () => http.get<{ data: Me }>("/me", undefined, true).then(r => r.data),
  signIn: (provider: Provider, idToken: string, nonce: string, name?: string) =>
    http
      .post<{ data: Me }>(`/auth/${provider}`, {
        id_token: idToken,
        nonce,
        name
      })
      .then(r => r.data),
  /** Local development only; the endpoint does not exist in production. */
  devLogin: (email: string) =>
    http.post<{ data: Me }>("/dev/login", { email }).then(r => r.data),
  setUsername: (username: string) =>
    http.patch<{ data: Me }>("/me/username", { username }).then(r => r.data),
  logout: () => http.post<void>("/auth/logout"),
  setPushPreviews: (enabled: boolean) =>
    http
      .patch<{ data: Me }>("/me/preferences", { push_previews: enabled })
      .then(r => r.data)
};

export const profileApi = {
  show: (username: string) =>
    http
      .get<{ data: PublicProfile }>(`/profiles/${username}`)
      .then(r => r.data),
  send: (username: string, body: string) =>
    http.post<{ data: Message; thread_id: string }>(
      `/profiles/${username}/messages`,
      { body }
    )
};

export const threadApi = {
  inbox: (cursor?: string) =>
    http.get<CursorPage<InboxThread>>("/inbox", { cursor }),
  sent: (cursor?: string) =>
    http.get<CursorPage<SentThread>>("/sent", { cursor }),
  messages: (threadId: string, cursor?: string) =>
    http.get<CursorPage<Message>>(`/threads/${threadId}/messages`, { cursor }),
  reply: (threadId: string, body: string) =>
    http
      .post<{ data: Message }>(`/threads/${threadId}/messages`, { body })
      .then(r => r.data),
  markRead: (threadId: string) => http.post<void>(`/threads/${threadId}/read`),
  block: (threadId: string) => http.put<void>(`/threads/${threadId}/block`),
  unblock: (threadId: string) =>
    http.delete<void>(`/threads/${threadId}/block`),
  report: (messageId: string, reason: string) =>
    http.post<void>(`/messages/${messageId}/report`, { reason })
};

export interface WebPushRegistration {
  endpoint: string;
  keys: { p256dh: string; auth: string };
}

export const pushApi = {
  /** VAPID public key, or null when the server has push switched off. */
  publicKey: () =>
    http
      .get<{ data: { public_key: string | null } }>("/push/config")
      .then(r => r.data.public_key),
  register: (subscription: WebPushRegistration) =>
    http.post<void>("/push/subscriptions", subscription),
  unregister: (endpoint: string) =>
    request<void>("DELETE", "/push/subscriptions", { body: { endpoint } })
};
