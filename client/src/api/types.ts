/** Shapes returned by the Psst API (server/app/Http/Resources). */

export interface Me {
  id: string;
  username: string | null;
  name: string;
  email: string;
  avatar_url: string | null;
  role: "user" | "admin";
  needs_username: boolean;
}

export interface PublicProfile {
  username: string;
  name: string;
  avatar_url: string | null;
}

export interface Message {
  id: string;
  body: string;
  /** Written by the signed-in user (relative to whoever is looking, never an identity). */
  mine: boolean;
  read_at: string | null;
  created_at: string;
  /** Client-only: optimistic message awaiting server confirmation. */
  pending?: boolean;
  failed?: boolean;
}

/** A thread as the receiver sees it: the sender is only ever "Anonymous #N". */
export interface InboxThread {
  id: string;
  label: string;
  unread_count: number;
  blocked: boolean;
  last_message_at: string | null;
  last_message: Message | null;
}

/** A thread as the sender sees it. */
export interface SentThread {
  id: string;
  receiver: PublicProfile;
  unread_count: number;
  can_reply: boolean;
  last_message_at: string | null;
  last_message: Message | null;
}

export interface CursorPage<T> {
  data: T[];
  meta: { next_cursor: string | null };
}

export type Provider = "google" | "apple";
