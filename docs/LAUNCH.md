# Launch gate checklists

Three checklists, run in order: M2 (live verification, unannounced), M3 (gate before announcing) and M4 (launch week).
Tick a box only when you have seen the pass condition yourself. Do not start the next list until the previous one is
fully ticked.

Background: `docs/superpowers/specs/2026-11-17-production-launch-design.md` (section 5 milestones, section 13 calls
made), `docs/DEPLOY.md` (setup, referenced here by section number), `docs/GROWTH.md` (day 0 and measurement).

Things the build could not test, because they need your real domain, accounts or a real phone, are collected in
"Known unverified items from the build" after M3. Work through them during M2 and M3.

Secrets: put real values only in `deploy/.env` and a password manager. Never write them in this file, a commit or a
chat. A command that takes a secret inline, such as `BACKUP_PASSPHRASE='...' scripts/restore-backup.sh` in
`docs/DEPLOY.md` section 9, leaves it in your shell history; clear it afterwards or export it from a prompt instead.

Values you must supply (fill in before starting; for secrets, tick only that they are stored, never write the value):

- Domain: `[FILL IN: <domain>]`
- SMTP provider and account name for the owner alert: `[FILL IN]`; SMTP password stored in password manager: yes / no
- Turnstile site key and secret key stored in password manager: yes / no
- Backup destination (S3-compatible bucket name, not its keys): `[FILL IN]`; bucket keys and `BACKUP_PASSPHRASE` (a copy
  kept off the server) stored in password manager: yes / no
- Play account type (personal or organisation; only matters for M5): `[FILL IN]`
- Announcement community and time: `[FILL IN]` (same field as `docs/GROWTH.md` section 2)

`docker compose` commands run from the `deploy/` directory (`docs/DEPLOY.md` intro), and `php artisan` commands run
inside the `app` container.

Use two browsers (or one normal and one private window) for the guest steps, and a real phone for the story cards.

## M2: Live verification (owner only, unannounced)

Precondition: `docs/DEPLOY.md` sections 1 to 5 are done and the section 8 smoke checks pass. The site is reachable but
not announced.

### Journey on the real domain

- [ ] **Sign in with Google.** Open `https://<domain>/` and sign in. Pass: you land in the app, no error toast.
- [ ] **Pick a username and copy the link.** Pass: the Link page shows `https://<domain>/<username>` and the copy
      action puts exactly that on the clipboard.
- [ ] **Guest send from a second browser.** In a private window (signed out) open your link and send a message. Pass:
      the send succeeds (with Turnstile on, after the challenge; see M3).
- [ ] **Realtime.** Keep the inbox open in the first browser, then send again as the guest. Pass: the "live" badge
      shows and the new message appears without a refresh.
- [ ] **Reverb reaches `/app` over `wss://`.** The earlier local smoke test returned 200 instead of 101, so confirm it
      on the real domain. In browser dev tools (Network, WS filter) the `/app/...` request shows status
      `101 Switching Protocols` and stays open. Pass: 101 and the realtime step above works. Fail: 200 or a closed
      socket; check `REVERB_ALLOWED_ORIGINS`, that `QCLI_REVERB_KEY` equals `REVERB_APP_KEY` (`docs/DEPLOY.md`
      section 2) and the `@ws` block in `deploy/Caddyfile`.
- [ ] **Claim by signing in.** In the guest browser, sign in with a second Google account after a guest send. Pass: the
      earlier guest message shows under Sent for that account.
- [ ] **Reply and react.** Reply to a message as the receiver and react to one. Pass: the other side sees both live.
- [ ] **Block.** As the receiver, use "Block sender" on a thread. Pass: the "You blocked this sender" banner shows and
      "Unblock" is offered.
- [ ] **Report.** Use "Report message" on a message. Pass: "Thanks. We'll take a look." shows and the report appears
      on the admin Reports page.
- [ ] **Delete account.** With a spare test account: Settings, "Delete account", type the name to confirm. Pass: the
      account is gone and its link no longer resolves.

### Guest network identity

- [ ] **Per-network `ip_hash` is distinct.** Send guest messages from two different networks, including a phone on
      mobile data (not the same Wi-Fi). Then run on the database:
      `select count(distinct ip_hash) from threads where sender_id is null and created_at > now() - interval '1 hour';`
      Pass: 2 or more. Fail: 1 means client IPs are collapsing (IPv6 behind the Docker proxy, or Cloudflare proxying
      the domain); see `docs/DEPLOY.md` section 1 step 2.

### Browser suites against the live origin

