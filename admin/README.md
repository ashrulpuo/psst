# Psst admin

Custom admin dashboard: Vue 3 + TypeScript + Tailwind v4, with [reka-ui](https://reka-ui.com) primitives (the same
layer shadcn-vue is built on) and hand-written components. No Filament. It talks to `/api/admin/*` on the Laravel API.

## What it does

| Screen | Pattern | Purpose |
|---|---|---|
| Dashboard | Dashboard | Open reports (the hero figure), users, activity, two 30-day charts (hover, keyboard, table view) |
| Users / user | List / Detail | Search, filter, open a profile with activity numbers, **ban** (named confirmation) and lift |
| Conversations / one | List / Detail | Both real identities (receiver and sender), the full message log |
| Reports | Split Pane | Triage queue, the conversation around the reported message, dismiss / review / **review and ban**, auto-advance |
| Audit log | List | Append-only record of identity views, bans and decisions, filterable |

## Access model

1. **Sign in** with the same SSO as everyone else. The account must already have the admin role:
   `php artisan psst:make-admin someone@example.com` (they sign in to Psst once first).
2. **Two-factor is mandatory.** First admin sign-in enrols an authenticator app (TOTP, RFC 6238) and shows 8
   one-time recovery codes once. Every new session asks for a code (or a recovery code); it is re-asked after 12 hours.
   Codes can't be replayed and attempts are limited to 5 a minute. Lost authenticator and codes:
   `php artisan psst:reset-2fa admin@example.com`, so a hijacked session can never swap in its own authenticator.
3. **Cookie session only.** The admin API refuses bearer tokens, because the second-factor proof lives in the session.
4. **Everything sensitive is audited**: opening a profile, a conversation or its messages, a report, every ban or
   decision, 2FA enrolment and failures. The table is append-only at the database level.

## Run it

```sh
pnpm install
cp .env.example .env        # VITE_DEV_LOGIN=true shows demo sign-in; the API needs PSST_DEV_LOGIN=true too
pnpm dev                    # http://localhost:9100  (proxies /api to 127.0.0.1:8000, so cookies just work)
```

`server/.env` needs `SANCTUM_STATEFUL_DOMAINS` to include `localhost:9100`. Demo admin: `admin@example.test`
(`php artisan migrate:fresh --seed`); you will be asked to set up two-factor on first sign-in.

## Scripts

| Command | |
|---|---|
| `pnpm dev` / `pnpm build` | Dev server / production build (served under `/admin/`) |
| `pnpm typecheck` | `vue-tsc` (strict, `exactOptionalPropertyTypes`) |
| `pnpm lint` / `pnpm lint:check` | oxfmt + oxlint |
| `pnpm e2e` | Playwright test: 2FA enrolment with real TOTP codes, every screen, ban and report flows, audit trail, dark mode, 375px layout |

## Production

Built with base `/admin/` and served by Caddy on the **same origin** as the API (see `deploy/Caddyfile`), with its
own CSP (`index.html`), `no-store` on the HTML shell and immutable caching on hashed assets. Restrict it further by
IP in the Caddyfile (a commented example is there). Search uses `ILIKE`; add `pg_trgm` indexes when the user table grows.
