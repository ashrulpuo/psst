// Native-app logic check. Real device builds can't run here, so this drives the same app code in Chromium with
// the native plugins replaced by in-page fakes (QCLI_NATIVE_SIMULATOR=true) on a *different origin* than the API,
// exactly like capacitor://localhost: bearer-token auth, CORS, token persistence, push-device registration,
// deep links, notification taps, back button and splash.
//
// Needs: API :8000 (+ CORS_ALLOWED_ORIGINS=http://localhost:9200, FCM_PROJECT_ID/FCM_CREDENTIALS set), Reverb :8080,
// a queue worker, a freshly seeded database, and the simulator build copied to $NATIVE_DIST:
//   QCLI_NATIVE_SIMULATOR=true QCLI_API_URL=http://localhost:9300 QCLI_PUBLIC_URL=https://psst.example.com \
//   QCLI_DEV_LOGIN=true pnpm build && cp -r dist/spa /tmp/psst-native
// Run: pnpm e2e:native
import { chromium } from "playwright-core";
import http from "node:http";
import net from "node:net";
import fs from "node:fs";
import path from "node:path";

const DIST = process.env.NATIVE_DIST ?? "/tmp/psst-native";
const APP = "http://localhost:9200";
const GATEWAY = "http://localhost:9300";
const API = "http://127.0.0.1:8000";
const REVERB = { host: "127.0.0.1", port: 8080 };

// ---- static app server (SPA fallback) ----
const types = {
  ".js": "text/javascript",
  ".css": "text/css",
  ".html": "text/html",
  ".svg": "image/svg+xml",
  ".json": "application/json",
  ".woff2": "font/woff2"
};
const appServer = http.createServer((req, res) => {
  const url = new URL(req.url, APP);
  let file = path.join(DIST, path.normalize(url.pathname));
  if (
    !file.startsWith(DIST) ||
    !fs.existsSync(file) ||
    fs.statSync(file).isDirectory()
  )
    file = path.join(DIST, "index.html");
  res.setHeader(
    "Content-Type",
    types[path.extname(file)] ?? "application/octet-stream"
  );
  fs.createReadStream(file).pipe(res);
});

// ---- gateway: one host for /api and the WebSocket, like Caddy in production ----
const proxy = (req, res, target) => {
  const up = http.request(
    {
      ...target,
      path: req.url,
      method: req.method,
      headers: { ...req.headers, host: `${target.host}:${target.port}` }
    },
    r => {
      res.writeHead(r.statusCode, r.headers);
      r.pipe(res);
    }
  );
  up.on("error", () => res.writeHead(502).end());
  req.pipe(up);
};
const gateway = http.createServer((req, res) =>
  proxy(req, res, { host: "127.0.0.1", port: 8000 })
);
gateway.on("upgrade", (req, socket, head) => {
  const up = net.connect(REVERB.port, REVERB.host, () => {
    up.write(
      `${req.method} ${req.url} HTTP/1.1\r\n` +
        Object.entries({
          ...req.headers,
          host: `${REVERB.host}:${REVERB.port}`
        })
          .map(([k, v]) => `${k}: ${String(v)}`)
          .join("\r\n") +
        "\r\n\r\n"
    );
    up.write(head);
    socket.pipe(up).pipe(up && socket);
  });
  up.on("error", () => socket.destroy());
  socket.on("error", () => up.destroy());
});
await Promise.all([
  new Promise(r => appServer.listen(9200, "127.0.0.1", r)),
  new Promise(r => gateway.listen(9300, "127.0.0.1", r))
]);

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
const native = page => page.evaluate(() => window.__psstNative.calls.slice());

