# Psst client

Quasar (Vue 3 + TypeScript) app. This milestone targets the **web** (SPA); the same code is meant to become the
Capacitor (iOS/Android) and Electron/Tauri (macOS/Windows) builds.

## Run it

Needs Node 22+ and pnpm. The API from `../server` must be running on `127.0.0.1:8000`.

```sh
pnpm install
cp .env.example .env          # then set QCLI_DEV_LOGIN=true for demo sign-in (see below)
pnpm dev                      # http://localhost:9000
```

The dev server proxies `/api` and `/sanctum` to the API, so the browser sees one origin and cookie sessions work
without CORS. In production Caddy does the same job.

### Sign-in while developing

With `QCLI_DEV_LOGIN=true` here **and** `PSST_DEV_LOGIN=true` in `server/.env`, the login page shows
"Local demo accounts" (Alice, Bob, Carol from `php artisan migrate:fresh --seed`). To use real providers set
`QCLI_GOOGLE_CLIENT_ID` / `QCLI_APPLE_CLIENT_ID` and the matching `GOOGLE_CLIENT_IDS` / `APPLE_CLIENT_IDS` on the
server. The browser obtains an ID token and posts it to the API, which verifies it.

Variables must start with `QCLI_` (Quasar v3 convention) and are public: never put secrets in them.

### Try the whole flow

1. Sign in as **Alice** in one browser window → `/inbox`.
2. In a private window open `http://localhost:9000/alice`, write a message, choose *Sign in to send* → **Bob**.
   (The draft survives the sign-in.)
3. Back in Alice's window the message appears as **Anonymous #1**; reply, block, report from there.

## Scripts

| Command | |
|---|---|
| `pnpm dev` | Dev server with HMR |
| `pnpm build` | Production SPA in `dist/spa` |
| `pnpm typecheck` | `vue-tsc` (strict, `exactOptionalPropertyTypes`) |
| `pnpm lint` / `pnpm lint:check` | oxfmt + oxlint |

## Structure

```
src/
  api/          fetch wrapper (CSRF, error normalisation), endpoint functions, response types
  stores/       Pinia: auth, messaging (lists, optimistic send, polling merge)
  features/     auth/ (Google, Apple, demo sign-in) · messaging/ (conversation pane)
  components/   AppAvatar, MessageBubble, MessageComposer, ThreadListItem, dialogs, EmptyState
  layouts/      AuthLayout (centred card) · AppLayout (left rail / bottom tabs)
  pages/        Login, Welcome (username), Profile (public link), Messages (split pane), Settings
  css/app.scss  design tokens: colours, spacing, type scale, elevation, dark mode
  i18n/         all user-facing copy
```

## Design rules (see `../docs/DESIGN.md`)

Each screen is one named pattern: *Form (auth)*, *Form* (public link), *Split Pane* (inbox/sent), *Settings*.
Single violet accent, 1px borders before shadows, 3 font weights, 44px hit targets, no page-level horizontal
overflow at 375px or 1280px, real dark mode, `prefers-reduced-motion` honoured.

## Known gaps

- Live updates use polling (6s in an open conversation, 15s for lists, paused while the tab is hidden) until
  Reverb lands.
- Web push, the Capacitor/desktop builds, i18n beyond English, and the marketing landing page are not built.
- Quasar's full stylesheet ships (~37 KB gzipped); trimming it is a later optimisation.
- Real Google/Apple buttons render only when client IDs are configured and have not been exercised against the
  live providers.
