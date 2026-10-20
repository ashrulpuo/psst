# Psst

Anonymous messaging with two-way chat. Receivers get a public link; senders sign in (Google / Apple) and stay
anonymous **to the receiver** — admins can identify senders (disclose this in the Terms and Privacy Policy).

Client instructions: [`client/README.md`](client/README.md). Plan and design docs live in [`docs/`](docs/): `PLAN.md`, `DATABASE.md`, `DESIGN.md`, `DEVELOPMENT.md`.

## Status

| Done | Not yet |
|---|---|
| **Server** (`server/`): schema, models, Google/Apple sign-in, username onboarding | Push notifications (Web Push / FCM) |
| Threads, messages, inbox, sent, read state, block/report, rate limits | Admin dashboard SPA and admin API |
| **Web client** (`client/`): sign-in, username, public link page, inbox/sent split pane, settings, dark mode | Capacitor (mobile) and desktop builds |
| **Realtime** (Reverb): live messages, read receipts, block state; polling only as fallback | Docker images (compose file written, never built) |
| Browser tests: `pnpm e2e` (21 checks incl. 375px/1280px overflow), `pnpm e2e:realtime` (7 checks) | |
| | Real Google/Apple credentials (tests use a fake provider) |

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
Restart `queue:work` after `migrate:fresh`: dropping the `jobs` table under a running worker makes it exit.

### Tests

```sh
cd server && php artisan test            # 64 tests, run against the psst_test database
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
