# Production deploy runbook

Step-by-step from an empty VPS to a live Psst. Follow the sections in order. Every command here exists in this repo
(`php artisan list | grep psst`, `ls scripts`, the `scripts` blocks of `client/package.json` and `admin/package.json`).

Placeholders: `<domain>` is your public hostname (for example `psst.example.com`), `<email>` is a real mailbox.
Never paste real secrets into this file or commit them; they live only in `deploy/.env` on the server.

Docker Compose commands run from the `deploy/` directory. `php artisan` commands run inside the `app` container
(`docker compose exec app php artisan ...`).

Background: `docs/RELEASE.md` explains what is verified and what is not. Section 2 turns its "Fatal if wrong"
checklist into a fill-in table.

## 1. DNS and VPS prerequisites

1. A Linux VPS with a public IPv4 address, at least 2 GB RAM, Docker Engine and the Docker Compose plugin, `git`,
   Node.js 22.12 or newer (24 also works; `client/package.json` requires `>= 26 || ^24 || ^22.12`) with `pnpm`. The app image is built from `php:8.3-fpm-alpine` and the project is pinned to PHP 8.3, so
   there is nothing to install for PHP on the host.
2. An `A` record for `<domain>` pointing at the VPS. Wait for it to resolve. **Do not publish an `AAAA` record**
   unless Docker IPv6 is enabled (`"ipv6": true` and `"ip6tables": true` in `/etc/docker/daemon.json`, plus
   `enable_ipv6: true` on the compose public network). Without that, Docker's userland proxy rewrites every IPv6
   client to the same internal address, so all IPv6 visitors share one per-IP rate limit and one `ip_hash`, and guest
   blocks and limits collapse onto a single bucket. Keep the record DNS-only: do not proxy the domain through
   Cloudflare (orange cloud). Behind the proxy the app sees Cloudflare edge IPs, so per-IP limits and `ip_hash` would
   key on the edge, not the visitor.
3. Open inbound ports 80 and 443 (TCP) and 443 (UDP, HTTP/3). Caddy needs port 80 reachable to obtain the
   certificate. Nothing else should be public: Postgres and Redis are only on the internal Docker network.
4. Clone the repository on the server and work from the checkout.

Verify DNS and Docker:

```sh
dig +short <domain>
docker compose version
```

Expected: `dig` prints the VPS IP address; `docker compose version` prints `Docker Compose version v2.x`.

## 2. Fill in `deploy/.env`

```sh
cd deploy
cp .env.example .env
chmod 600 .env
```

`deploy/.env.example` contains every variable below (empty ones are the ones you fill in). Generate each secret with a password manager or `openssl rand -hex 24`.

Generate `APP_KEY` (needs the image, so build it first):

```sh
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate --show
```

Expected: one line starting `base64:`. Paste it as `APP_KEY=`. Use `--show`; without it the command would try to
rewrite a `.env` file inside the read-only container.

### Required (fatal if wrong)