- [ ] `BASE_URL=https://<domain> pnpm e2e` in `client/`, plus the other client suites you want (`e2e:realtime`,
      `e2e:account`, `e2e:guest`, `e2e:two-people`; see the `scripts` block of `client/package.json`) and `pnpm e2e` in
      `admin/`. Pass: every suite you run passes. Some suites may sign in with the dev login, which is off in
      production: read each script's header first and skip or adapt those. Do not turn the dev login on in production
      to make them pass. Run the guest suites before Turnstile is on (M3); with it on they may fail for lack of a token.

### Push

- [ ] **Desktop Chrome.** First check `curl -sk https://<domain>/api/push/config` returns a non-null `public_key`
      (`docs/DEPLOY.md` sections 7 and 8). Turn on notifications when prompted, background the tab, send a message from
      another browser. Pass: a system notification arrives.
- [ ] **Installed Android PWA.** Install from Chrome ("Add to Home screen"), open it from the icon, turn on
      notifications, send a message while the app is closed. Pass: a notification arrives and tapping it opens the
      thread.

### Admin

- [ ] **First admin with TOTP.** `docs/DEPLOY.md` section 5: `psst:make-admin <email>`, open `https://<domain>/admin/`,
      scan the QR code, enter the code. Pass: you reach the dashboard; signing out and back in asks for a code.

### Backup and restore

- [ ] **Backup.** `docker compose exec app php artisan psst:backup`. Pass: prints `Uploaded psst-...sql.gz.enc` and the
      file is in the bucket (`docs/DEPLOY.md` section 9).
- [ ] **Restore into a scratch database** with `scripts/restore-backup.sh` as in `docs/DEPLOY.md` section 9. Pass:
      `restored ... into psst_restore_test` and a plausible `select count(*) from users;`. Then `dropdb`. The restore
      runs on a host that has `psql`, `openssl` and `gunzip`, not inside the app image. Never point the script at the
      live database.

### Story cards on a real phone

- [ ] **Link card.** Link page, "Share story card", try the styles. Pass: a 9:16 card renders and the native share sheet
      opens. Details in the carry-forward section below.
- [ ] **Received-note card.** Inbox, "Share this note to your story" on a received note. Pass: the card renders and the
      share sheet opens. Details below.

M2 exit (design doc section 5): the full journey passes on the live domain, both the browser suites and your manual run,
with no open bug rated blocking.

## M3: Gate (do not announce until every box passes)

### Abuse controls

- [ ] **Turnstile on.** `TURNSTILE_SECRET` in `deploy/.env`, `QCLI_TURNSTILE_SITE_KEY` in `client/.env`, client
      rebuilt, containers recreated (`docs/DEPLOY.md` section 6). Pass: the `curl` in that section (a guest send with
      no token) prints `422` and the body says "We could not verify you are human", and a real guest send through the
      browser widget still works. A `201` means the secret is empty (the challenge is off), and that run creates a
      real message.
- [ ] **Dev login is off in production.** In `deploy/.env`: `grep -x 'PSST_DEV_LOGIN=false' deploy/.env` prints the
      line. In `client/.env` (the example defaults it to `true`): `grep -x 'QCLI_DEV_LOGIN=false' client/.env` prints the
      line, and the client was rebuilt after that value was set (`docs/DEPLOY.md` section 4). On the live site:
      `curl -sk -o /dev/null -w '%{http_code}\n' -X POST https://<domain>/api/dev/login -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{"email":"x@example.test"}'`
      prints `404` (the route is not registered in production), and the login page at `https://<domain>/login` shows
      only the real sign-in buttons, no dev sign-in block.
- [ ] **Terms and Privacy reachable.** `https://<domain>/terms` and `https://<domain>/privacy` load while signed out,
      are linked from sign-in and Settings, and the contact line is a real address (see carry-forward below).
- [ ] **Abuse drill (design doc section 5, M3 exit).** Do all of these in one sitting:
  - [ ] Owner alert configured: `ADMIN_ALERT_EMAIL` and the `MAIL_*` values in `deploy/.env` (`docs/DEPLOY.md`
        section 2), containers recreated. `APP_URL` is `https://<domain>` exactly, because the email's link is built from
        it. The alert is a queued job, so the `queue` container must be running or no email is sent.
  - [ ] As a guest, send a message containing a default flag keyword (list: `moderation.keywords` in
        `server/config/psst.php`). Pass: the receiver still gets the message normally (flagged messages are delivered).
  - [ ] Open `https://<domain>/admin/flags` as the admin. Pass: the message is in the open tab; marking it reviewed
        moves it to the reviewed tab.
  - [ ] Pass: the owner email arrives (at most one per 10 minutes), contains no message text, and its link goes to
        `https://<domain>/admin/flags`. A second flagged message within 10 minutes does not send a second email; to
        retest, wait 10 minutes or clear the cache.
  - [ ] Ban a signed-in test sender from the admin Users page. Pass: that account's next request is refused with HTTP
        403 and the message "This account has been suspended."; unbanning restores it.
  - [ ] As a receiver, block a guest, then send again as that guest. Pass: the guest sees a normal success, nothing
        reaches that receiver, and a different receiver still receives that guest's messages (blocks are per receiver).

