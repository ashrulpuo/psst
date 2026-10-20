# Psst

Anonymous messaging with two-way chat, NGL-style. Receivers sign in (Google / Apple) and get a public link; **anyone can
send without an account**. Signing in is for chatting: a sender who signs in can be replied to. Senders stay anonymous
**to the receiver**; admins can identify signed-in senders (disclose this in the Terms and Privacy Policy).

Client: [`client/README.md`](client/README.md). Admin: [`admin/README.md`](admin/README.md). Plan and design docs live in [`docs/`](docs/): `MOBILE.md`, `DESKTOP.md`, `PLAN.md`, `DATABASE.md`, `DESIGN.md`, `DEVELOPMENT.md`.

## Status

| Done | Not yet |
|---|---|
| **Server** (`server/`): schema, models, Google/Apple sign-in, username onboarding | Mobile apps compiled and run on real devices / real FCM, Google and Apple sheets (see `docs/MOBILE.md`) |
| Threads, messages, inbox, sent, read state, block/report, rate limits | Caddy config for the two SPAs has not been run (no Caddy here) |
| **Web client** (`client/`): sign-in, username, public link page, inbox/sent split pane, settings, dark mode | macOS/Windows desktop builds run on real machines, code signing, auto-update (see `docs/DESKTOP.md`) |
| **Realtime** (Reverb): live messages, read receipts, block state; polling only as fallback | Docker images (compose file written, never built) |
| **Web Push** (VAPID): per-device opt-in, queued delivery, installable PWA | |
| **Admin dashboard** (`admin/`): users, conversations with real identities, report triage, bans, audit log; mandatory TOTP 2FA | |
| Browser tests: client `pnpm e2e` (21), `e2e:realtime` (7), `e2e:push` (12), `e2e:native` (15), `e2e:account` (6), `e2e:guest` (9); admin `pnpm e2e` (16) | |
| **Desktop (Electron)**: hardened shell, native notifications + badge, offline page; `e2e:desktop` (12, Linux) | |
| **Anonymous sending with no login** (guest threads, sign-in unlocks chat, abuse controls, optional Turnstile) | Turnstile/abuse limits tuned on real traffic |
| **Account deletion**: typed confirmation, erases the user and all their conversations, opaque audit record; admins must be demoted first | |
| **Mobile (Capacitor)**: native sign-in/Keychain token, FCM push, deep links, safe areas, icons; `e2e:native` (15) | Real Google/Apple credentials (tests use a fake provider) |

## Run the API locally

Needs PHP 8.3 (`pdo_pgsql`), Composer and PostgreSQL 16.

```sh
# one-time database setup
sudo -u postgres psql -c "CREATE ROLE psst LOGIN PASSWORD 'psst' CREATEDB;" \
  -c "CREATE DATABASE psst OWNER psst;" -c "CREATE DATABASE psst_test OWNER psst;"

cd server
composer install
cp .env.example .env && php artisan key:generate
echo 'PSST_DEV_LOGIN=true' >> .env      # local-only sign-in shortcut, ignored in production
php artisan migrate:fresh --seed         # demo users: alice, bob, carol, admin
php artisan serve                        # API on http://127.0.0.1:8000
php artisan reverb:start                 # WebSocket server on :8080 (separate terminal)
php artisan queue:work                   # sends the broadcasts (separate terminal)
```

Realtime needs `BROADCAST_CONNECTION=reverb` and the `REVERB_*` keys (`php artisan reverb:install` generates them), and
the client needs the public key in `client/.env` as `QCLI_REVERB_KEY`. Without it the app still works by polling.
### Admin access

```sh
php artisan psst:make-admin you@example.com   # the account must have signed in once
php artisan psst:reset-2fa you@example.com    # support path if an admin loses their authenticator AND recovery codes
```

See [`admin/README.md`](admin/README.md) for the dashboard, the two-factor flow and what is audited.

### Push notifications

```sh
php artisan psst:vapid                   # prints VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY for server/.env
```

With no `VAPID_PUBLIC_KEY` push stays off and the app says so in Settings. Web Push needs the PHP `gmp` extension
(`apt install php8.3-gmp`; the Docker image has it). The client needs the PWA build for its service worker:
`pnpm dev:pwa` instead of `pnpm dev`. On iPhone/iPad the app must first be added to the Home Screen.
Message text in notifications is opt-in per user (lock screens are public); sender identity is never included.

Restart `queue:work` after `migrate:fresh`: dropping the `jobs` table under a running worker makes it exit.

### Tests

```sh
cd server && php artisan test            # 150 tests, run against the psst_test database
```

### Try it by hand

`/api/dev/login` exists only when `PSST_DEV_LOGIN=true` (never in production) and swaps a seeded email for a token.

```sh
B=http://127.0.0.1:8000/api; J='-H Accept:application/json -H Content-Type:application/json'
tok(){ curl -s $J -X POST $B/dev/login -d "{\"email\":\"$1@example.test\"}" | python3 -c "import sys,json;print(json.load(sys.stdin)['token'])"; }
ALICE=$(tok alice); BOB=$(tok bob)

curl -s $J $B/profiles/alice                                                        # public link page data
curl -s $J -H "Authorization: Bearer $BOB" -X POST $B/profiles/alice/messages -d '{"body":"hi!"}'
curl -s $J -H "Authorization: Bearer $ALICE" $B/inbox                               # sender shows as "Anonymous #1"
curl -s $J -H "Authorization: Bearer $ALICE" -X POST $B/threads/<thread-id>/messages -d '{"body":"hello"}'
curl -s $J -H "Authorization: Bearer $BOB" $B/sent                                  # bob sees the reply
```

## API

| Method & path | Auth | Purpose |
|---|---|---|
| `POST /api/auth/{google\|apple}` | – | Browser sign-in (cookie session; needs `/sanctum/csrf-cookie` first) |
| `POST /api/auth/{google\|apple}/token` | – | Native sign-in, returns a 30-day bearer token |
| `POST /api/auth/logout` | ✔ | Revoke token / end session |
| `GET /api/me` · `PATCH /api/me/username` | ✔ | Profile; claim a username once |
| `GET /api/profiles/{username}` | – | Public profile for the link page |
| `POST /api/profiles/{username}/messages` | ✔ | Send an anonymous message (opens the thread) |
| `GET /api/inbox` · `GET /api/sent` | ✔ | Threads I received / threads I started (cursor-paginated) |
| `GET·POST /api/threads/{id}/messages` | ✔ | History (newest first, cursor) · reply |
| `POST /api/threads/{id}/read` | ✔ | Mark the other side's messages read |
| `PUT·DELETE /api/threads/{id}/block` | ✔ receiver | Block / unblock the sender |
| `POST /api/messages/{id}/report` | ✔ | Report the other party's message |

Sign-in bodies: `{ "id_token": "...", "nonce": "<16+ chars>", "name"?: "...", "device_name": "..." (token endpoint) }`.

## Real Google / Apple sign-in

Set `GOOGLE_CLIENT_IDS` and `APPLE_CLIENT_IDS` (comma-separated OAuth client IDs for web, iOS, Android). The client
obtains an ID token with a random nonce embedded in the request and posts both to the API. Until the IDs are set,
every sign-in is rejected (fails closed).
