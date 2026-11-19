# Psst Production Launch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Take Psst from feature-complete to a safe public web and PWA launch **built to spread**: the owner's goal is a hit, so growth mechanics and a distribution playbook are first-class work, not extras. Covers spec milestones M1, M3, M3.5 and the M2/M4 gate checklists. Android (M5) gets its own plan after the web launch.

**Architecture:** No new services. Server work stays in Laravel (`server/app/...`), following the existing Action classes, FormRequests, Resources and Admin controllers. Client work stays in `client/src` (Quasar/Vue, i18n in `client/src/i18n/en-US/index.ts`), admin work in `admin/src` (Vue). Deploy changes are `deploy/Caddyfile` and docs.

**Tech Stack:** Laravel (PHP 8.3, Postgres 16, Sanctum, Reverb, PHPUnit), Quasar/Vue 3 + TypeScript (`pnpm typecheck`, `pnpm lint:check`), Vue admin SPA, Caddy, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-11-17-production-launch-design.md`

## Global Constraints

- Server tests: `cd server && php artisan test`; formatting: `vendor/bin/pint --test`. Baseline is 230 passing; never lower it.
- Client gates: `cd client && pnpm typecheck && pnpm lint:check && pnpm build`. Admin: `cd admin && pnpm typecheck && pnpm build`.
- **Do not use Playwright** (owner instruction). Verify UI by running `scripts/dev-stack.sh --seed`, then driving the app in the built-in browser. Existing `client/e2e` suites are left untouched.
- zsh does not word-split unquoted variables; run multi-line curl scripts with `bash`.
- Message bodies are encrypted at rest (`Message` casts `body` as `encrypted`). Moderation must read the plaintext in `PostMessage` before storing, and must never log or send a body to a third party.
- Flagged messages are **still delivered**; the receiver sees no difference (spec section 5, M3).
- Guest bans are **per receiver** only (token or IP hash). Never add a global guest ban (spec M3).
- Privacy: no Google name or avatar in link previews; aggregate counters only, no per-person join between a guest and an account (spec sections 5 and 9). A shared note card contains only the message text the receiver chose to share, never any sender information.
- Migrations must be backward compatible (add only; no drops or renames) so a rollback never needs a restore (spec M1).
- English only (`en-US`). Google-only sign-in. Basic OAuth scopes only.
- Reserved usernames already include `terms` and `privacy` (`server/config/psst.php`); do not remove them.
- Commit after every task. Do not push.

## Review Focus

1. A message with mixed case, extra spaces or surrounding punctuation containing a keyword is still flagged; an empty keyword list flags nothing (not everything). (Task 5)
2. `pg_dump` fails halfway: no partial file is uploaded, the command exits non-zero, and the temp file is deleted. (Task 2)
3. A crawler asking for a banned or nonexistent username gets 404 with no tag leak, and usernames can't inject HTML into the preview page. (Task 11)
4. `/terms` and `/privacy` open the legal pages, not the `/:username` profile route. (Task 4)
5. Error-tracker events never contain message bodies, request payloads or the `Authorization` header. (Task 7)
6. `POST /api/events` rejects unknown event names with 422, requires a signed-in user, and stores no IP. (Task 9)
7. A note card with a very long, multi-line or emoji-heavy message wraps and truncates inside the 1080x1920 canvas and never overflows. (Task 13)

---

## Part A: Foundations (spec M1)

### Task 1: Trace and fix the 3 console errors

**Files:** found during the trace (likely `client/src/stores/*`, `client/src/boot/*`, `client/src/api/http.ts`); Modify as needed.

**Interfaces:** none.

- [ ] **Step 1: Reproduce.** Run `scripts/dev-stack.sh --seed`. In the built-in browser open `http://localhost:9000/alice`, sign in as Alice from `/login`, open `/inbox`, then a thread. Read console errors with `read_console_messages` (onlyErrors) and the failing requests with `read_network_requests`. Expected from 2026-11-17: two `401` and one `404`. Write down each failing URL and the page action that triggers it.
- [ ] **Step 2: Classify each.** A `401` on `/api/me` (or similar) for a visitor who is not signed in is expected behaviour: change the client to skip the call when no token or session exists, so a clean load shows no error. A `404` is a real bug (missing asset or wrong endpoint): fix the path or add the asset.
- [ ] **Step 3: Fix each at its source** (file named in Step 1). Do not suppress errors in the console.
- [ ] **Step 4: Verify.** Repeat the Step 1 walk. Expected: `read_console_messages` onlyErrors returns nothing for the guest page, login, link, inbox and a thread. Run `cd client && pnpm typecheck && pnpm lint:check`.
- [ ] **Step 5: Commit** `fix(client): remove console errors on guest and signed-in loads`.

### Task 2: Encrypted nightly database backup

**Files:**
- Create: `server/app/Console/Commands/BackupDatabase.php`, `scripts/restore-backup.sh`
- Modify: `server/config/psst.php` (add `backup` block), `server/config/filesystems.php` (add `backups` S3 disk), `server/routes/console.php` (schedule), `deploy/.env.example`, the app image file (ensure `pg_dump` for Postgres 16 is installed; find it with `grep -rln "php-fpm" deploy server --include=Dockerfile*`)
- Test: `server/tests/Feature/Ops/BackupDatabaseTest.php`

**Interfaces:**
- Produces: artisan command `psst:backup` (exit 0 on success, non-zero on failure). Config `psst.backup.disk` (`'backups'`), `psst.backup.passphrase` (env `BACKUP_PASSPHRASE`), `psst.backup.keep_days` (`14`). File name `psst-YYYYMMDD-HHMMSS.sql.gz.enc`.

- [ ] **Step 1: Failing tests** in `BackupDatabaseTest`: `test_it_uploads_an_encrypted_dump_and_prunes_old_ones` (fake `Process` success and `Storage::fake('backups')`, assert one `psst-*.sql.gz.enc` exists, a 20-day-old fake file is deleted, a 3-day-old one kept); `test_it_fails_without_uploading_when_pg_dump_fails` (`Process::fake` exit code 1: command returns non-zero, nothing in the disk); `test_it_refuses_to_run_without_a_passphrase` (empty passphrase: non-zero, no process run).
- [ ] **Step 2: Run** `php artisan test --filter=BackupDatabaseTest`. Expected: FAIL (command missing).
- [ ] **Step 3: Implement `BackupDatabase::handle(): int`.** Build one `Process` running `bash -o pipefail -c 'pg_dump ... | gzip | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE -out <tmp>'` with the DB credentials passed as `PG*` env vars (never on the command line), then stream the temp file to the `backups` disk and always delete the temp file. Prune files older than `keep_days` by `lastModified`.
- [ ] **Step 4: Schedule** `Schedule::command('psst:backup')->dailyAt('03:00')->onOneServer()` in `routes/console.php`. Add `BACKUP_PASSPHRASE`, `BACKUP_S3_ENDPOINT`, `BACKUP_S3_BUCKET`, `BACKUP_S3_KEY`, `BACKUP_S3_SECRET` to `deploy/.env.example`.
- [ ] **Step 5: Write `scripts/restore-backup.sh <file>`**: decrypt with the same passphrase, gunzip, `psql` into the database named by `DB_DATABASE`; refuse to run if the target DB is non-empty unless `--force`.
- [ ] **Step 6: Run** `php artisan test --filter=BackupDatabaseTest` then the full suite. Expected: all PASS.
- [ ] **Step 7: Commit** `feat(ops): encrypted nightly database backup and restore script`.

### Task 3: Deploy runbook

**Files:** Create `docs/DEPLOY.md`.

**Interfaces:** none (documentation).

- [ ] **Step 1: Write the runbook** with these ordered sections, each with the exact command and its expected output: (1) DNS and VPS prerequisites; (2) `deploy/.env` fill-in table with every variable from `docs/RELEASE.md` "Fatal if wrong", plus `BACKUP_*`, `SENTRY_LARAVEL_DSN`, `ADMIN_ALERT_EMAIL`, `MAIL_*`; (3) Google OAuth client creation and the consent-screen note (move to production before M4, basic scopes only; verify against Google's current rules); (4) `pnpm build:pwa`, admin `pnpm build`, `docker compose up -d`, `php artisan migrate --force`; (5) first admin: `php artisan psst:make-admin <email>`, then TOTP setup; (6) Turnstile: set `TURNSTILE_SECRET` and `QCLI_TURNSTILE_SITE_KEY`, rebuild the client; confirm a guest send without a token returns 422; (7) VAPID: `php artisan psst:vapid`; (8) smoke checks (`curl -sk https://<domain>/up`, `/api/push/config` shows a public key, `/.well-known/assetlinks.json`); (9) restore drill: `psst:backup`, then `scripts/restore-backup.sh` into a scratch database; (10) rollback: previous image tag plus note that migrations are backward compatible.
- [ ] **Step 2: Verify** every command in the doc exists (`php artisan list | grep psst`, `ls scripts`). Fix mismatches.
- [ ] **Step 3: Commit** `docs: production deploy runbook`.

---

## Part B: Public-ready safety (spec M3)

### Task 4: Terms and Privacy pages

**Files:**
- Create: `client/src/pages/TermsPage.vue`, `client/src/pages/PrivacyPage.vue`
- Modify: `client/src/router/routes.ts`, `client/src/i18n/en-US/index.ts`, `client/src/pages/LoginPage.vue`, `client/src/pages/SettingsPage.vue`, `client/src/pages/ProfilePage.vue` (footer links)

**Interfaces:**
- Produces: routes `/terms` (name `terms`) and `/privacy` (name `privacy`), public (no `requiresAuth`, no `guestOnly`), declared before the `/:username(...)` route.

- [ ] **Step 1: Write both pages** with i18n keys `legal.terms.*` and `legal.privacy.*`. Required statements, in plain language: receivers never see sender identity; **admins can identify signed-in senders and cannot identify guests**; messages are stored encrypted; anonymous usage counts are collected (aggregate, no person-level link); a receiver may share a note they received and then it is no longer private to them; how to delete an account; blocking and reporting; abuse results in bans; contact address (use `legal.contact`, value supplied by the owner, placeholder `hello@<domain>`).
- [ ] **Step 2: Add routes** inside the public layout and link both pages from the sign-in page privacy line, the Settings page, and the profile (send) page footer.
- [ ] **Step 3: Verify.** `pnpm typecheck && pnpm lint:check`. In the built-in browser open `/terms` and `/privacy` signed out and signed in: each renders its page (not the profile page, not a redirect), and the footer links work from `/login`, `/settings` and `/alice`.
- [ ] **Step 4: Commit** `feat(client): terms and privacy pages`.

### Task 5: Keyword flagging core

**Files:**
- Create: `server/database/migrations/2026_11_18_000015_create_message_flags_table.php`, `server/app/Models/MessageFlag.php`, `server/app/Moderation/KeywordFlagger.php`, `server/app/Moderation/FlagInbound.php`
- Modify: `server/config/psst.php` (add `moderation` block), `server/app/Actions/Messaging/PostMessage.php`
- Test: `server/tests/Feature/Moderation/KeywordFlaggerTest.php`, `server/tests/Feature/Moderation/FlagInboundTest.php`

**Interfaces:**
- Produces: `App\Moderation\KeywordFlagger::__construct(array $terms)` and `matches(string $body): list<string>` (lowercased matched terms, empty if none). `App\Moderation\FlagInbound::handle(Message $message, string $body): ?MessageFlag`. Table `message_flags(id uuid pk, message_id uuid fk cascade, terms jsonb, reviewed_at ts null, reviewed_by uuid null, created_at ts)`. Model `MessageFlag` with `message(): BelongsTo`. Config `psst.moderation.keywords` (array, default a short conservative list of threat and self-harm phrases the builder chooses), `psst.moderation.alert_email` (env `ADMIN_ALERT_EMAIL`).

- [ ] **Step 1: Failing tests.** `KeywordFlaggerTest`: `test_matches_ignore_case_and_punctuation` (`'I will KILL you!'` with term `kill you` returns `['kill you']`); `test_matches_whole_words_only` (term `ass` does not match `class`); `test_empty_term_list_flags_nothing`; `test_returns_each_term_once`. `FlagInboundTest`: `test_guest_message_with_a_term_creates_a_flag_and_is_still_delivered`; `test_receiver_replies_are_never_flagged`; `test_clean_message_creates_no_flag`.
- [ ] **Step 2: Run** `php artisan test --filter=Moderation`. Expected: FAIL.
- [ ] **Step 3: Implement** the migration (add only), model, `KeywordFlagger` (unicode-aware, word-boundary regex built from escaped terms, skips empty terms), `FlagInbound`. In `PostMessage::handle`, after the message is created and only when `! $fromReceiver`, call `FlagInbound::handle($message, $body)`; inject it through the constructor. It must not change delivery or throw into the send path (catch and `report()`).
- [ ] **Step 4: Run** the filter, then the full server suite and `vendor/bin/pint --test`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(moderation): flag inbound messages by keyword, still deliver`.

### Task 6: Admin flags API and owner alert

**Files:**
- Create: `server/app/Http/Controllers/Admin/FlagController.php`, `server/app/Notifications/FlaggedMessageAlert.php`, `server/app/Jobs/NotifyAdminOfFlag.php`
- Modify: `server/routes/api.php` (admin group, same middleware as `admin/reports`), `server/app/Moderation/FlagInbound.php` (dispatch the job after commit)
- Test: `server/tests/Feature/Admin/AdminFlagsTest.php`, `server/tests/Feature/Moderation/FlagAlertTest.php`

**Interfaces:**
- Consumes: `MessageFlag` (Task 5); mirror `Admin\ReportController` for pagination, resource shape and audit logging via `App\Admin\AuditLogger`.
- Produces: `GET /api/admin/flags?status=open|reviewed` returns `{data: [{id, message_id, thread_id, sender_kind, terms, body, created_at, reviewed_at}], ...pagination as ReportController}`; `POST /api/admin/flags/{flag}/review` sets `reviewed_at`/`reviewed_by`, writes an audit entry, returns 200. Alert: mail to `psst.moderation.alert_email` with **no message body**: only "A message was flagged" and the admin URL.

- [ ] **Step 1: Failing tests.** `AdminFlagsTest`: lists open flags with decrypted body for an admin with 2FA (use `AdminTestCase`), 403 for non-admin, review marks reviewed and writes an audit row. `FlagAlertTest`: `Notification::fake()`, flagged guest send sends `FlaggedMessageAlert` once to the configured address, mail text does not contain the body, nothing sent when `alert_email` is empty.
- [ ] **Step 2: Run** `php artisan test --filter="AdminFlagsTest|FlagAlertTest"`. Expected: FAIL.
- [ ] **Step 3: Implement** controller, routes, job (queued, `afterCommit`), notification (`Notification::route('mail', $email)`).
- [ ] **Step 4: Run** the filter and the full suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(admin): flagged messages API and owner email alert`.

### Task 7: Error tracking with body scrubbing, uptime note

**Files:**
- Create: `server/app/Observability/ScrubEvent.php`, `server/tests/Unit/ScrubEventTest.php`
- Modify: `server/composer.json` (add `sentry/sentry-laravel`), `server/config/sentry.php` (published), `server/bootstrap/app.php` (report exceptions to Sentry), `deploy/.env.example` (`SENTRY_LARAVEL_DSN`), `docs/DEPLOY.md`

**Interfaces:**
- Produces: `App\Observability\ScrubEvent::handle(\Sentry\Event $event): \Sentry\Event` set as `before_send`. Removes `request.data`, request cookies, `Authorization`/`X-Guest-Token` headers, and any breadcrumb or extra key named `body`. `send_default_pii` is false.

- [ ] **Step 1: Failing unit test** `ScrubEventTest`: build an event with request data `{body: 'secret text'}`, an `Authorization` header and a `body` breadcrumb; after `handle()` none of them remain and the exception message is untouched.
- [ ] **Step 2: Run** `php artisan test --filter=ScrubEventTest`. Expected: FAIL.
- [ ] **Step 3: Install** `composer require sentry/sentry-laravel`, publish config, implement `ScrubEvent`, register it as `before_send`, keep the DSN empty by default (no reporting without it).
- [ ] **Step 4: Document** in `docs/DEPLOY.md`: create a free-tier project, set the DSN, and set an external uptime check on `https://<domain>/up` (any free uptime service; the owner picks).
- [ ] **Step 5: Run** the full suite. Expected: PASS.
- [ ] **Step 6: Commit** `feat(observability): error tracking with body scrubbing`.

### Task 8: Abuse drill test

**Files:** Create `server/tests/Feature/Moderation/AbuseDrillTest.php`.

**Interfaces:** consumes Tasks 5 and 6.

- [ ] **Step 1: Write the drill as three tests**: `test_flagged_guest_message_reaches_admin_and_owner_alert`; `test_banned_signed_in_sender_is_refused` (403 "This account has been suspended."); `test_guest_blocked_by_one_receiver_is_dropped_only_for_that_receiver` (guest blocked by alice still delivers to carol, and the response to alice looks identical to success).
- [ ] **Step 2: Run** `php artisan test --filter=AbuseDrillTest`. Expected: PASS (these assert behaviour built in Tasks 5-6 and existing code). Any failure is a real bug: fix it in the owning code.
- [ ] **Step 3: Commit** `test(moderation): abuse drill`.

---

## Part C: Built to spread (spec M3.5, expanded for the "hit" goal)

The loop: a receiver posts a story card with their link, or posts **a note they received** (the strongest hook: curiosity makes friends tap); friends tap and send with no signup; the receiver gets a push and posts again; some senders make their own link.

### Task 9: Growth events and admin metrics (server)

**Files:**
- Create: `server/database/migrations/2026_11_18_000016_create_growth_events_table.php`, `server/app/Growth/GrowthEvents.php`, `server/app/Http/Controllers/EventController.php`
- Modify: `server/app/Actions/Messaging/SendGuestMessage.php`, `server/app/Admin/AdminStats.php`, `server/routes/api.php`
- Test: `server/tests/Feature/Growth/GrowthEventsTest.php`

**Interfaces:**
- Produces: table `growth_events(id bigserial, name varchar(40), created_at ts)`, index `(name, created_at)`; **no IP, no user id**. `App\Growth\GrowthEvents::record(string $name): void` with `GrowthEvents::NAMES = ['guest_sent', 'link_shared', 'note_shared', 'own_link_after_guest']`. `POST /api/events` (auth:sanctum, `throttle:profile`) body `{name: 'link_shared'|'note_shared'|'own_link_after_guest'}` returns 204. `AdminStats::overview()['growth']` adds `{guest_sends_7d: int, link_shares_7d: int, note_shares_7d: int, own_links_after_guest_7d: int, conversion_rate: float|null}` where `conversion_rate = own_links_after_guest_7d / guest_sends_7d`, `null` when there are no guest sends.

- [ ] **Step 1: Failing tests.** `test_guest_send_records_guest_sent`; `test_dropped_guest_message_records_nothing`; `test_events_endpoint_requires_auth` (401), `test_events_endpoint_rejects_unknown_names` (422, including `guest_sent`); `test_events_store_no_ip_or_user` (table has only the three columns); `test_overview_growth_block_and_null_conversion_when_no_sends`.
- [ ] **Step 2: Run** `php artisan test --filter=GrowthEventsTest`. Expected: FAIL.
- [ ] **Step 3: Implement.** Record `guest_sent` in `SendGuestMessage::handle` only when the message was delivered. Compute the growth block in `AdminStats::compute()` (stays inside the existing 60 s cache).
- [ ] **Step 4: Run** the filter and full suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(growth): aggregate growth events and admin metrics`.

### Task 10: Client events, conversion card, admin tiles

**Files:**
- Modify: `client/src/api/endpoints.ts` (add `trackEvent(name: "link_shared" | "note_shared" | "own_link_after_guest"): Promise<void>`, fire-and-forget, swallows errors), `client/src/pages/LinkPage.vue` (call `link_shared` on copy and share success), `client/src/pages/WelcomePage.vue` and `client/src/components/ClaimLinkForm.vue` (call `own_link_after_guest` after a successful username set when a guest token exists in the existing guest-token storage used by `ProfilePage.vue`), `client/src/pages/ProfilePage.vue` and `client/src/i18n/en-US/index.ts` (conversion card), `admin/src/pages/DashboardPage.vue`, `admin/src/lib/types.ts`

**Interfaces:** consumes `POST /api/events` and the `growth` block from Task 9.

- [ ] **Step 1: Conversion card.** In the "Sent" card of `ProfilePage.vue`, for `sentAsGuest && !auth.isAuthenticated`, replace the small `own-link` text link with a bordered card: title `profile.ownLinkTitle` ("Want your own link?"), body `profile.ownLinkBody` ("Takes 10 seconds. People message you anonymously."), a button `profile.ownLinkCta` to `/get-link`. "Sign in to chat" stays the primary action above it.
- [ ] **Step 2: Wire events** as listed in Files. Never block the UI on the request.
- [ ] **Step 3: Admin tiles.** Add five `StatTile`s to the dashboard from `overview.data.growth` (conversion shown as a percent, "n/a" when null) with a one-line caption "Guest senders who made their own link (7 days). Target 10%." Update `types.ts`.
- [ ] **Step 4: Verify.** `cd client && pnpm typecheck && pnpm lint:check`; `cd admin && pnpm typecheck`. In the built-in browser: send as a guest to `/alice`, confirm the card shows and links to `/get-link`; create a link there; open the admin dashboard (admin user from the seed with 2FA) and see `guest_sends_7d` and `own_links_after_guest_7d` each at 1.
- [ ] **Step 5: Commit** `feat(growth): conversion card, event tracking, admin growth tiles`.

### Task 11: Link previews and noindex

**Files:**
- Create: `server/resources/views/preview.blade.php`, `client/public/og.png` (1200x630, brand colours and the word "psst"; any tool)
- Modify: `server/routes/web.php`, `deploy/Caddyfile`
- Test: `server/tests/Feature/PreviewTest.php`

**Interfaces:**
- Produces: `GET /preview/{username}` returns an HTML page with `og:title` ("Message @username anonymously · Psst"), `og:description`, `og:image` (`{APP_URL}/og.png`), `twitter:card=summary_large_image`, `<meta name="robots" content="noindex,nofollow">`. It shows the **username only**, never the Google name or avatar. 404 for unknown or banned usernames. Every value is escaped by Blade.

- [ ] **Step 1: Failing tests.** `test_preview_has_og_tags_and_username_only` (assert the user's display name and avatar URL are absent); `test_preview_404_for_unknown_and_banned`; `test_preview_rejects_markup_in_username` (route 404s for `<script>`); `test_preview_is_noindex`.
- [ ] **Step 2: Run** `php artisan test --filter=PreviewTest`. Expected: FAIL.
- [ ] **Step 3: Implement** route and view. In `deploy/Caddyfile` add: (a) a matcher for known crawler user agents (`facebookexternalhit|Twitterbot|Slackbot|WhatsApp|TelegramBot|LinkedInBot|Discordbot|Instagram|Threads|Googlebot|bingbot`, case-insensitive via `header_regexp`) combined with `path_regexp user ^/([a-z0-9_.]{3,24})$`, which rewrites to `/preview/{re.user.1}` and proxies to PHP like the `@laravel` block; `/preview/*` is not exposed to normal browsers; (b) `header X-Robots-Tag "noindex, nofollow"` for profile-shaped paths (the same regex) on the SPA handler. Home (`/`) stays indexable.
- [ ] **Step 4: Verify.** Run the filter and the full suite. Validate the Caddyfile: `docker run --rm -e APP_DOMAIN=example.test -e ACME_EMAIL=a@example.test -v "$PWD/deploy:/etc/caddy" caddy:2-alpine caddy validate --config /etc/caddy/Caddyfile`. Against the local Docker stack on 8443 after a rebuild: `curl -sk -A "WhatsApp/2" https://localhost:8443/alice` shows the OG tags; a normal user agent still gets the SPA.
- [ ] **Step 5: Commit** `feat(growth): crawler link previews and noindex on profiles`.

### Task 12: Shareable link story cards with templates

**Files:**
- Create: `client/src/utils/storyCard.ts`
- Modify: `client/src/pages/LinkPage.vue`, `client/src/i18n/en-US/index.ts`

**Interfaces:**
- Produces: `STORY_TEMPLATES = ["sunset", "mint", "ink", "candy", "paper"] as const`; `type StoryTemplate = (typeof STORY_TEMPLATES)[number]`; `renderStoryCard(template: StoryTemplate, username: string, url: string): Promise<Blob>` (PNG, 1080x1920, canvas; shows "ask me anything", `@username`, the URL). Selected template persists in `localStorage` key `psst.story_template` (wrapped in try/catch). A shared helper `shareImage(blob: Blob, url: string): Promise<"shared" | "downloaded">` in the same file: uses `navigator.share({ files, url })` when `navigator.canShare?.({ files })` is true, else downloads the PNG.

- [ ] **Step 1: Implement `storyCard.ts`** with five distinct colour treatments (the builder picks them to match the existing brand gradient in `LinkPage.vue`) and `shareImage`.
- [ ] **Step 2: Wire `LinkPage.vue`.** The story row gets a template picker (five small swatches) and a "Share story card" button that calls `shareImage`, then `trackEvent("link_shared")`.
- [ ] **Step 3: Verify.** `pnpm typecheck && pnpm lint:check && pnpm build`. In the built-in browser at `/link`: switch templates (thumb changes, choice survives reload), click the button and confirm a PNG is produced (inspect via `javascript_tool`: output blob is `image/png`, canvas 1080x1920).
- [ ] **Step 4: Commit** `feat(growth): shareable story cards with five templates`.

### Task 13: Share a received note as a story card (the hook)

**Files:**
- Modify: `client/src/utils/storyCard.ts`, `client/src/features/messaging/ConversationPane.vue`, `client/src/components/MessageBubble.vue`, `client/src/i18n/en-US/index.ts`

**Interfaces:**
- Consumes: `StoryTemplate`, `shareImage` (Task 12), `trackEvent("note_shared")` (Task 10).
- Produces: `renderNoteCard(template: StoryTemplate, note: string, username: string, url: string): Promise<Blob>` (PNG 1080x1920): the note text in a big speech-bubble style, "send me anonymous messages" and the link beneath. The note text wraps, caps at about 280 characters with an ellipsis, and keeps emoji intact. It carries no sender info or timestamp.

- [ ] **Step 1: Implement `renderNoteCard`** with word wrap and the cap above, reusing the five templates.
- [ ] **Step 2: Add a "Share to story" action** on received (not own) messages in the conversation view: a small button on the message in `MessageBubble.vue`, emitting an event handled in `ConversationPane.vue`, which renders the card with the signed-in user's own username and link (`auth` store; the same URL logic as `LinkPage.vue`), calls `shareImage`, then `trackEvent("note_shared")`. The first time per device show a one-line notice: "Sharing shows this note to your followers. The sender stays anonymous."
- [ ] **Step 3: Verify.** `pnpm typecheck && pnpm lint:check && pnpm build`. In the built-in browser: sign in as Alice with a seeded thread, open it, use "Share to story" on a received note, confirm a PNG is produced. Test with a 1000-character message and a message of only emoji: text stays inside the canvas (inspect the blob via `javascript_tool` or render to an `<img>` and look at it).
- [ ] **Step 4: Commit** `feat(growth): share a received note as a story card`.

---

## Part D: Distribution playbook and launch gates

### Task 14: Distribution playbook

**Files:** Create `docs/GROWTH.md`.

**Interfaces:** none (documentation). Product features alone do not make a hit; this doc is the part the owner runs.

- [ ] **Step 1: Write the playbook** with these sections, concrete and short: (1) **Pick one beachhead** (owner fills in: the community and platform; examples are one campus or school, one creator's followers, one friend network), and why one beats broad; (2) **Day 0 seeding:** the owner and 20-30 people from the beachhead each post their link card the same day so the community sees links everywhere at once; (3) **The note-post habit:** a posting script for early users ("post the first note you get, with your link"); (4) **Micro-creator outreach:** a short template message, offering the link card and a custom username, focusing on creators with a few thousand to tens of thousands of followers inside the beachhead; (5) **Content ideas** for the owner's own channels (reply-to-notes clips, "most savage note", prompt-of-the-day) with a weekly rhythm; (6) **Prompt packs:** seasonal and community-specific prompt chips (builder adds a data file, not code, if cheap); (7) **What to measure and when to change course:** read the admin growth tiles daily; core number (guest senders who made their own link) under 5% means fix the sent-card conversion before buying attention; 10% or more means push more distribution; (8) **Safety during growth:** abuse spikes after any viral post, so check the flag queue the same day; a plan for pausing guest sends (set `TURNSTILE_SECRET` stricter, or temporarily lower rate limits) if abuse jumps.
- [ ] **Step 2: Commit** `docs: distribution playbook`.

### Task 15: Live verification and launch checklists

**Files:** Create `docs/LAUNCH.md`.

**Interfaces:** none (documentation).

- [ ] **Step 1: Write three checklists with pass/fail checkboxes:**
  - **M2 live verification (owner, unannounced):** the full journey on the real domain with real Google sign-in (sign in, username, copy link, guest send from a second browser, claim by signing in, reply, react, block, report, delete account); realtime shows the LIVE badge and a message arrives without refresh; web push on desktop Chrome and an installed Android PWA; admin sign-in with TOTP; `psst:backup` plus a restore into a scratch database; Reverb WebSocket reaches `/app` over `wss://` (the earlier local smoke test returned 200 instead of 101, so confirm); both story cards (link and received note) render and share on a real phone.
  - **M3 gate (do not announce until all pass):** Turnstile on (guest send without token returns 422); Terms and Privacy reachable; the abuse drill: send a flagged message as a guest, see it in admin, receive the owner email, ban a signed-in test sender, block a guest and confirm silent drop; Sentry receives a test exception with no body; uptime check green; Google consent screen in production; link preview verified in Instagram, Threads and WhatsApp by pasting a link in each.
  - **M4 launch week:** run `docs/GROWTH.md` day 0; daily error-tracker and flag-queue review; reports handled within a day; note the core number (admin growth tile) against the 5% and 10% thresholds; rollback rehearsal noted.
- [ ] **Step 2: Commit** `docs: launch gate checklists`.

---

## Self-review notes

- **Spec coverage:** M1 (Tasks 1-3; OAuth, VAPID, env and domain are owner-supplied and live in the runbook), M2 and M4 (Tasks 14-15), M3 (Tasks 4-8; Turnstile is configuration, covered in Tasks 3 and 15), M3.5 (Tasks 9-13; push is configuration, covered in Tasks 3 and 15). M5 Android is deliberately a separate plan.
- **Added after the owner restated the goal ("make this app boom and hit"):** Task 13 (share a received note), the `note_shared` event and tile, and Task 14 (distribution playbook). These go beyond the approved spec's M3.5 list; the owner should confirm them when approving the plan.
- **Calls made while planning:** link-card image is one static brand image (`og.png`) with the username in the title, not a per-user image (spec left the method to the builder); owner alert is email, so the owner must supply SMTP settings (added to the runbook).
- **Honest limit:** the app cannot guarantee a hit. Product mechanics only raise the odds; distribution (Task 14, run by the owner) decides most of it.
- **Not testable by the agent:** real Google sign-in, DNS, Play account, SMTP delivery, social-app preview rendering; those are owner-run checks in `docs/LAUNCH.md`.
