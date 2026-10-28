# Release readiness

What actually builds, what is verified, what is still missing before this ships to real people.
Written 2026-10-28 against commit `a81a7bd`. Re-verify with the commands at the bottom before trusting it.

**Read the "Blocked on you" table first — two of those items stop a launch outright.**

## Target status

| Target | Builds today? | Verified how | Left before shipping | Owner |
|---|---|---|---|---|
| **Web SPA** | Yes | `pnpm build` | Production env (below), TLS domain, OAuth IDs | You + me |
| **Web PWA** | Yes | `pnpm build:pwa`, `e2e:push` 12/12 | VAPID keys, `PUSH_EXTRA_ALLOWED_HOSTS` | You |
| **Server / API** | Yes | `php artisan test` 230 passed, `deploy/docker-compose.yml` validates in CI | Real `.env`, migrations `--force`, backups | You |
| **Admin SPA** | Yes | `pnpm typecheck`, `pnpm build` | Its own admin credentials | You |
| **Android** | **Yes** (bundle) | `pnpm build:android --skip-pkg` succeeds | Release keystore, `ANDROID_HOME`, `assetlinks.json` fingerprint, Play listing | You |
| **iOS** | **No** | — | macOS with Xcode + CocoaPods, Apple Developer account, provisioning, App Store review notes | You |
| **macOS desktop** | **Yes** (bundle) | `pnpm build:desktop --skip-pkg` succeeds | Electron binary (`pnpm approve-builds`), Developer ID signing + notarization | You |
| **Windows desktop** | **Yes** (bundle) | same build | Build on Windows, EV/OV code-signing certificate | You |

"Bundle" means the app code compiles for that target. Producing an installable artefact additionally needs the
platform SDK and signing material listed in the last column.

## Blocked on you

| # | Item | Why it blocks | Where |
|---|---|---|---|
| 1 | **OAuth client IDs** | The API **fails closed**: with `GOOGLE_CLIENT_IDS` / `APPLE_CLIENT_IDS` empty, every sign-in is rejected. Nobody can log in. | `deploy/.env`, `client/.env` — see README §Real Google / Apple sign-in |
| 2 | **Apple sign-in** | Needs a Services ID, an Apple key, and an **https** return URL. Cannot be tested on `localhost`. | `APPLE_CLIENT_IDS`, `QCLI_APPLE_CLIENT_ID` |
| 3 | **Android release keystore** | No signed `.aab` without it; Play App Signing fingerprint also goes into `deploy/wellknown/assetlinks.json` | `./gradlew signingReport` |
| 4 | **Apple Team ID** | Universal Links stay off until `deploy/wellknown/apple-app-site-association` has the real Team ID | developer.apple.com → Membership |
| 5 | **Turnstile decision** | Guest sends are rate-limited only; the challenge is off (`TURNSTILE_SECRET` empty). Decide before you invite strangers. | `server/.env` |
| 6 | **VAPID keys** | Web Push does nothing while `VAPID_PUBLIC_KEY` is empty. Generate with `php artisan psst:vapid`. | `server/.env` |
| 7 | **Desktop/mobile signing** | Unsigned macOS/Windows apps trigger Gatekeeper / SmartScreen. No auto-update exists at all. | `quasar.config.ts → electron.packager` |

## Production environment checklist

`deploy/.env.example` already ships production-shaped values (unlike `server/.env.example`, which is a **dev** file:
`APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000` — never copy it to a server unedited).

Fatal if wrong:

- [ ] `APP_KEY=` — generate with `php artisan key:generate --show`
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`
- [ ] `DB_PASSWORD`, `REDIS_PASSWORD` — no `change-me`
- [ ] `GOOGLE_CLIENT_IDS`, `APPLE_CLIENT_IDS` — otherwise nobody signs in
- [ ] `CORS_ALLOWED_ORIGINS=capacitor://localhost,https://localhost,https://<domain>` — native builds need the first two
- [ ] `SANCTUM_STATEFUL_DOMAINS=<domain>` — cookies only work for listed hosts
- [ ] `REVERB_APP_ID` / `KEY` / `SECRET`, `REVERB_ALLOWED_ORIGINS=<domain>` — realtime
- [ ] `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `BROADCAST_CONNECTION=reverb`

Before inviting users:

- [ ] `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT`
- [ ] `TURNSTILE_SECRET` + `QCLI_TURNSTILE_SITE_KEY`, or a deliberate decision to run without it
- [ ] `PSST_DEV_LOGIN=false` — otherwise demo accounts are a login bypass
- [ ] `LOG_CHANNEL=stderr`, `LOG_LEVEL=info`
- [ ] `PUSH_EXTRA_ALLOWED_HOSTS` — only for local push testing; keep empty in production
- [ ] Fill `deploy/wellknown/assetlinks.json` and `apple-app-site-association` (see its README)
- [ ] Decide admin provisioning: there is no self-serve path — the first admin is granted with
      `php artisan psst:make-admin <email>` and must set up two-factor on first admin sign-in
      (`psst:reset-2fa` exists if they lose the device)

## Never verified anywhere

- macOS and Windows **binaries** (only the Electron bundle is proven); code signing; notarization; auto-update.
- iOS build end-to-end (no CocoaPods/Apple account here).
- Android **packaging** (no `ANDROID_HOME` here) — only the bundle.
- Apple token revocation on account deletion: Psst never stores the authorization code, so the Sign in with Apple token
  is not revoked. Mention it in App Store review notes if asked.
- `e2e:desktop` (needs `xvfb` on Linux) and `e2e:native` (needs a device) have not been run in this environment.
- **Dark mode has never been rendered** since the canvas-gradient change. The tokens exist and the build passes, but no
  one has looked at it. Treat it as unverified UI.

## Ship order

1. **Web + PWA only** (fastest path to something real)
   - fill `deploy/.env`, build `client` (`pnpm build:pwa`) and `admin` (`pnpm build`), `docker compose up -d`
   - `php artisan migrate --force`, then confirm `caddy` serves TLS
   - run the browser suites against the deployed origin (`BASE_URL=https://… pnpm e2e`)
2. **Android** — keystore, `ANDROID_HOME`, `pnpm build:android`, upload the `.aab`, put the Play fingerprint in
   `assetlinks.json`, redeploy Caddy
3. **Desktop** — `pnpm approve-builds` (Electron binary), sign, build per OS (or the *Desktop builds* workflow)
4. **iOS last** — needs the Mac setup, Apple account and review notes above

## Reproduce the checks

```sh
# client gates (all green as of a81a7bd)
cd client && pnpm typecheck && pnpm lint:check && pnpm build
pnpm build:android --skip-pkg      # capacitor bundle
pnpm build:desktop --skip-pkg      # electron bundle

# server
cd server && php artisan test && vendor/bin/pint --test

# browser suites — API + Reverb + worker + dev server, fresh seed each time
php artisan migrate:fresh --seed          # then restart queue:work --sleep=1
cd client && pnpm e2e                     # 23 checks
pnpm e2e:guest && pnpm e2e:account && pnpm e2e:realtime
pnpm e2e:two-people && pnpm e2e:push      # push needs pnpm dev:pwa + VAPID keys
```

`scripts/dev-stack.sh --seed` starts the whole local stack in the right order (the worker restarts itself, because
`migrate:fresh` drops `jobs` and silently kills it).
