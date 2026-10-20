/** Shapes returned by /api/admin (server/app/Http/Resources/Admin). These are real identities. */

export interface UserBrief {
  id: string;
  username: string | null;
  name: string;
  email: string;
  avatar_url: string | null;
  role: "user" | "admin";
  banned_at: string | null;
}

export interface AdminUser extends UserBrief {
  ban_reason: string | null;
  last_login_at: string | null;
  created_at: string;
  two_factor: boolean;
}

export interface AdminUserDetail extends AdminUser {
  providers: string[];
  stats: {
    received_threads: number;
    sent_threads: number;
    messages_sent: number;
    reports_filed: number;
    reports_against: number;
    push_devices: number;
  };
}

export interface AdminThread {
  id: string;
  label: string;
  receiver: UserBrief;
  /** Null for a guest who never signed in: there is no account to reveal. */
  sender: UserBrief | null;
  sender_kind: "user" | "guest";
  blocked_at: string | null;
  last_message_at: string | null;
  created_at: string;
  message_count?: number;
}

export interface AdminMessage {
  id: string;
  author: "receiver" | "sender";
  body: string;
  read_at: string | null;
  created_at: string;
}

export type ReportStatus = "open" | "reviewed" | "dismissed";

export interface AdminReport {
  id: string;
  status: ReportStatus;
  reason: string;
  created_at: string;
  reviewed_at: string | null;
  reviewer: UserBrief | null;
  reporter: UserBrief;
  reported_user: UserBrief | null;
  thread_id: string;
  message: { id: string; body: string; created_at: string };
  /** Only on the detail endpoint: the conversation around the reported message. */
  context?: AdminMessage[];
}

export interface AuditEntry {
  id: string;
  action: string;
  subject_type: string | null;
  subject_id: string | null;
  meta: Record<string, unknown> | null;
  ip: string | null;
  created_at: string;
  actor: UserBrief | null;
}

export interface DayCount {
  date: string;
  count: number;
}

export interface Overview {
  totals: {
    users: number;
    new_users_7d: number;
    active_users_7d: number;
    banned_users: number;
    threads: number;
    messages: number;
    messages_24h: number;
    open_reports: number;
  };
  series: { signups: DayCount[]; messages: DayCount[] };
  generated_at: string;
}

export interface TwoFactorStatus {
  enrolled: boolean;
  pending: boolean;
  verified: boolean;
  recovery_codes_left: number;
}

export interface Me {
  id: string;
  name: string;
  email: string;
  avatar_url: string | null;
  role: "user" | "admin";
}

export interface CursorPage<T> {
  data: T[];
  meta: { next_cursor: string | null };
}
