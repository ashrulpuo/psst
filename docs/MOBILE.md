# Mobile apps (iOS & Android)

The mobile apps are the **same Quasar code** wrapped by Capacitor 8 (`client/src-capacitor/`). Native-only code lives
in `client/src/native/` and `client/src/boot/native.ts`; web builds never include it (checked: no plugin code in
`dist/spa` or `dist/pwa`).

## What differs from the web app

| Concern | Web | Mobile app |
|---|---|---|
| Sign-in | Google / Apple JS buttons → cookie session | Native Google / Apple sheet → `POST /api/auth/{provider}/token` → 30-day bearer token |
| Token storage | httpOnly session cookie | iOS Keychain / Android Keystore (`@aparajita/capacitor-secure-storage`) |
| API | same origin, CSRF | `QCLI_API_URL`, CORS, `Authorization: Bearer`, no cookies, no CSRF |
| Realtime | Reverb, cookie-authorised | Reverb on the API host, bearer-authorised channels |
| Push | Web Push (VAPID) | FCM (Android directly, iOS via APNs) → `POST /api/push/devices` |
| Share link | `window.location.origin` | `QCLI_PUBLIC_URL` (an app's own origin is useless to others) |
| Links | – | `https://<domain>/alice` opens the app (App Links / Universal Links); only the public host is honoured |

Tapping a notification or link only ever routes inside the app (same-origin paths; `//host` and absolute URLs are refused).

## What has and hasn't been verified

Verified in this repo (CI-able, no device needed):
- `pnpm e2e:native`: 15 browser checks of the native code paths with the plugins replaced by in-page fakes, on a
  different origin than the API (like `capacitor://localhost`): CORS + bearer auth, token survives restart, realtime
  with the bearer authorizer, push permission → device registration → token rotation, deep links (own host only),
  notification-tap routing, Android back/exit, splash hide, sign-in failure, sign-out revokes the token.
- Backend: FCM sender (JWT signature, HTTP mapping to deliver/gone/retry/fail), device endpoints, CORS (181 tests).
- `quasar build -m capacitor -T android --skip-pkg` succeeds and `cap sync` registers all 7 plugins.

**Not verified (needs real toolchains/accounts, none available in the dev sandbox):**
Compiling the APK/IPA (no Android SDK / Xcode here), the real Google and Apple sheets, real FCM/APNs delivery, and
App Link / Universal Link verification. Expect to debug these on first device run.

## One-time setup

### 1. Choose your identifiers
App id `app.psst.mobile` (change in `capacitor.config.ts`, Android `build.gradle`/`strings`, iOS bundle id, `deploy/wellknown/*`).
Replace the placeholder host `psst.example.com` with your domain in:
`android/app/src/main/res/values/strings.xml` (`deep_link_host`), `ios/App/App/App.entitlements` (`applinks:`).

### 2. Client env (`client/.env`, baked in at build time)
```
QCLI_API_URL=https://psst.example.com          # where the API + /app (Reverb) live
QCLI_PUBLIC_URL=https://psst.example.com       # host used in shared links
QCLI_GOOGLE_CLIENT_ID=<web client id>          # also the audience for the token
QCLI_GOOGLE_IOS_CLIENT_ID=<ios client id>
QCLI_APPLE_CLIENT_ID=<services id / bundle id>
QCLI_REVERB_KEY=<same as server REVERB_APP_KEY>
```
`QCLI_API_URL` is also added to the page's CSP `connect-src` automatically.

### 3. Server env
- `GOOGLE_CLIENT_IDS` must list the **web and iOS** client IDs; `APPLE_CLIENT_IDS` the iOS bundle id.
- `CORS_ALLOWED_ORIGINS` defaults to `capacitor://localhost,https://localhost,http://localhost` (the app's WebView origins).
- Push: create a Firebase project, add the Android and iOS apps, then
  `FCM_PROJECT_ID=<id>` and `FCM_CREDENTIALS=<service-account JSON | base64 | file path>`.
  Upload your APNs auth key (.p8) to Firebase → Project settings → Cloud Messaging.
- Reverb: `REVERB_ALLOWED_ORIGINS` must include the app origins above if you restrict it.

### 4. Firebase / Apple native files (not committed)
- Android: `google-services.json` → `client/src-capacitor/android/app/`
- iOS: `GoogleService-Info.plist` → `client/src-capacitor/ios/App/App/` (add to the Xcode target); `AppDelegate`
  only calls `FirebaseApp.configure()` when the file exists.
- iOS capabilities already declared: Push Notifications, Sign in with Apple, Associated Domains. Replace the
  placeholder reversed client id in `Info.plist` with your iOS Google client id reversed.

### 5. Deep-link verification
Fill the placeholders in `deploy/wellknown/` (see its README) and redeploy Caddy.

## Build

```sh
cd client
pnpm install
pnpm build:android      # = quasar build -m capacitor -T android  (needs Android SDK + JDK 21)
pnpm build:ios          # needs macOS + Xcode + CocoaPods
# or open the native project: quasar dev -m capacitor -T android / npx cap open ios (inside src-capacitor)
```
Icons and splash are generated from `src-capacitor/assets/` with
`cd src-capacitor && npx @capacitor/assets generate --android --ios --assetPath assets`.

## Store checklist
- iOS: Sign in with Apple is offered next to Google (App Store rule 4.8) — done. Privacy "nutrition label": email, user content.
- Both stores require in-app **report/block** (done) and a privacy policy that discloses admins can identify senders.
- In-app account deletion (Settings → Delete account) satisfies Apple's requirement. Apple also expects the
  Sign in with Apple token to be revoked on deletion; that needs the user's authorization code/refresh token, which
  Psst doesn't store, so it is not done. Say so in the review notes if asked.
- Android: sign with a release key; put its SHA-256 in `assetlinks.json`.