### Observability

- [ ] **Sentry receives a test exception with no body.** `docs/DEPLOY.md` section 11: with `SENTRY_LARAVEL_DSN` set,
      `docker compose exec app php artisan sentry:test`. Pass: the event shows in the project's Issues within a minute
      and has no request body, cookies, query string, user or IP.
- [ ] **Uptime check green.** A monitor on `https://<domain>/up` every 1 to 5 minutes, alerting your email
      (`docs/DEPLOY.md` section 11). Pass: green, and briefly stopping `app` (`docker compose stop app`, then
      `docker compose start app`) fires the alert. This takes the site down: do it only before announcing.

### Sign-in and previews

- [ ] **Google consent screen in production.** In the Google Cloud console the publishing status is "In production", not
      "Testing", with only `openid`, `email`, `profile` requested (`docs/DEPLOY.md` section 3). Pass: a Google account
      that is not a listed test user can sign in.
- [ ] **Preview markup for crawlers.**
      `curl -sk -A "facebookexternalhit/1.1" https://<domain>/<username> | grep -E "og:title|robots"` shows `og:title`
      and `noindex, nofollow`; the same URL without `-A` returns the app shell (`docs/DEPLOY.md` section 8). The route
      behind it is `/preview/{username}`.
- [ ] **Instagram, Threads, WhatsApp.** Paste your link in each (a DM or draft is enough). Pass: each shows a card with
      the username title and the brand image, not a blank or generic card. If a platform shows a stale card, use its
      debugger or wait and re-paste before treating it as a failure.

### Rest of the safety net

- [ ] The "Known unverified items from the build" section below is fully ticked.

M3 exit: every box above passes. Only then announce.

## Known unverified items from the build (carry-forward)

The build could not verify these. Check each live.

### Admin (real browser, 2FA admin session)

- [ ] **Flags page.** `/admin/flags`: open and reviewed tabs work, "mark reviewed" moves a flag; opening the URL directly
      from the alert email loads the page through the SPA fallback (not a 404).
- [ ] **Growth tiles.** Dashboard shows five tiles (Guest sends, Link shares, Note shares, Own links after guest,
      Conversion); at lg width they are not cramped; Conversion shows an em dash when there are no guest sends; the
      dashboard still renders if the growth block is missing.

### Story cards and sharing (real phone)

- [ ] **Link card and received-note card** render on a real phone, in light and dark mode.
- [ ] **Native share sheet** on iOS Safari and Android Chrome: cancelling is silent; if the share sheet fails the PNG
      downloads instead. Capacitor webviews are checked later, in M5.
- [ ] **Instagram story safe zones:** after posting, the wordmark and the link chip are not covered by the story UI.
- [ ] **Received-note first-share notice.** The notice dialog opens on the first tap (a first real pointer click once did
      not visibly open it in a test; confirm on a real phone and on desktop), the second share skips the notice, and a
      note you sent yourself shows no share button.
- [ ] **Conversion card and growth event.** After a guest send the "Want your own link?" card shows, and after claiming a
      link in the same browser the admin tile "Own links after guest" counts it (the server row was never observed end
      to end).

### Backups

- [ ] **`psst:backup` against the real Postgres and the real bucket** uploads without error.
- [ ] **Restore drill** into a scratch database with `scripts/restore-backup.sh` (never run against a real Postgres
      during the build); see M2.
- [ ] **Image has `pg_dump`.** Build the real image once (`docker compose build`, `docs/DEPLOY.md` section 2); the Alpine
      package `postgresql16-client` (in `server/Dockerfile`) was never verified. Pass: the build succeeds and
      `docker compose run --rm --no-deps app pg_dump --version` prints a version.

### Sign-in, push and mail

- [ ] **Real Google sign-in on the production domain** works for a non-test user; consent screen published; the
      authorised JavaScript origin is `https://<domain>` (`docs/DEPLOY.md` section 3).
