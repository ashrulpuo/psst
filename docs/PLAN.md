# Anonymous Chat App — Stack & Plan (draft v2)

Name: **Psst** (domain/trademark unchecked)

## Concept
NGL-style anonymous messaging with two-way chat. Owner gets a public link; senders message them anonymously *to the receiver*; owner can reply and the thread continues. Free, open source, self-hosted. Just for fun.

## Decisions so far
- Owner web client: yes (plus Flutter apps)
- Senders can receive notifications
- Moderation: manual block/report only (no auto filter)
- Admin sees full detail: dashboard, user registrations, who is sender and receiver
- Login: SSO only (Google; Apple required on iOS if Google is offered)

## IMPORTANT: privacy model
"Anonymous" = anonymous to the receiver only. Admin can identify senders.
Therefore senders must be identifiable -> **senders sign in with SSO too** (recommended), instead of anonymous cookie sessions.
- Pro: strong abuse control, notifications trivial, admin visibility is real
- Con: more friction than NGL's no-signup flow
- Must be disclosed in Privacy Policy / Terms (GDPR/PDPA-style duties: purpose, retention, access)
- Receiver UI shows "Anonymous #N" only; real identity never in any receiver-facing API response

## Full Stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | Laravel 12 (PHP 8.3+) | API, policies, queues, scheduler |
| Auth | Laravel Socialite (Google, Apple) + Sanctum | SSO only, no passwords. Flutter: native sign-in -> ID token -> backend verifies |
| Realtime | Laravel Reverb | Pusher protocol, Redis |
| Database | PostgreSQL 16 | |
| Cache/queue | Redis | |
| Admin panel | Custom: separate Vue 3 + TS SPA (Tailwind v4 + shadcn-vue), served at admin.<domain> | No Filament. Talks to /api/admin/* (admin-role-gated). Dashboard, users, threads, reports, blocks, audit log |
| Client (single codebase) | Quasar (Vue 3 + TypeScript) | One codebase -> PWA/web, iOS/Android (Capacitor), macOS/Windows (Electron or Tauri) |
| Public link page /{username} | Thin Laravel Blade page | OG tags for link previews, then opens the Quasar app |
| Push | FCM (mobile), Web Push (browser) | Desktop app: live over WebSocket |
| Abuse | Cloudflare Turnstile, rate limiters, manual block/report | |
| Proxy/TLS | Caddy | |
| Deploy | Docker Compose on own VPS | caddy, app, reverb, queue, scheduler, postgres, redis |
| CI | GitHub Actions | |

## Research notes (Oct 2026, from third-party sources — verify against official docs)
- Quasar: app-vite 3.x active; Capacitor and Electron modes exist (Electron mode revamped in v3).
- Desktop wrapper: Electron (desktop only, bundles Chromium, safest if no Rust) vs Tauri 2 (desktop + iOS/Android, small, OS webview, needs some Rust). Decide at milestone 8.
- Laravel: Reverb scales horizontally via Redis pub/sub. Laravel 13 reportedly released 2026-03-17 with a Reverb database driver (unverified) -> check latest Laravel version before scaffolding.
- Filament dropped by user decision; admin is a custom SPA.
- Capacitor social login (Google/Apple) and FCM plugins: current maintained plugins and Capacitor version compatibility NOT verified -> de-risk with a spike (or use web OAuth redirect via Socialite).

## Data model (v1)
- users: id, name, email, provider, provider_id, username, avatar, is_admin, last_login_at
- threads: id, receiver_id, sender_id, public_id, blocked_at, created_at
- messages: id, thread_id, from_receiver, body, read_at, created_at
- blocks (receiver_id, sender_id), reports (thread/message, reporter, reason, status)
- push_tokens (user_id, type fcm|webpush, token)
- audit_log (admin views of sender identity)

## Admin
- Dashboard: signups/day, messages/day, active users, open reports
- Users list, thread viewer with real sender + receiver, reports queue, ban/unban
- Access to identities logged in audit_log, admin role required (2FA recommended)

## Milestones
1. Server skeleton: docker-compose, Laravel, Postgres, Redis, CI
2. SSO auth (Google, Apple) + users
3. Core domain: threads/messages, policies, API
4. Realtime: Reverb
5. Quasar client (web first): login, inbox/chat, send flow; Blade link-preview page
6. Notifications: Web Push + FCM
7. Moderation + Filament admin
8. Capacitor mobile builds + desktop wrapper (same Quasar codebase)
9. Deploy, backups, monitoring, privacy page

## Open questions
- Confirm: senders must sign in with SSO?
- Project name
- Web client tech: Inertia + Vue vs Flutter web
