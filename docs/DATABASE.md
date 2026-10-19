# Psst — Database Design (PostgreSQL 16)

## Conventions
- Primary keys: **UUIDv7** (time-ordered → index-friendly, safe to expose, no enumeration). Laravel `HasUuids` / `uuid` columns.
- All timestamps `timestamptz` (UTC). Soft deletes only where audit requires it.
- Every FK has an index; FKs enforce `ON DELETE` explicitly (no silent cascades on user-content).
- Constraints live in the DB (NOT NULL, CHECK, UNIQUE, FK) — never rely on app validation alone.
- Money-free app; no floats. Enums as `text` + CHECK (easier migrations than PG enum types).

## Tables

### users
| col | type | notes |
|---|---|---|
| id | uuid pk | |
| username | citext unique | 3–24 chars, `^[a-z0-9_.]+$`, reserved-word list |
| name, avatar_url | text | from SSO |
| email | citext unique | from SSO, verified only |
| role | text check in (user, admin) | default user |
| banned_at, ban_reason | timestamptz, text | |
| last_login_at, created_at, updated_at | timestamptz | |

### identities (SSO links; one user, many providers)
`id, user_id fk, provider check in (google, apple), provider_user_id, created_at` — **unique (provider, provider_user_id)**. No passwords anywhere.

### threads
| col | type | notes |
|---|---|---|
| id | uuid pk | |
| receiver_id | uuid fk users | the profile owner |
| sender_id | uuid fk users | real identity (admin-only visibility) |
| anon_label | smallint | per-receiver counter → "Anonymous #N" |
| last_message_at | timestamptz | inbox ordering |
| receiver_unread_count / sender_unread_count | int | denormalized, updated in same tx |
| blocked_at | timestamptz null | set when receiver blocks |
| created_at | timestamptz | |

Constraints: **unique (receiver_id, sender_id)** (one thread per pair), CHECK (receiver_id <> sender_id).
Indexes: `(receiver_id, last_message_at desc)`, `(sender_id, last_message_at desc)`.

### messages
`id uuid pk, thread_id fk, from_receiver bool, body text (ciphertext; 1000-char cap enforced in the app, since a CHECK can not see plaintext), read_at, created_at`
Index: **(thread_id, id desc)** — keyset pagination, no OFFSET. `body` uses Laravel `encrypted` cast (app-level encryption at rest; admin reads via app).

### blocks
`receiver_id, sender_id, created_at` — pk (receiver_id, sender_id). Checked on every send (cache in Redis, invalidate on write).

### reports
`id, reporter_id, message_id, reason text, status check in (open, reviewed, dismissed), reviewed_by, created_at`. Index `(status, created_at)`.

### push_subscriptions
`id, user_id, channel check in (webpush, fcm), token/endpoint, keys jsonb, user_agent, last_seen_at` — unique (channel, token). Pruned on 404/410 from push service.

### audit_logs (append-only)
`id, actor_id, action, subject_type, subject_id, meta jsonb, ip inet, created_at`. Written whenever an admin views sender identity, bans, resolves reports. Revoke UPDATE/DELETE from the app DB role.

## Performance
- Inbox = one indexed query on `threads` (no joins to messages). Unread counts are denormalized.
- Chat history = keyset pagination on `(thread_id, id)`.
- Hot reads (block checks, user by username) cached in Redis with explicit invalidation.
- PgBouncer in transaction mode when connections grow; `pg_stat_statements` enabled from day one.
- Eager-load always (`Model::preventLazyLoading()` in non-prod); no N+1.
- Nightly `pg_dump` + WAL archiving to off-VPS storage; restore tested.

## Security
- Separate DB roles: `app` (DML only), `migrator` (DDL), `readonly` (admin analytics). No superuser in app.
- Postgres reachable only on the Docker internal network; TLS if ever remote.
- Sender identity never selected in receiver-facing queries — enforced by API Resources, policy tests, and a dedicated `AdminThreadResource`.
- Retention: define deletion on account removal (anonymize sender_id → tombstone user, delete messages per policy).