| Variable | Value | In example? |
|---|---|---|
| `APP_DOMAIN` | `<domain>` | yes |
| `ACME_EMAIL` | `<email>` (Let's Encrypt notices) | yes |
| `APP_TAG` | image tag for this release, for example `2026-11-17` (see section 10) | yes |
| `APP_ENV` | `production` | yes |
| `APP_DEBUG` | `false` | yes |
| `APP_URL` | `https://<domain>` | yes |
| `APP_KEY` | output of `key:generate --show` | yes (empty) |
| `DB_PASSWORD` | long random value, not `change-me` | yes |
| `REDIS_PASSWORD` | long random value, not `change-me` | yes |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` | `redis` | yes |
| `BROADCAST_CONNECTION` | `reverb` | yes |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | random values you generate (the secret stays private; the key is also the client build's `QCLI_REVERB_KEY`) | yes (empty) |
| `REVERB_ALLOWED_ORIGINS` | `<domain>` | yes |
| `GOOGLE_CLIENT_IDS` | OAuth client ID from section 3 | yes (empty) |
| `APPLE_CLIENT_IDS` | Apple Services ID; empty means Apple sign-in is rejected | yes (empty) |
| `CORS_ALLOWED_ORIGINS` | `capacitor://localhost,https://localhost,https://<domain>` (native builds need the first two) | yes (edit the domain) |
| `SANCTUM_STATEFUL_DOMAINS` | `<domain>` | yes (edit the domain) |

Keep the defaults already in the example: `DB_*`, `REDIS_HOST=redis`, `LOG_CHANNEL=stderr`, `LOG_LEVEL=info`,
`REVERB_HOST=reverb`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`.

### Before inviting users

| Variable | Value | In example? |
|---|---|---|
| `PSST_DEV_LOGIN` | `false`. The route refuses to register in production anyway; keep it explicit. | yes |
| `PUSH_EXTRA_ALLOWED_HOSTS` | leave unset in production | no (leave out) |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | from `psst:vapid` (section 7); subject `mailto:<email>` | yes (empty) |
| `ADMIN_ALERT_EMAIL`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | where flagged-message alerts go (no message text in the mail) and the SMTP account that sends them; empty address = no email | yes (empty) |
| `TURNSTILE_SECRET` | Cloudflare Turnstile secret (section 6), or empty to run without a challenge | yes (empty) |

### Backups

Nightly at 03:00 the `scheduler` service runs `php artisan psst:backup`: a `pg_dump`, gzip, AES-256 encryption and an
upload to an S3-compatible bucket on the `backups` disk. Backups older than 13 days are pruned (`psst.backup.keep_days`; pruning runs only after a successful backup, so
encrypted copies are gone within the 14 days the privacy page promises). With
`BACKUP_PASSPHRASE` empty the command refuses to run. **Losing the passphrase makes every backup unreadable: keep a
copy outside this server.**

| Variable | Value | In example? |
|---|---|---|
| `BACKUP_PASSPHRASE` | long random value | yes (empty) |
| `BACKUP_S3_ENDPOINT` | bucket endpoint URL (Cloudflare R2, Backblaze B2, MinIO, ...) | yes (empty) |
| `BACKUP_S3_BUCKET` | bucket name | yes (empty) |
| `BACKUP_S3_KEY` | access key ID | yes (empty) |
| `BACKUP_S3_SECRET` | secret access key | yes (empty) |
| `BACKUP_S3_REGION` | `auto`; change only if your provider needs a real region | yes |

### Send limits and the guest pause

`PSST_GUEST_SENDS` (default `true`) pauses anonymous sending when `false`; the limits are `PSST_GUEST_PER_IP_MINUTE`
(4), `PSST_GUEST_PER_IP_HOUR` (30), `PSST_GUEST_PER_TOKEN_MINUTE` (3), `PSST_GUEST_PER_TARGET_HOUR` (200),
`PSST_USER_PER_MINUTE` (20) and `PSST_USER_PER_DAY` (300). All are in `deploy/.env.example`. Changes need
`docker compose up -d --force-recreate app` (and `php artisan config:cache` if config is cached).

### Needed from the later task

The code at the commit this runbook was written against does not read these yet. They are listed so the table stays
accurate once the production-launch tasks that use them are merged.

| Variable | Used for | Needed from the later task |
|---|---|---|
| `SENTRY_LARAVEL_DSN` | error reporting from the API | Task 7 (Sentry and uptime) |
| `ADMIN_ALERT_EMAIL` | where flagged-message alerts are sent | Task 6 |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | outbound mail for those alerts (your SMTP provider's values) | Task 6 |

Check the required keys are all set, and that nothing unsafe remains:

```sh
for k in APP_KEY DB_PASSWORD REDIS_PASSWORD GOOGLE_CLIENT_IDS REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET CORS_ALLOWED_ORIGINS SANCTUM_STATEFUL_DOMAINS; do
  grep -qE "^$k=.+" .env || echo "MISSING or empty: $k"
done
grep -qx 'PSST_DEV_LOGIN=false' .env || echo "PSST_DEV_LOGIN must be false"
grep -n 'change-me' .env && echo "replace the change-me values"
grep -n 'example.com' .env && echo "replace the example.com placeholders"
```

Expected: no output at all. Every line printed is a value you still have to fix.

## 3. Google OAuth client

1. Google Cloud Console, *APIs & Services, OAuth consent screen*: set the app name, support email and authorised
   domain `<domain>`.
2. *Credentials, Create credentials, OAuth client ID, Web application*. Add `https://<domain>` under *Authorised
   JavaScript origins*.
3. Put the resulting `...apps.googleusercontent.com` ID in two places:
   - `GOOGLE_CLIENT_IDS=` in `deploy/.env` (the server verifies tokens against it; comma-separated for several
     clients);
   - `QCLI_GOOGLE_CLIENT_ID=` in `client/.env` (the client renders the button; inlined at build time, see section 4).
     The iOS app uses its own client ID in `QCLI_GOOGLE_IOS_CLIENT_ID`, which must also be listed in
     `GOOGLE_CLIENT_IDS`.

**Consent screen publishing status.** A new consent screen starts in "Testing", where only listed test users can
sign in. Move it to production before the public launch (before M4, the public opening), or strangers are refused.
Request only the basic scopes: `openid`, `email`, `profile`. Verify against Google's current rules; this runbook has
not confirmed whether basic scopes need any review, so check the console's publishing page and Google's
documentation rather than assuming the move is instant.

Apple sign-in needs a Services ID, a key and an https return URL; see the README section "Real Google / Apple
sign-in" and `docs/RELEASE.md`. It is optional for a web-first launch.

## 4. Build the front-ends, start the stack, migrate

Caddy serves the build output straight from the checkout, so both builds must exist before `docker compose up`.

Client. `QCLI_*` values are inlined at build time, so rebuild whenever one changes.

```sh
cd client
cp .env.example .env     # skip if it exists; then edit it
```

Set in `client/.env`: `QCLI_GOOGLE_CLIENT_ID`, `QCLI_REVERB_KEY` (same value as `REVERB_APP_KEY`),
`QCLI_PUBLIC_URL=https://<domain>`, `QCLI_DEV_LOGIN=false` (the example defaults it to `true`), and
`QCLI_TURNSTILE_SITE_KEY` if you use Turnstile (section 6). Leave `QCLI_API_URL` empty (web builds use their own
origin).

```sh
pnpm install --frozen-lockfile
pnpm build:pwa
```

Expected: the build finishes without errors and `client/dist/pwa/index.html` exists.

Admin:

```sh
cd ../admin
pnpm install --frozen-lockfile
pnpm build
```

Expected: `vue-tsc` passes, Vite reports a built bundle, and `admin/dist/index.html` exists.

Start the stack and migrate:

```sh
cd ../deploy
docker compose up -d --build
docker compose ps
docker compose exec app php artisan migrate --force
```

Expected: `docker compose ps` lists `caddy`, `app`, `reverb`, `queue`, `scheduler`, `postgres` and `redis` as running,
with `app`, `reverb`, `postgres` and `redis` reporting healthy after about 30 seconds. `migrate --force` prints each
migration followed by `DONE`, or `Nothing to migrate.` on a repeat run. Caddy obtains the TLS certificate on the first
request; if `docker compose logs caddy` shows ACME errors, recheck DNS and ports 80 and 443.

## 5. First admin

There is no self-serve admin path. The person signs in to Psst once with Google (so an account exists), then:

```sh
docker compose exec app php artisan psst:make-admin <email>
```

Expected: `<email> is now an admin.` If it prints `No account with that email. Ask them to sign in to Psst once
first.`, they have not signed in yet.

Then open `https://<domain>/admin/`, sign in as that person and complete the two-factor (TOTP) setup: scan the QR
code with an authenticator app and enter the code it shows. The admin must set up two-factor on first admin sign-in.
If they later lose the device, after verifying who is asking:

```sh
docker compose exec app php artisan psst:reset-2fa <email>
```

## 6. Turnstile (guest-send challenge)

Guest sends are otherwise protected only by rate limits.

1. Cloudflare dashboard, Turnstile, add a widget for `<domain>`. Copy the site key and the secret key.
2. `TURNSTILE_SECRET=<secret>` in `deploy/.env`.
3. `QCLI_TURNSTILE_SITE_KEY=<site key>` in `client/.env`.
4. Rebuild the client (the key is inlined) and recreate the API containers so they read the secret:

```sh
cd client && pnpm build:pwa
cd ../deploy && docker compose up -d --force-recreate app reverb queue scheduler
```

Confirm a guest send without a token is rejected (use an existing username):

```sh
curl -sk -o /dev/null -w '%{http_code}\n' -X POST https://<domain>/api/profiles/<username>/messages \
  -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{"body":"hello"}'
```

Expected: `422` (the body says "We could not verify you are human"). With `TURNSTILE_SECRET` empty the same request
is accepted (`201`), which is how you know the challenge was off. Note that a successful run of this command with the
secret empty creates a real message.

## 7. VAPID keys (Web Push)

```sh
docker compose exec app php artisan psst:vapid
```

Expected: the line "Add these to the server environment..." followed by `VAPID_PUBLIC_KEY=...`,
`VAPID_PRIVATE_KEY=...` and `VAPID_SUBJECT=mailto:you@your-domain.example`. Put them in `deploy/.env` with
`VAPID_SUBJECT=mailto:<email>`, keep the private key secret, then recreate the containers:

```sh
docker compose up -d --force-recreate app reverb queue scheduler
```

Web Push stays off while `VAPID_PUBLIC_KEY` is empty. Generate the pair once and keep it; new keys invalidate existing
push subscriptions.

## 8. Smoke checks

```sh
curl -sk https://<domain>/up
curl -sk https://<domain>/api/push/config
curl -sk https://<domain>/.well-known/assetlinks.json
curl -skI https://<domain>/ | head -n 1
curl -skI https://<domain>/admin/ | head -n 1
curl -sk -A "facebookexternalhit/1.1" https://<domain>/<an-existing-username> | grep -E "og:title|robots"
```

Expected:

- `/up`: HTTP 200 with the Laravel health page.
- `/api/push/config`: `{"data":{"public_key":"<a long string>","fcm":false}}`. `"public_key":null` means VAPID is
  not configured (section 7).
- `/.well-known/assetlinks.json`: the JSON file served as `application/json`. Until you replace the fingerprint (see
  `deploy/wellknown/README.md`), app links simply open in the browser.
- `/` and `/admin/`: `HTTP/2 200`.
- The crawler request: the `og:title` and `noindex, nofollow` robots tags. The same URL with a normal browser
  user agent returns the app shell instead.

If the database was seeded or imported before launch, check that no user already holds a reserved name (they would have a dead profile): `docker compose exec app php artisan tinker --execute='dump(App\Models\User::whereIn("username", config("psst.reserved_usernames"))->pluck("username"));'` must print an empty collection.

Drop `-k` once the certificate is issued to make curl enforce TLS. Then open `https://<domain>/` in a browser, sign in
with Google, send yourself a message and confirm it arrives live (realtime goes through `/app` on port 443).

## 9. Restore drill

Do this once before launch and again after any change to the backup setup. A backup you have never restored is not a
backup.

```sh
cd deploy
docker compose exec app php artisan psst:backup
```

Expected: `Uploaded psst-YYYYMMDD-HHMMSS.sql.gz.enc`. `BACKUP_PASSPHRASE is not set; refusing to write an unencrypted
backup.` means section 2 is incomplete.

Download that file from the bucket to a host that has `psql`, `openssl` and `gunzip`. `scripts/restore-backup.sh` is a
bash script for such a host; it is **not** inside the app image. On that host create a scratch database (never the
live one) and restore:

```sh
createdb psst_restore_test
BACKUP_PASSPHRASE='<passphrase>' DB_DATABASE=psst_restore_test DB_USERNAME=<user> DB_PASSWORD=<password> \
  DB_HOST=<host> scripts/restore-backup.sh psst-YYYYMMDD-HHMMSS.sql.gz.enc
```

Expected: `restored psst-YYYYMMDD-HHMMSS.sql.gz.enc into psst_restore_test`. The script refuses a database that already
has tables unless you pass `--force`, which guards against restoring over live data. Spot-check and clean up:

```sh
psql -d psst_restore_test -c 'select count(*) from users;'
dropdb psst_restore_test
```

Expected: a plausible user count. A wrong passphrase fails at the `openssl` step and leaves the database untouched.

For a real disaster restore: stop `app`, `reverb`, `queue` and `scheduler`, restore into an empty database with the
script, start the services again, then run `php artisan migrate --force`.

## 10. Rollback

Every release is a tagged image: `docker-compose.yml` names it `psst-app:${APP_TAG}`. Set a new `APP_TAG` in
`deploy/.env` for each release (the date or git SHA) before `docker compose up -d --build`, so the previous image
stays on the host. To roll back:

```sh
cd deploy
docker image ls psst-app
# edit deploy/.env: APP_TAG=<previous tag>
docker compose up -d --no-build
```

Expected: `image ls` lists the earlier tag; `docker compose up -d --no-build` recreates `app`, `reverb`, `queue` and
`scheduler` on that image without rebuilding from the current checkout. Front-end rollbacks are separate: Caddy serves
`client/dist/pwa` and `admin/dist` from the checkout, so check out the previous commit and rebuild them (section 4)
if the bug is in the UI.

Migrations are written to be backward compatible, so the previous image can run against the already-migrated
database. Do not run `migrate:rollback` in production; if a schema change has to be undone, ship a new forward
migration. If data is damaged, restore from backup (section 9).

## 11. Error tracking and uptime

**Error tracking (Sentry, free tier).**

1. Create a free account at sentry.io, then a project with the platform set to Laravel. Copy its DSN.
2. Set `SENTRY_LARAVEL_DSN=<dsn>` in `deploy/.env` and run `docker compose up -d` so `app`, `queue` and `scheduler`
   pick it up. Leave it empty and nothing is sent.
3. Verify from the host: `docker compose exec app php artisan sentry:test`. Expected: the command prints that a test
   event was sent and it appears in the project's Issues list within a minute.

psst is anonymous, so every event goes through `App\Observability\ScrubEvent` before it leaves the server. Request
payloads, cookies, query strings, all headers except content type, the user and IP, `extra`, breadcrumb messages and
data, stack-frame variables and source lines are dropped. Database error messages are cut before the SQL and the
Postgres `DETAIL` line, where a bound message body would appear. `send_default_pii` is off and SQL bindings are not
recorded. Exception type, message, stack trace, route and release still arrive. Do not turn on Sentry session replay,
client SDKs or performance traces without extending the scrubber first.

**Uptime check.** Add an external monitor on any free uptime service (UptimeRobot, Better Stack, Uptime Kuma on
another host); the owner picks. Point an HTTPS check at `https://<domain>/up` every 1 to 5 minutes and send alerts to
the owner's email. Expected: `/up` returns 200 while the app is healthy; stop `app` briefly to confirm the alert fires.
