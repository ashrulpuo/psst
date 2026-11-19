# Psst: production launch and growth design

Date: 2026-11-17. Status: draft for approval. Baseline: commit `97b1f1d` (see `docs/RELEASE.md`).

## 1. Intent and why

Psst is anonymous messaging with two-way chat. A receiver signs in (Google or Apple), gets a public link
`https://<domain>/<username>`, and anyone can send to it with no account. Signing in unlocks chat.

The owner wants real users on it, not a demo: **web and PWA first, then Android**, hosted on the owner's own VPS, and
**growth** (the link spreading through stories and chats) treated as part of the launch, not an afterthought.

Why now: the code is feature-complete for v1. Server tests pass (230/230) and the core UI flows were driven by hand in a
browser on 2026-11-17 (guest send, sign-in, inbox, live arrival, reply, reactions, block menu). What stands between the
code and real users is production setup, a handful of missing public-facing pieces, and a growth loop that is wired but
untuned.

## 2. Goals, non-goals, anti-goals

### Goals
1. Psst runs on the owner's VPS over TLS with real Google sign-in, realtime and web push working end to end.
2. A public launch that strangers can use safely: legal pages, bot and abuse controls, moderation tooling, backups,
   error visibility.
3. A measurable growth loop with a target for the number that matters (section 9).
4. An Android build from the same codebase, published to Google Play.

### Non-goals (for this design)
- iOS and Sign in with Apple. (Apple needs a Mac, an Apple Developer account and a Services ID. Deferred.)
- macOS and Windows desktop binaries, code signing, auto-update.
- Paid features, ads, monetisation.
- New languages beyond `en-US`. (Revisit after launch, see section 12.)
- Rewriting any feature that already works.

### Anti-goals (failure even if it technically works)
- Opening to the public before abuse controls exist. A hyped anonymous app that becomes a harassment tool gets removed
  from Instagram, Play and community spaces, which kills the growth this design is trying to create.
- Marketing "totally anonymous" while admins can identify signed-in senders. The product must say what is true.
- A launch that cannot be rolled back, or loses user data (no backup, no restore test).

## 3. Constraints

- **Hosting:** one VPS, existing `deploy/docker-compose.yml` (app, reverb, queue, scheduler, postgres, redis, caddy).
  The images are built from this repo. TLS through Caddy.
- **Stack is fixed:** Laravel (PHP 8.3, Postgres 16, Sanctum, Reverb) + Quasar/Vue client + separate Vue admin SPA +
  Capacitor for Android. Build on what exists.
- **Auth:** Google only for launch. With client IDs empty the API fails closed (no one can sign in). `PSST_DEV_LOGIN`
  must be false in production because it is a login bypass.
- **The owner supplies** credentials and accounts: domain, VPS access, Google Cloud OAuth client, Play Console account,
  Android release keystore. These are blockers the builder cannot create (see section 8).
- Privacy statement: receivers never see sender identity; admins can identify signed-in senders and cannot identify
  guests. Terms and Privacy must say this plainly.

## 4. Approaches considered

| | Approach | Trade-off |
|---|---|---|
| A | Build all safety and growth work first, deploy once, launch | Cleanest launch, but nothing meets real OAuth, TLS or the WebSocket behind Caddy until late. Infra bugs surface at the end. |
| **B** | **Deploy early to the VPS unannounced, verify it yourself, harden, then announce publicly (no beta group, owner decision)** | **Infra and sign-in bugs surface in week one without needing testers. Risk: the site is reachable before it is safe, so it must not be announced until M3 passes.** |
| C | One big milestone for everything | Fastest to plan, hardest to debug, no early feedback. |

**Chosen: B.** It was recommended to the owner, who did not object and said to proceed; confirm at approval. The
audience was not stated, so the plan assumes **strangers will find it publicly**, which is the safer assumption. If the
first users are a small known group, M3 safety work can be trimmed.

## 5. Milestones

Each milestone has an exit check. Do not start the next until the exit check passes.

### M1: Foundations (production environment)
- Fill `deploy/.env` from `deploy/.env.example` using the checklist in `docs/RELEASE.md` (APP_KEY, APP_ENV=production,
  APP_DEBUG=false, real DB and Redis passwords, CORS, SANCTUM_STATEFUL_DOMAINS, Reverb keys, redis session, queue, cache).
- Register the Google OAuth web client; put the ID in `GOOGLE_CLIENT_IDS` (server) and `QCLI_GOOGLE_CLIENT_ID` (client,
  inlined at build time).
