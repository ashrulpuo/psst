# Desktop apps (macOS, Windows, Linux)

Electron, via Quasar's `electron` mode (`client/src-electron/`). Tauri was considered; Electron wins here because the
shell must reuse the web app's Google/Apple sign-in popups, and Chromium behaves identically on all three systems.

## How it works

The desktop app is a **hardened shell around your hosted web app** (`QCLI_PUBLIC_URL`, or `PSST_APP_URL` at runtime).
Same code, same cookie session, always the version you deployed — nothing to update on users' machines for UI changes.

The shell adds what a browser tab can't:
- **Native notifications** and a **dock/taskbar badge** for unread messages. Browsers get Web Push; Electron has no
  browser push service, so the live Reverb socket drives notifications instead (works while the app is open or in
  the tray/background, not when it is quit). Text is shown only if the user turned on *message previews*.
- Clicking a notification focuses the window and opens that conversation.
- Remembered window size, single-instance lock, friendly offline page with *Try again*.

Security posture (`src-electron/electron-main.ts`): `contextIsolation`, `sandbox`, no Node in the page;
the page gets a 4-function bridge only (`setBadge`, `notify`, `onNavigate`, `platform`) and every call is validated
in the main process and only accepted from the app's own origin. Navigation away from the app opens the system
browser (https only); only Google/Apple sign-in hosts may open as popups; all device permissions except
notifications are denied; DevTools are closed in release builds.

## Build

```sh
cd client
QCLI_PUBLIC_URL=https://psst.example.com pnpm build:desktop     # packages for the OS you run it on
```
Output: `dist/electron/Packaged/`. Build each OS on that OS: the **Desktop builds** GitHub workflow does all three
(Actions → Desktop builds → Run workflow, enter your server URL).

Develop: `QCLI_DEV_LOGIN=true npx quasar dev -m electron` (loads the dev server).

## Before you ship it
- **Sign:** unsigned apps trigger Gatekeeper / SmartScreen warnings. macOS: set `osxSign`/`osxNotarize` in
  `quasar.config.ts → electron.packager` (Apple Developer ID). Windows: sign with an EV/OV certificate.
- **Google sign-in** works because the app loads your real https origin (already registered with Google). No extra
  OAuth client is needed.
- **Auto-update** is not included (add `electron-updater` with the builder bundler if you want it).
- Replace `psst.example.com` default (`QCLI_PUBLIC_URL`) — the app refuses to be useful without a real server.

## Verification status
`pnpm e2e:desktop` (`xvfb-run`, 12 checks) launches the real Electron build on Linux against a local gateway:
bridge surface and no Node leakage, sign-in, native notification (no text leak), badge, click-to-open, no
notification while focused, external-link and navigation lockdown, permissions, window-state persistence, offline page.
**Not verified:** macOS and Windows builds (no such machines here), tray/dock badge rendering (the call is checked,
not the pixels), code signing, and auto-update.