- [ ] **VAPID web push** works on desktop Chrome and on an installed Android PWA (the M2 push boxes).
- [ ] **SMTP delivery of the owner alert** reaches your inbox; the mail contains no message text and the link goes to
      `/admin/flags`.

### Security hygiene (before announcing)

- [ ] `composer audit` (in `server/`) and `pnpm audit` (in `client/` and `admin/`) are run and the flagged packages are
      upgraded. The build saw 8 advisories on 5 existing packages: `laravel/framework`, `symfony/http-foundation`,
      `symfony/http-kernel`, `symfony/mime`, `symfony/routing`. Pass: no known advisory left unexplained, and the
      server tests still pass.
- [ ] **Reserved usernames.** If the database was seeded or imported, run the `tinker` check in `docs/DEPLOY.md`
      section 8. Pass: it prints an empty collection.

### Content and legal

- [ ] **Keyword list review.** The default flag keywords (`moderation.keywords` in `server/config/psst.php`) include
      terms like "kys", "suicide" and "shoot up" that also flag harmless mentions. Pass: you have decided the final
      list and deployed any change.
- [ ] **Terms and Privacy.** Replace the `legal.contact` placeholder in `client/src/i18n/en-US/index.ts` with a real
      address, have the wording reviewed by a professional, and bump the "Last updated" date (the `new Date("...")` in
      `client/src/pages/TermsPage.vue` and `PrivacyPage.vue`) whenever the text changes. Rebuild the client. Pass: the
      pages show your address and the right date.

### Sentry

- [ ] **First reports watched for text leaks.** Exception messages that are not SQL are sent up to 200 characters. Pass:
      the first reports contain no message text; if one does, stop and fix the scrubber (`App\Observability\ScrubEvent`)
      before continuing.

## M4: Launch week

Precondition: M3 fully ticked.

- [ ] **Day 0 run.** Follow `docs/GROWTH.md` section 2 (pre-announcement checks are in its section 1). Pass: you posted
      first and the beachhead's stories went up.
- [ ] **Daily error-tracker review.** Open Sentry Issues every day for seven days. Pass: no unhandled error spike; any
      new issue triaged the same day.
- [ ] **Daily flag-queue review.** `https://<domain>/admin/flags` every day (the 10-minute routine is in
      `docs/GROWTH.md` section 7). Pass: the open tab is empty or every item reviewed.
- [ ] **Reports handled within a day.** Admin Reports page. Pass: no report older than 24 hours is unresolved.
- [ ] **Core number.** Read the admin Growth "Conversion" tile (own links per guest send, last 7 days) daily and write it
      down. Compare it with the 5% and 10% thresholds and act per the table in `docs/GROWTH.md` section 7. Under about
      30 guest sends in the window it is noise; do not change course on it.
- [ ] **Rollback rehearsal noted.** Practise `docs/DEPLOY.md` section 10 (previous `APP_TAG`,
      `docker compose up -d --no-build`) once on the real host and note how long it took and that `/up` stayed green.
      Result: `[FILL IN]`
- [ ] **Backups still running.** After the first nightly run (03:00) a new file is in the bucket.

M4 exit (design doc section 5): one week live with no data loss, no unhandled error spike, and abuse reports handled
within a day.

### Rollback and abort criteria

Stop announcing, and do not post your link again, when any of these happens:

- [ ] **Abuse spike:** the flag queue or reports grow faster than you can clear them the same day, or a harassment
      pattern targets a receiver.
- [ ] **Error-rate spike:** Sentry shows a new unhandled error repeating after a deploy, or the uptime check alerts and
      does not clear.
- [ ] **Data leak suspicion:** any sign that message text, sender identity or a guest link reached a place it should
      not (an email body, a Sentry event, a preview card).

What to do:

1. Bad deploy: roll back as in `docs/DEPLOY.md` section 10 (previous `APP_TAG`, `docker compose up -d --no-build`;
   rebuild the front-ends from the previous commit if the bug is in the UI). Do not run `migrate:rollback`. Damaged
   data: restore from backup (`docs/DEPLOY.md` section 9).
2. Abuse: use the levers that exist, in `docs/GROWTH.md` section 8: turn Turnstile on if it is off, ban abusers, resolve
   reports, tell receivers they can block, and tighten the `messages` limiter in
   `server/app/Providers/AppServiceProvider.php` (a code change plus a redeploy).
3. There is no pause switch for guest sends. If you need one it has to be built first; until then, stopping the
   announcement is the only brake on new traffic.
