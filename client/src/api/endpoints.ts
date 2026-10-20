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

export interface TokenLogin {
  token: string;
  user: Me;
}

export const authApi = {
  me: () => http.get<{ data: Me }>("/me", undefined, true).then(r => r.data),
  /** Native sign-in: the same ID-token verification, answered with a bearer token instead of a cookie session. */
  signInToken: (
    provider: Provider,
    idToken: string,
    nonce: string,
    deviceName: string,
    name?: string
  ) =>
    http.post<TokenLogin>(`/auth/${provider}/token`, {
      id_token: idToken,
      nonce,
      device_name: deviceName,
      ...(name ? { name } : {})
    }),
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
    http.post<{ data: Me } | TokenLogin>("/dev/login", { email }),
  setUsername: (username: string) =>
    http.patch<{ data: Me }>("/me/username", { username }).then(r => r.data),
  logout: () => http.post<void>("/auth/logout"),
  /** Permanent. `confirmation` is the username typed by the user. */
  deleteAccount: (confirmation: string) =>
    request<void>("DELETE", "/me", { body: { confirmation } }),
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
  /**
   * Anyone can send. A signed-in sender gets a thread id back; a guest gets a token to keep in their browser,
   * which later lets them claim the thread (and be replied to) by signing in.
   */
  send: (
    username: string,
    body: string,
    guest?: { token: string | null; challenge?: string; website?: string }
  ) =>
    request<SendResult>("POST", `/profiles/${username}/messages`, {
      body: {
        body,
        ...(guest?.challenge ? { turnstile_token: guest.challenge } : {}),
        ...(guest?.website ? { website: guest.website } : {})
      },
      headers: guest?.token ? { "X-Guest-Token": guest.token } : {}
    })
};

export type SendResult =
  | { data: Message; thread_id: string }
  | { data: { sent: true }; guest_token: string };

export const guestApi = {
  /** After sign-in: attach this browser's anonymous threads to the account. */
  claim: (token: string) =>
    request<{ data: { claimed: number } }>("POST", "/guest/claim", {
      body: {},
      headers: { "X-Guest-Token": token }
    }).then(r => r.data.claimed)
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

export interface PushConfig {
  /** VAPID public key for browsers; null when Web Push is off. */
  publicKey: string | null;
  /** Whether the server can push to the native apps. */
  fcm: boolean;
}

export const pushApi = {
  config: () =>
    http
      .get<{ data: { public_key: string | null; fcm: boolean } }>(
        "/push/config"
      )
      .then((r): PushConfig => ({
        publicKey: r.data.public_key,
        fcm: r.data.fcm
      })),
  /** VAPID public key, or null when the server has push switched off. */
  publicKey: () => pushApi.config().then(c => c.publicKey),
  register: (subscription: WebPushRegistration) =>
    http.post<void>("/push/subscriptions", subscription),
  unregister: (endpoint: string) =>
    request<void>("DELETE", "/push/subscriptions", { body: { endpoint } }),
  /** Native devices register their FCM token. */
  registerDevice: (token: string, platform: "ios" | "android") =>
    http.post<void>("/push/devices", { token, platform }),
  unregisterDevice: (token: string) =>
    request<void>("DELETE", "/push/devices", { body: { token } })
};
