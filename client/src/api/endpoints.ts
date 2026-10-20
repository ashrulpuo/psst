import { http } from "./http";
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
  logout: () => http.post<void>("/auth/logout")
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
