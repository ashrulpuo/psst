# Psst

Anonymous messaging with two-way chat. Receivers get a public link; senders sign in (Google / Apple) and stay
anonymous **to the receiver** — admins can identify senders (disclose this in the Terms and Privacy Policy).

Plan and design docs live in [`docs/`](docs/): `PLAN.md`, `DATABASE.md`, `DESIGN.md`, `DEVELOPMENT.md`.

## Status

| Done (backend, in `server/`) | Not yet |
|---|---|
| Schema + migrations, models | Realtime (Reverb) and push notifications |
| Google / Apple sign-in (ID-token verification) | Quasar web/mobile/desktop client |
| Username onboarding | Admin dashboard SPA and admin API |
| Threads, messages, inbox, sent, read state | Docker images (compose file written, never built) |
| Block / unblock, report, rate limits, spam cap | Real Google/Apple credentials (tests use a fake provider) |

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
php artisan serve                        # http://127.0.0.1:8000
```

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
