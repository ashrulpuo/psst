// Desktop shell check: launches the real Electron build under a display (xvfb-run) against a local gateway that
// serves the web build and proxies /api and the WebSocket on ONE origin, like Caddy.
//
// Needs: API :8000 + Reverb :8080 + queue worker, freshly seeded DB, SANCTUM_STATEFUL_DOMAINS including
// localhost:9400, `QCLI_DEV_LOGIN=true pnpm build` and `pnpm build:desktop` done.
// Run: xvfb-run -a pnpm e2e:desktop
import { _electron as electron } from "playwright-core";
import http from "node:http";
import net from "node:net";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

const ROOT = new URL("..", import.meta.url).pathname;
const SPA = path.join(ROOT, "dist/spa");
const MAIN = path.join(ROOT, "dist/electron/UnPackaged/electron-main.js");
const EXE = path.join(ROOT, "src-electron/node_modules/electron/dist/electron");
const ORIGIN = "http://localhost:9400";
const API = "http://127.0.0.1:8000";

const types = {
  ".js": "text/javascript",
  ".css": "text/css",
  ".html": "text/html",
  ".svg": "image/svg+xml",
  ".json": "application/json",
  ".woff2": "font/woff2",
  ".png": "image/png"
};
const forward = (req, res, port) => {
  const up = http.request(
    {
      host: "127.0.0.1",
      port,
      path: req.url,
      method: req.method,
      headers: { ...req.headers, host: `127.0.0.1:${port}` }
    },
    r => {
      res.writeHead(r.statusCode, r.headers);
      r.pipe(res);
    }
  );
  up.on("error", () => res.writeHead(502).end());
  req.pipe(up);
};
const server = http.createServer((req, res) => {
  const url = new URL(req.url, ORIGIN);
  if (url.pathname.startsWith("/api/") || url.pathname.startsWith("/sanctum/"))
    return forward(req, res, 8000);
  let file = path.join(SPA, path.normalize(url.pathname));
  if (
    !file.startsWith(SPA) ||
    !fs.existsSync(file) ||
    fs.statSync(file).isDirectory()
  )
    file = path.join(SPA, "index.html");
  res.setHeader(
    "Content-Type",
    types[path.extname(file)] ?? "application/octet-stream"
  );
  fs.createReadStream(file).pipe(res);
});
server.on("upgrade", (req, socket, head) => {
  const up = net.connect(8080, "127.0.0.1", () => {
    up.write(
      `${req.method} ${req.url} HTTP/1.1\r\n` +
        Object.entries({ ...req.headers, host: "127.0.0.1:8080" })
          .map(([k, v]) => `${k}: ${String(v)}`)
          .join("\r\n") +
        "\r\n\r\n"
    );
    up.write(head);
    socket.pipe(up);
    up.pipe(socket);
  });
  up.on("error", () => socket.destroy());
  socket.on("error", () => up.destroy());
});
await new Promise(r => server.listen(9400, "127.0.0.1", r));

let failed = 0;
const step = async (name, fn) => {
  const started = Date.now();
  try {
    await fn();
    console.log(`PASS  ${name}  (${Date.now() - started}ms)`);
  } catch (e) {
    failed++;
    console.log(
      `FAIL  ${name}  — ${String(e instanceof Error ? e.message : e).split("\n")[0]}`
    );
  }
};
const assert = (c, m) => {
  if (!c) throw new Error(m);
};
const until = async (p, ms = 8000, label = "condition") => {
  const end = Date.now() + ms;
  while (Date.now() < end) {
    if (await p()) return;
    await new Promise(r => setTimeout(r, 150));
  }
  throw new Error(`timed out waiting for ${label}`);
};

const launch = (appUrl, userData) =>
  electron.launch({
    executablePath: EXE,
    args: ["--no-sandbox", MAIN, `--user-data-dir=${userData}`],
    env: { ...process.env, PSST_APP_URL: appUrl }
  });

// Record what the shell does instead of really showing notifications / opening a browser.
const instrument = app =>
  app.evaluate(({ app: a, Notification, shell }) => {
    globalThis.__rec = { badges: [], notes: [], external: [] };
    a.setBadgeCount = n => (globalThis.__rec.badges.push(n), true);
    Notification.isSupported = () => true;
    Notification.prototype.show = function () {
      globalThis.__rec.notes.push(this);
    };
    shell.openExternal = async url => void globalThis.__rec.external.push(url);
  });
const rec = (app, key) =>
  app.evaluate(
    (_e, k) =>
      globalThis.__rec[k].map(x =>
        typeof x === "object" && x ? { title: x.title, body: x.body } : x
      ),
    key
  );

