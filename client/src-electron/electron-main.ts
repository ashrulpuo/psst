import {
  BrowserWindow,
  Notification,
  app,
  ipcMain,
  Menu,
  shell,
  session,
  type Rectangle
} from "electron";
import fs from "node:fs";
import path from "node:path";
import os from "node:os";
import {
  registerQuasarRuntime,
  resolveElectronAssetsPath
} from "#q-app/electron/main";

// The desktop app is a hardened shell around the hosted web app: same code, same cookie session, always current.
// Override at runtime with PSST_APP_URL (self-hosters, local testing); baked in at build time otherwise.
const APP_URL = new URL(
  process.env.PSST_APP_URL ||
    (import.meta.env.QCLI_PUBLIC_URL as string | undefined) ||
    "https://psst.example.com"
);
// Hosts that may open as a sign-in popup (Google Identity Services / Sign in with Apple).
const SIGN_IN_HOSTS = new Set(["accounts.google.com", "appleid.apple.com"]);

const platform = process.platform || os.platform();
let mainWindow: BrowserWindow | null = null;

const ownOrigin = (url: string) => {
  try {
    return new URL(url).origin === APP_URL.origin;
  } catch {
    return false;
  }
};
/** In-app path only, never a full URL: what the renderer may ask the shell to navigate to. */
const safePath = (p: unknown): string | null =>
  typeof p === "string" && p.startsWith("/") && !p.startsWith("//") ? p : null;

// ---- remembered window size/position ----
const stateFile = () => path.join(app.getPath("userData"), "window-state.json");
function loadBounds(): Partial<Rectangle> {
  try {
    const b = JSON.parse(fs.readFileSync(stateFile(), "utf8")) as Rectangle;
    return [b.width, b.height].every(n => Number.isFinite(n) && n >= 320)
      ? b
      : {};
  } catch {
    return {};
  }
}
function saveBounds(win: BrowserWindow) {
  try {
    fs.writeFileSync(stateFile(), JSON.stringify(win.getNormalBounds()));
  } catch {
    // not worth surfacing
  }
}

const offlinePage = (detail: string) =>
  "data:text/html;charset=utf-8," +
  encodeURIComponent(`<!doctype html><meta charset="utf-8"><title>Psst</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px system-ui;background:#fafafa;color:#1b1a23}
@media(prefers-color-scheme:dark){body{background:#14131c;color:#ececf3}}
main{max-width:340px;text-align:center;display:grid;gap:12px}h1{font-size:20px;margin:0}p{margin:0;opacity:.7}
button{justify-self:center;min-height:44px;padding:0 20px;border:0;border-radius:10px;background:#5b4bf0;color:#fff;font:inherit;cursor:pointer}</style>
<main><h1>Can't reach Psst</h1><p>Check your connection and try again.</p><p style="font-size:13px">${detail.replace(/[<>&]/g, "")}</p>
<button onclick="location.href='${APP_URL.origin}'">Try again</button></main>`);

async function createWindow() {
  const win = new BrowserWindow({
    icon: resolveElectronAssetsPath("icons/icon.png"), // Windows and Linux
    width: 1100,
    height: 760,
    minWidth: 380,
    minHeight: 520,
    ...loadBounds(),
    useContentSize: true,
    backgroundColor: "#14131c",
    show: false,
    webPreferences: {
      contextIsolation: true,
      sandbox: true,
      nodeIntegration: false,
      webSecurity: true,
      spellcheck: true,
      preload: path.join(import.meta.dirname, "electron-preload.cjs")
    }
  });
  mainWindow = win;
  win.once("ready-to-show", () => win.show());
  win.on("close", () => saveBounds(win));
  win.on("closed", () => (mainWindow = null));

  const contents = win.webContents;

  // Links: our own pages stay in the app, sign-in popups are allowed, everything else opens in the real browser.
  contents.setWindowOpenHandler(({ url }) => {
    const target = new URL(url);
    if (target.protocol === "https:" && SIGN_IN_HOSTS.has(target.hostname))
      return { action: "allow" };
    if (ownOrigin(url)) {
      void contents.loadURL(url);
    } else if (target.protocol === "https:" || target.protocol === "mailto:") {
      void shell.openExternal(url);
    }
    return { action: "deny" };
  });
  contents.on("will-navigate", (event, url) => {
    if (ownOrigin(url) || url.startsWith("data:text/html")) return;
    event.preventDefault();
    if (url.startsWith("https://")) void shell.openExternal(url);
  });
  contents.on("did-fail-load", (_e, code, description, url, isMainFrame) => {
    // -3 is a navigation we cancelled ourselves.
    if (isMainFrame && code !== -3 && !url.startsWith("data:"))
      void contents.loadURL(offlinePage(description));
  });

  if (import.meta.env.QUASAR_DEV) {
    await win.loadURL(import.meta.env.QUASAR_APP_URL);
  } else {
    await win.loadURL(APP_URL.href);
  }

  if (import.meta.env.QUASAR_DEBUG) {
    contents.openDevTools();
  } else {
    contents.on("devtools-opened", () => contents.closeDevTools());
  }
}

function focusWindow() {
  if (!mainWindow) return void createWindow();
  if (mainWindow.isMinimized()) mainWindow.restore();
  mainWindow.show();
  mainWindow.focus();
}

// ---- renderer → shell bridge (see electron-preload.ts); every message is validated, the renderer is not trusted ----
function registerIpc() {
  const fromApp = (e: Electron.IpcMainEvent | Electron.IpcMainInvokeEvent) =>
    e.senderFrame !== null && ownOrigin(e.senderFrame.url);

  ipcMain.on("psst:badge", (e, count: unknown) => {
    if (!fromApp(e) || typeof count !== "number") return;
    app.setBadgeCount(Math.max(0, Math.min(9999, Math.trunc(count))));
  });

  ipcMain.on("psst:notify", (e, input: unknown) => {
    if (!fromApp(e) || !Notification.isSupported()) return;
    const {
      title,
      body,
      path: target
    } = (input ?? {}) as Record<string, unknown>;
    if (typeof title !== "string" || typeof body !== "string") return;
    const notification = new Notification({
      title: title.slice(0, 80),
      body: body.slice(0, 200),
      silent: false
    });
    const route = safePath(target);
    notification.on("click", () => {
      focusWindow();
      if (route) mainWindow?.webContents.send("psst:navigate", route);
    });
    notification.show();
  });
}

function lockDownSession() {
  // Nothing but notifications is ever needed; the sign-in popups use no device permissions.
  session.defaultSession.setPermissionRequestHandler(
    (_wc, permission, callback) => callback(permission === "notifications")
  );
  session.defaultSession.setPermissionCheckHandler(
    (_wc, permission) => permission === "notifications"
  );
}

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on("second-instance", focusWindow);

  void app.whenReady().then(() => {
    registerQuasarRuntime();
    lockDownSession();
    registerIpc();
    if (!import.meta.env.QUASAR_DEBUG) Menu.setApplicationMenu(null);
    void createWindow();

    app.on("activate", () => {
      if (BrowserWindow.getAllWindows().length === 0) void createWindow();
    });
  });

  app.on("window-all-closed", () => {
    if (platform !== "darwin") app.quit();
  });
}