- Generate VAPID keys (`php artisan psst:vapid`); keep `PUSH_EXTRA_ALLOWED_HOSTS` empty.
- Build client (`pnpm build:pwa`) and admin; `docker compose up -d`; `php artisan migrate --force`; Caddy serves TLS.
- Database backups: scheduled dump, off-box copy, and one restored-from-backup test.
- Trace and fix the 3 console errors seen on 2026-11-17 (two 401 responses, one 404).
- **No invite gating (owner decision: no beta).** The site is public from the first deploy, but it is not announced
  until M3 passes. Until then only the owner uses it.
- Backups: nightly dump, encrypted, copied off the box (destination supplied by the owner, section 8). My call: 24 hours
  of possible data loss is acceptable until launch; tighten before M4 if the owner wants.
- Deploys: migrations must be backward compatible (add first, remove later) so a rollback to the previous image never
  needs a database restore. A short maintenance window during a deploy is acceptable (my call); no zero-downtime work.
- Start the Google OAuth consent-screen check here (see section 10).
- **Exit:** the site loads over HTTPS on the real domain, `PSST_DEV_LOGIN=false`, `/up` healthy, and a restore from
  backup has been proven once.

### M2: Live verification (owner only, unannounced)
- No beta group. The owner, plus one or two people the owner chooses, run the real site before it is announced.
- Real Google sign-in through the whole journey: sign in, pick username, share link, guest sends, claim, reply, block,
  report, delete account.
- Run the browser suites against the live origin (`BASE_URL=https://... pnpm e2e` and the other suites). Fix whatever
  breaks. Create the first admin with `psst:make-admin` and complete TOTP 2FA on the real domain.
- Verify web push on desktop Chrome and on an installed PWA on Android.
- **Exit:** the full journey passes on the live domain for both the browser suites and a manual run by the owner, with
  no open bugs rated blocking.

### M3: Public-ready (safety and trust)
- **Legal:** Terms and Privacy pages, linked from sign-in and settings, stating the anonymity truth (section 3). Needed
  for Play review as well.
- **Abuse:** turn on Turnstile (`TURNSTILE_SECRET`, `QCLI_TURNSTILE_SITE_KEY`) and retune guest rate limits.
- **Moderation (my call on policy):** keyword flagging on inbound messages. Flagged messages are **still delivered**
  (holding them would change the delivery, inbox and push paths), the receiver sees nothing different, and the flag
  appears in the admin report queue. The owner is assumed to be the only moderator, so new flags notify the admin (push
  or email) instead of relying on someone watching the queue.
- **Bans, signed-in senders:** a ban stops the account's access; verify it.
- **Bans, guests:** guests are not bannable as accounts. The existing design (`docs/PLAN.md`) blocks a guest **per
  receiver**, by browser token or IP hash, and the sender is never told. The abuse drill tests that, plus global
  rate limits and Turnstile for guests. Do not promise a global guest ban; it would need fingerprinting.
- **Observability (my call):** a hosted error tracker on its free tier with message bodies scrubbed before they leave the
  server (no user content to a third party), plus an uptime check on `/up`. Self-hosting is avoided so the single VPS
  does not take on more load. The owner can override this.
- **Exit:** a documented abuse drill passes: a test sender sends flagged and spam content, the flag appears in admin and
  notifies the owner, a signed-in sender is banned and refused, and a guest blocked by one receiver is silently dropped
  for that receiver only.

### M3.5: Growth loop (see section 9)
- Link-preview cards for `/<username>` (OG and Twitter tags with a generated image and clear title). My call: the app is
  a client-rendered SPA, so crawlers get tags from a small server route chosen by Caddy for known bot user agents. The
  card shows the username and a generic invitation only, **not** the receiver's Google name or avatar (privacy).
  Profile pages are `noindex` (nothing sets this today) so search engines do not list people's links.
- A prominent "get your own link" conversion card after a guest sends (today it is a small text link).
- Web push live and tested (depends on M1 VAPID).
- More story-card styles (4-5 templates) for the receiver's share flow.
- A small admin view for the loop metrics (section 9).
- **Exit:** sharing a link into Instagram, Threads and WhatsApp shows a correct preview card; the conversion card
  and push are covered by browser tests; the metrics view shows real numbers from the owner's own test traffic.

### M4: Web and PWA public launch
- Announce it. Seed the loop with the owner's own audience, since there is no beta crowd (the owner names the community
  and platform before this milestone; see section 12). Watch error tracking and the admin report queue daily for the
  first week. Keep a rollback path (previous image tag plus the backup).
- **Exit:** one week live with no data loss, no unhandled error spike, and abuse reports handled within a day.

### M5: Android
- Release keystore, `ANDROID_HOME`, `pnpm build:android`, signed `.aab`.
- Fill `deploy/wellknown/assetlinks.json` with the Play App Signing fingerprint; redeploy Caddy.
- FCM: create the Firebase project, add its credentials server side; native Google sign-in; deep links verified.
- Play listing: store text, screenshots, data-safety form, the privacy policy URL from M3, account-deletion URL.
- Internal testing track first, then production.
- **Exit:** the app installs from the Play internal track, signs in with Google, receives a push, and opens a shared
  link from outside the app.