const userData = fs.mkdtempSync(path.join(os.tmpdir(), "psst-desktop-"));
const bob = await (
  await fetch(`${API}/api/dev/login`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Origin: "http://localhost:9999"
    },
    body: JSON.stringify({ email: "bob@example.test" })
  })
).json();
assert(bob.token, "no token for Bob");
const send = body =>
  fetch(`${API}/api/profiles/alice/messages`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${bob.token}`
    },
    body: JSON.stringify({ body })
  });

const app = await launch(ORIGIN, userData);
await instrument(app);
const page = await app.firstWindow();
const errors = [];
page.on("pageerror", e => errors.push(String(e)));
await page.waitForLoadState("domcontentloaded");

await step(
  "loads the hosted web app and exposes only the small desktop bridge",
  async () => {
    await page.waitForSelector("html.desktop-app", { timeout: 10000 });
    const surface = await page.evaluate(() => ({
      bridge: Object.keys(window.psstDesktop).sort().join(),
      node: [typeof require, typeof process, typeof Buffer].join()
    }));
    assert(
      surface.bridge === "notify,onNavigate,platform,setBadge",
      `bridge: ${surface.bridge}`
    );
    assert(
      surface.node === "undefined,undefined,undefined",
      `node leaked into the page: ${surface.node}`
    );
  }
);

await step(
  "sign in (cookie session, same origin) reaches the inbox",
  async () => {
    await page.getByRole("button", { name: "Alice" }).click();
    await page.waitForURL("**/inbox", { timeout: 8000 });
  }
);

await step(
  "unfocused: a new message raises a native notification without leaking the text",
  async () => {
    await page.waitForTimeout(1500);
    await app.evaluate(({ BrowserWindow }) =>
      BrowserWindow.getAllWindows()[0].blur()
    );
    await page.evaluate(() => {
      document.hasFocus = () => false;
    });
    const r = await send("Secret text that must not appear");
    assert(r.status < 300, `send ${r.status}`);
    await until(
      async () => (await rec(app, "notes")).length === 1,
      6000,
      "notification"
    );
    const [n] = await rec(app, "notes");
    assert(n.title === "New anonymous message", `title ${n.title}`);
    assert(
      !n.body.includes("Secret"),
      "message text leaked into the notification"
    );
  }
);

await step("dock/taskbar badge follows unread count", async () => {
  await until(
    async () => (await rec(app, "badges")).includes(1),
    5000,
    "badge 1"
  );
});

await step(
  "clicking the notification focuses the app and opens that conversation",
  async () => {
    await app.evaluate(() => globalThis.__rec.notes[0].emit("click"));
    await page.waitForURL(/\/inbox\/[0-9a-f-]{36}/, { timeout: 5000 });
    await page
      .getByText("Secret text that must not appear")
      .first()
      .waitFor({ timeout: 5000 });
    await until(
      async () => (await rec(app, "badges")).at(-1) === 0,
      5000,
      "badge cleared after reading"
    );
  }
);

await step(
  "focused window: no notification for a message already on screen",
  async () => {
    await page.evaluate(() => {
      document.hasFocus = () => true;
    });
    await send("Second message, window focused");
    await page
      .getByText("Second message, window focused")
      .first()
      .waitFor({ timeout: 5000 });
    assert((await rec(app, "notes")).length === 1, "notified while focused");
  }
);

await step(
  "external links open in the real browser, never inside the app",
  async () => {
    await page.evaluate(() => window.open("https://example.org/page"));
    await until(
      async () =>
        (await rec(app, "external")).includes("https://example.org/page"),
      3000,
      "openExternal"
    );
    await page.evaluate(() => window.open("javascript:alert(1)"));
    await page.evaluate(() => window.open("file:///etc/passwd"));
    await page.waitForTimeout(300);
    const ext = await rec(app, "external");
    assert(ext.length === 1, `unexpected external opens: ${ext}`);
    assert(app.windows().length === 1, "a popup window was created");
  }
);

await step("top-level navigation away from the app is blocked", async () => {
  await page.evaluate(() => {
    location.href = "https://evil.example.org/";
  });
  await page.waitForTimeout(800);
  assert(new URL(page.url()).origin === ORIGIN, `navigated to ${page.url()}`);
  assert(
    (await rec(app, "external")).includes("https://evil.example.org/"),
    "not handed to the browser"
  );
});

await step("only the notifications permission is granted", async () => {
  const res = await page.evaluate(async () => {
    const out = {};
    for (const name of ["geolocation", "camera", "microphone"]) {
      try {
        out[name] = (await navigator.permissions.query({ name })).state;
      } catch {
        out[name] = "error";
      }
    }
    return out;
  });
  assert(!Object.values(res).includes("granted"), JSON.stringify(res));
});

await step("no page errors", async () =>
  assert(errors.length === 0, errors.slice(0, 2).join(" | "))
);

await app.close();

await step("window size is remembered", async () => {
  const f = path.join(userData, "window-state.json");
  assert(fs.existsSync(f), "window-state.json missing");
  assert(JSON.parse(fs.readFileSync(f, "utf8")).width >= 320, "bad bounds");
});

// A second app instance pointed at a dead port shows the friendly offline page, not a blank window.
const offline = await launch(
  "http://127.0.0.1:9",
  fs.mkdtempSync(path.join(os.tmpdir(), "psst-desktop-"))
);
const offlinePage = await offline.firstWindow();
await step(
  "unreachable server shows an offline page with Try again",
  async () => {
    await offlinePage
      .getByRole("button", { name: "Try again" })
      .waitFor({ timeout: 10000 });
    assert(
      await offlinePage.getByText("Can't reach Psst").isVisible(),
      "no message"
    );
  }
);
await offline.close();

server.close();
console.log(failed ? `\n${failed} FAILED` : "\nAll desktop checks passed");
process.exit(failed ? 1 : 0);