// Bob is a plain API client (token), writing to Alice from "another device".
const bobLogin = await (
  await fetch(`${API}/api/dev/login`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Origin: APP
    },
    body: JSON.stringify({ email: "bob@example.test" })
  })
).json();
assert(
  bobLogin.token,
  "dev login did not return a token for a non-stateful origin"
);
const bobSend = body =>
  fetch(`${API}/api/profiles/alice/messages`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${bobLogin.token}`
    },
    body: JSON.stringify({ body })
  });

const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}
);
const ctx = await browser.newContext({
  viewport: { width: 390, height: 844 },
  isMobile: true,
  hasTouch: true
});
const page = await ctx.newPage();
const errors = [];
page.on("pageerror", e => errors.push(String(e)));
page.on(
  "console",
  m =>
    m.type() === "error" &&
    !/Failed to load resource/.test(m.text()) &&
    errors.push(m.text())
);
const requests = [];
page.on("request", r => r.url().startsWith(GATEWAY) && requests.push(r));

await page.goto(`${APP}/login`);

await step(
  "native shell boots: class, status bar, splash hidden after first screen",
  async () => {
    await page.waitForSelector("html.native-app");
    await until(
      async () => (await native(page)).includes("splash:hide"),
      5000,
      "splash:hide"
    );
    assert(
      (await native(page)).some(c => c.startsWith("statusBar:")),
      "status bar not synced"
    );
  }
);

await step(
  "login page shows native buttons (Google; Apple only on iOS) and no web SDK buttons",
  async () => {
    await page
      .getByRole("button", { name: /google/i })
      .first()
      .waitFor({ timeout: 4000 });
    assert(
      (await page.getByRole("button", { name: /apple/i }).count()) === 0,
      "Apple offered on a non-iOS platform"
    );
  }
);

await step(
  "sign-in failure (unverifiable ID token) shows an error and stays usable",
  async () => {
    await page
      .getByRole("button", { name: /google/i })
      .first()
      .click();
    await page.getByRole("alert").waitFor({ timeout: 5000 });
    assert(
      await page
        .getByRole("button", { name: /google/i })
        .first()
        .isEnabled(),
      "button stuck disabled"
    );
    assert(
      new URL(page.url()).pathname === "/login",
      "navigated away after failure"
    );
  }
);

await step(
  "dev sign-in over the cross-origin API yields a bearer token (no cookies, no CSRF call)",
  async () => {
    await page.getByRole("button", { name: "Alice" }).click();
    await page.waitForURL("**/inbox", { timeout: 8000 });
    assert(
      !requests.some(r => r.url().includes("/sanctum/csrf-cookie")),
      "CSRF cookie requested in native mode"
    );
    await until(
      () => requests.some(r => /\/api\/(inbox|sent)/.test(r.url())),
      5000,
      "first authed request"
    );
    const headers = await Promise.all(
      requests
        .filter(r => /\/api\/(inbox|sent)/.test(r.url()))
        .map(r => r.allHeaders())
    );
    assert(
      headers.length && headers[0].authorization?.startsWith("Bearer "),
      `inbox requests lacked the bearer header: ${JSON.stringify(headers.map(h => h.authorization))}`
    );
    assert((await ctx.cookies()).length === 0, "cookies were set");
    assert(
      await page.evaluate(() => localStorage.getItem("sim.api-token")),
      "token not saved to the (simulated) keychain"
    );
  }
);

await step(
  "session survives an app restart (token restored from secure storage)",
  async () => {
    await page.reload();
    await page.waitForURL("**/inbox", { timeout: 8000 });
    await page
      .getByRole("navigation", { name: /main/i })
      .first()
      .waitFor({ timeout: 5000 });
  }
);

await step(
  "realtime over the bearer-authorised socket: message arrives in <5s (polling is 30s)",
  async () => {
    await page.goto(`${APP}/inbox`);
    await page.waitForTimeout(1500); // socket + channel auth
    const r = await bobSend("Hello from another device");
    assert(r.status < 300, `send failed: ${r.status}`);
    // Polling would take 15-30s; the queue worker's 1s sleep is the floor here.
    await page
      .getByText("Hello from another device")
      .first()
      .waitFor({ timeout: 5000 });
  }
);

await step(
  "settings: enabling notifications asks the OS, then registers the FCM token with the API",
  async () => {
    await page.goto(`${APP}/settings`);
    await page.getByRole("button", { name: "Turn on notifications" }).click();
    await page
      .getByRole("button", { name: "Turn off notifications" })
      .waitFor({ timeout: 5000 });
    assert(
      (await native(page)).includes("requestPermission"),
      "OS permission never requested"
    );
    const reg = requests.find(
      r => r.url().endsWith("/api/push/devices") && r.method() === "POST"
    );
    assert(reg, "device token not registered");
    assert(JSON.parse(reg.postData()).platform === "android", "wrong platform");
  }
);

await step("token rotation re-registers the new token", async () => {
  await page.evaluate(() =>
    window.__psstNative.emitTokenRefresh("rotated-token-abcdefghijklmnop:zzz")
  );
  await until(
    () =>
      requests.some(
        r =>
          r.url().endsWith("/api/push/devices") &&
          (r.postData() ?? "").includes("rotated-token")
      ),
    5000,
    "re-registration"
  );
});

await step("deep link to our own host routes in-app", async () => {
  await page.evaluate(() =>
    window.__psstNative.emitDeepLink("https://psst.example.com/settings")
  );
  await page.waitForURL("**/settings", { timeout: 3000 });
  await page.evaluate(() =>
    window.__psstNative.emitDeepLink("https://psst.example.com/inbox")
  );
  await page.waitForURL("**/inbox", { timeout: 3000 });
});

await step("deep link to a foreign host is ignored", async () => {
  await page.evaluate(() =>
    window.__psstNative.emitDeepLink("https://evil.example.org/settings")
  );
  await page.waitForTimeout(500);
  assert(new URL(page.url()).pathname === "/inbox", "foreign link navigated");
});

await step(
  "notification tap routes to its in-app path; protocol-relative / absolute URLs are refused",
  async () => {
    await page.evaluate(() =>
      window.__psstNative.tapNotification({ url: "/settings" })
    );
    await page.waitForURL("**/settings", { timeout: 3000 });
    await page.evaluate(() =>
      window.__psstNative.tapNotification({ url: "//evil.example.org/x" })
    );
    await page.evaluate(() =>
      window.__psstNative.tapNotification({ url: "https://evil.example.org/x" })
    );
    await page.waitForTimeout(500);
    assert(
      new URL(page.url()).origin === APP &&
        new URL(page.url()).pathname === "/settings",
      "unsafe tap target followed"
    );
  }
);

await step(
  "Android back: steps back inside the app, exits only from a root screen",
  async () => {
    await page.goto(`${APP}/inbox`);
    await page.waitForFunction(() => window.__psstNative);
    await page.evaluate(() => window.__psstNative.pressBack());
    await until(
      async () => (await native(page)).includes("exitApp"),
      3000,
      "exitApp from root"
    );
  }
);

await step("on-device layout: no horizontal overflow at 390px", async () => {
  const overflow = await page.evaluate(
    () =>
      document.documentElement.scrollWidth -
      document.documentElement.clientWidth
  );
  assert(overflow <= 0, `overflow ${overflow}px`);
});

await step(
  "sign out revokes the token server-side, and clears the keychain",
  async () => {
    await page.goto(`${APP}/settings`);
    const token = await page.evaluate(() =>
      localStorage.getItem("sim.api-token")
    );
    await page.getByRole("button", { name: "Sign out" }).click();
    await page.waitForURL("**/login", { timeout: 5000 });
    assert(
      !(await page.evaluate(() => localStorage.getItem("sim.api-token"))),
      "token still in keychain"
    );
    assert(
      (await native(page)).includes("signOut"),
      "provider session not signed out"
    );
    const r = await fetch(`${API}/api/me`, {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" }
    });
    assert(r.status === 401, `old token still valid (${r.status})`);
  }
);

await step("no console or page errors", async () => {
  assert(errors.length === 0, errors.slice(0, 3).join(" | "));
});

await browser.close();
appServer.close();
gateway.close();
console.log(failed ? `\n${failed} FAILED` : "\nAll native checks passed");
process.exit(failed ? 1 : 0);