## 6. Decisions already made (by the owner)
- Web and PWA first, then Android. Real users, not a demo.
- Hosting on the owner's VPS with the existing Docker setup.
- Growth is part of the launch design. No closed beta: the owner's own audience is the seed crowd at launch.

## 7. How work proceeds
Build and fix bugs milestone by milestone until the exit checks pass. Bugs found during a milestone are fixed inside it;
anything that would change scope goes back to the owner.

## 8. What the owner must supply (blockers for the builder)
1. Domain and DNS access, VPS login.
2. Google Cloud project and OAuth web client (authorised origins: the production origin and `http://localhost:9000`).
3. Turnstile site and secret keys (Cloudflare account).
4. Play Console account, Android keystore, Firebase project for FCM.
5. A free-tier account for the error tracker and uptime check (or an override of my call in M3).
6. A place for off-box backups (S3-compatible bucket, a second server, or similar) and its credentials.
7. The final production domain, bought and with DNS under your control. The Android app-link host cannot change later
   without a new release, so this is fixed before M5. The admin SPA stays on the same origin at `/admin/` (already how
   Caddy is set up).

## 9. Growth design

**The loop:** a receiver posts their link on a story; friends tap and send with no signup; the receiver gets a push and
posts again; a share of senders tap "get your own link" and become receivers.

**Strategy (my call, owner approved the direction):** seed one tight community rather than launch broadly, so people see
each other's links and the loop starts. With no beta, the owner's own followers or community at launch (M4) is that
group. The audience and platform were not specified; the plan assumes Instagram stories, Threads and WhatsApp.

**Privacy rule for measurement (my call):** use aggregate counters only. Count "a guest sent a message" and "a new
username was created within the same browser session after a guest send", with no per-person join between a guest and
an account beyond what the existing sign-in claim already does. The Privacy page states that anonymous usage counts are
collected.

**Metrics** (measurable from existing data plus a small amount of event counting):
- **Core:** share of guest senders who create their own link. Target 10% or higher; investigate below 5%.
- Story or link shares per receiver (the Share and Copy actions).
- 7-day return rate for receivers.

**Honest limits:** these targets are guesses, not benchmarks from psst. They tell the owner whether the loop works and
should be revised after the first weeks live.

## 10. Risks
- **Abuse after hype** (highest). Mitigation: M3 gates M4; do not announce the site until the drill passes. With no invite gate, anyone who finds
  the URL early can use it, so keep it unlinked until then.
- **Google OAuth consent screen.** In Testing mode only listed test users (up to 100) can sign in, which would silently
  break M4. My call: request only the basic scopes (openid, email, profile), check the consent screen status in M1, and
  move it to production once the Terms and Privacy URLs exist at M3. Verify against Google's current rules at that time
  rather than trusting this paragraph, since I have not confirmed whether basic scopes need any review.
- **Play account type (fact to confirm):** new personal developer accounts have been required to run a closed test with
  a minimum number of testers for about two weeks before production access. An organisation account avoids this. This
  can add weeks to M5.
- **Reverb behind Caddy** (WebSocket upgrade, allowed origins). An earlier local smoke test returned 200 rather than 101
  on the WebSocket path; verify against the real domain in M2.
- **Play review** (data safety, account deletion, anonymous-content policy). Mitigation: Terms, Privacy and the existing
  in-app delete flow; expect questions about moderation.
- **Single VPS** means no redundancy. Accepted for launch; backups are the safety net.

## 11. Left to the builder
- Choice of error tracking and uptime tools, within the constraint that user content is not sent to a third party without
  the owner's say-so.
- Exact keyword-flagging approach and wordlist (flag, not auto-delete).
- Preview image generation method for link cards.
- Test and rollout details inside each milestone.

## 12. Open questions for the owner
- Who exactly are the first users, and which platform do they live on? (Assumed: strangers, Instagram, Threads, WhatsApp.)
- Is a single `en-US` locale acceptable for launch, or does the audience need another language?
- Is Google-only sign-in acceptable at launch, with Apple deferred to the iOS phase?
- Is the production domain decided and bought, and is your Play Console account personal or an organisation?
- Where should off-box backups go?

## 13. Calls made by the builder-check pass (check these)
Flagged messages delivered, not held; guest bans are per-receiver only; 24-hour backup loss accepted until launch; backward-compatible migrations with a short maintenance window; hosted
free-tier error tracking with bodies scrubbed; crawler-only preview route with no name or avatar and `noindex`; aggregate
counters for growth metrics; basic OAuth scopes only; owner is the only moderator, with admin notifications on flags.
No target dates were set; milestones run strictly in order.
