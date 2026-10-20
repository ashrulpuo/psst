// Push notification checks. Headless Chromium cannot reach a real push service, so this test:
//  - stubs PushManager.subscribe (the browser side) with a fixed fake subscription,
//  - runs a local fake push service that the Laravel queue worker delivers to, and
//  - delivers pushes straight to the service worker over the DevTools protocol.
//
// Needs: API + queue worker + `pnpm dev:pwa` running, a freshly seeded database, VAPID keys and
// PUSH_EXTRA_ALLOWED_HOSTS=127.0.0.1 in server/.env, and psql on PATH. Run: pnpm e2e:push
import { chromium } from "playwright-core";
import { execFileSync } from "node:child_process";
import http from "node:http";
import fs from "node:fs";

const BASE = process.env.BASE_URL ?? "http://localhost:9000";
const PUSH_PORT = 9911;
const ENDPOINT = `http://127.0.0.1:${PUSH_PORT}/push/e2e-device`;
const OUT = new URL("./shots/", import.meta.url).pathname;
fs.mkdirSync(OUT, { recursive: true });

const db = sql =>
  execFileSync(
    "psql",
    ["-h", "127.0.0.1", "-U", "psst", "-d", "psst", "-tAc", sql],
    {
      env: { ...process.env, PGPASSWORD: process.env.PGPASSWORD ?? "psst" }
    }
  )
    .toString()
    .trim();
const devicesOf = username =>
  Number(
    db(
      `select count(*) from push_subscriptions ps join users u on u.id = ps.user_id where u.username = '${username}'`
    )
  );

// ---- fake push service ----
const received = [];
const pushServer = http.createServer((req, res) => {
  const chunks = [];
  req.on("data", c => chunks.push(c));
  req.on("end", () => {
    received.push({
      method: req.method,
      path: req.url,
      headers: req.headers,
      bytes: Buffer.concat(chunks).length
    });
    res.statusCode = 201;
    res.end();
  });
});
await new Promise(resolve =>
  pushServer.listen(PUSH_PORT, "127.0.0.1", resolve)
);

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
const until = async (predicate, ms = 8000, label = "condition") => {
  const end = Date.now() + ms;
  while (Date.now() < end) {
    if (await predicate()) return;
    await new Promise(r => setTimeout(r, 150));
  }
  throw new Error(`timed out waiting for ${label}`);
};

const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}
);

// ---- Alice's context: stubbed subscribe, notifications granted ----
const ctx = await browser.newContext({
  viewport: { width: 1280, height: 800 }
});
await ctx.grantPermissions(["notifications"], { origin: BASE });
await ctx.addInitScript(endpoint => {
  const KEY = "e2e.push.subscription";
  // Public key from the RFC 8291 test vectors: a valid P-256 point, so the server can really encrypt to it.
  const make = () => ({
    endpoint,
    expirationTime: null,
    toJSON: () => ({
      endpoint,
      expirationTime: null,
      keys: {
        p256dh:
          "BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM",
        auth: "tBHItJI5svbpez7KI4CCXg"
      }
    }),
    unsubscribe: async () => {
      sessionStorage.removeItem(KEY);
      return true;
    }
  });
  PushManager.prototype.getSubscription = async () =>
    sessionStorage.getItem(KEY) ? make() : null;
  PushManager.prototype.subscribe = async () => {
    sessionStorage.setItem(KEY, "1");
    return make();
  };
}, ENDPOINT);

const page = await ctx.newPage();
const errors = [];
page.on("pageerror", e => errors.push(String(e)));

const cdp = await ctx.newCDPSession(page);
await cdp.send("ServiceWorker.enable");
let registrationId;
cdp.on("ServiceWorker.workerRegistrationUpdated", ({ registrations }) => {
  for (const r of registrations)
    if (r.scopeURL.startsWith(BASE)) registrationId = r.registrationId;
});
const deliver = async data => {
  await until(() => registrationId, 10000, "service worker registration");
  await cdp.send("ServiceWorker.deliverPushMessage", {
    origin: BASE,
    registrationId,
    data: typeof data === "string" ? data : JSON.stringify(data)
  });
  await new Promise(r => setTimeout(r, 700));
};
const notifications = () =>
  page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    const list = await reg.getNotifications();
    return list.map(n => ({
      title: n.title,
      body: n.body,
      tag: n.tag,
      url: n.data?.url
    }));
  });
const clearNotifications = () =>
  page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    (await reg.getNotifications()).forEach(n => n.close());
  });
/** With the app closed (no window client) the service worker has nobody to suppress for. */
const withAppClosed = async fn => {
  await page.goto("about:blank");
  await fn();
  await page.goto(`${BASE}/settings`);
  await page.waitForSelector("text=Your link");
};

// ---- sign in as Alice, open Settings ----
await page.goto(`${BASE}/login`);
await page.getByRole("button", { name: "Alice" }).click();
await page.waitForURL("**/inbox");
await page.goto(`${BASE}/settings`);
await page.waitForSelector("text=Notifications");

await step(
  "service worker registers and Settings offers notifications",
  async () => {
    await until(() => registrationId, 10000, "service worker");
    await page
      .getByRole("button", { name: "Turn on notifications" })
      .waitFor({ timeout: 5000 });
    await page.screenshot({ path: OUT + "push-1-settings-off.png" });
  }
);

await step(
  "turning on notifications registers this device with the server",
  async () => {
    await page.getByRole("button", { name: "Turn on notifications" }).click();
    await page
      .getByText("Notifications are on for this device.")
      .waitFor({ timeout: 5000 });
    await until(() => devicesOf("alice") === 1, 5000, "device row");
    await page.screenshot({ path: OUT + "push-2-settings-on.png" });
  }
);

await step(
  "message previews are off by default and the switch persists",
  async () => {
    const sw = page.getByRole("switch", {
      name: "Show message text in notifications"
    });
    if ((await sw.getAttribute("aria-checked")) !== "false")
      throw new Error("previews should default to off");
    await sw.click();
    await until(
      async () => (await sw.getAttribute("aria-checked")) === "true",
      3000,
      "switch on"
    );
    if (db("select push_previews from users where username = 'alice'") !== "t")
      throw new Error("not saved");
    await sw.click();
    await until(
      () =>
        db("select push_previews from users where username = 'alice'") === "f",
      3000,
      "switch off"
    );
  }
);

await step("a push shows a notification when the app is closed", async () => {
  await withAppClosed(() =>
    deliver({
      title: "Anonymous #1",
      body: "You have a new anonymous message",
      tag: "thread:abc",
      url: "/inbox/abc"
    })
  );
  const list = await notifications();
  if (list.length !== 1)
    throw new Error(`expected 1 notification, got ${list.length}`);
  const [n] = list;
  if (
    n.title !== "Anonymous #1" ||
    n.tag !== "thread:abc" ||
    n.url !== "/inbox/abc"
  ) {
    throw new Error(`unexpected notification ${JSON.stringify(n)}`);
  }
});

await step(
  "a newer message in the same conversation replaces the notification",
  async () => {
    await withAppClosed(() =>
      deliver({
        title: "Anonymous #1",
        body: "Second message",
        tag: "thread:abc",
        url: "/inbox/abc"
      })
    );
    const list = await notifications();
    if (list.length !== 1 || list.at(0).body !== "Second message")
      throw new Error(JSON.stringify(list));
  }
);

await step(
  "no notification while the app is open and focused (realtime handles it)",
  async () => {
    await clearNotifications();
    if (!(await page.evaluate(() => document.hasFocus())))
      throw new Error("page not focused in this browser; cannot test");
    await deliver({
      title: "Anonymous #2",
      body: "You are looking at the app",
      tag: "thread:zzz",
      url: "/inbox/zzz"
    });
    const list = await notifications();
    if (list.length !== 0)
      throw new Error(`expected none, got ${JSON.stringify(list)}`);
  }
);

await step(
  "malformed payloads fall back to a generic notification",
  async () => {
    await clearNotifications();
    await withAppClosed(() => deliver("this is not json"));
    const list = await notifications();
    if (
      list.length !== 1 ||
      list[0].title !== "Psst" ||
      list[0].url !== "/inbox"
    )
      throw new Error(JSON.stringify(list));
  }
);

await step(
  "a payload cannot point a notification at another site",
  async () => {
    await clearNotifications();
    await withAppClosed(() =>
      deliver({
        title: "Hi",
        body: "x",
        tag: "t",
        url: "https://evil.example/phish"
      })
    );
    const list = await notifications();
    if (list.length !== 1 || list[0].url !== "/inbox")
      throw new Error(JSON.stringify(list));
    await clearNotifications();
  }
);

await step(
  "notification clicks navigate in the open app, and ignore foreign URLs",
  async () => {
    const sendMessage = data =>
      page.evaluate(
        d =>
          navigator.serviceWorker.dispatchEvent(
            new MessageEvent("message", { data: d })
          ),
        data
      );
    await sendMessage({ type: "navigate", url: "//evil.example/x" });
    await sendMessage({ type: "navigate", url: "https://evil.example/x" });
    await page.waitForTimeout(300);
    if (!page.url().endsWith("/settings"))
      throw new Error(`navigated away to ${page.url()}`);
    await sendMessage({ type: "navigate", url: "/inbox" });
    await page.waitForURL("**/inbox", { timeout: 3000 });
  }
);

// ---- the real server pipeline: Bob messages Alice -> queue worker -> fake push service ----
const bobCtx = await browser.newContext({
  viewport: { width: 1280, height: 800 }
});
const bob = await bobCtx.newPage();
await bob.goto(`${BASE}/login`);
await bob.getByRole("button", { name: "Bob" }).click();
await bob.waitForURL("**/inbox");
const bobSends = async text => {
  await bob.goto(`${BASE}/alice`);
  await bob.fill("#message", text);
  await bob.getByRole("button", { name: "Send message" }).click();
  await bob.waitForSelector("text=Sent");
};

await step(
  "a new message makes the server push an encrypted payload to the device",
  async () => {
    received.length = 0;
    await bobSends("Server-side push check");
    await until(
      () => received.length >= 1,
      10000,
      "request at the push service"
    );
    const r = received[0];
    const h = r.headers;
    if (r.method !== "POST" || r.path !== "/push/e2e-device")
      throw new Error(`${r.method} ${r.path}`);
    if (h["content-encoding"] !== "aes128gcm")
      throw new Error(`content-encoding ${h["content-encoding"]}`);
    if (!String(h.authorization).startsWith("vapid t="))
      throw new Error("missing VAPID authorization");
    if (h.ttl !== "86400" || String(h.topic).length !== 32)
      throw new Error(`ttl/topic ${h.ttl}/${h.topic}`);
    if (r.bytes < 100) throw new Error(`body too small (${r.bytes}B)`);
  }
);

await step(
  "turning notifications off stops server pushes and removes the device",
  async () => {
    await page.goto(`${BASE}/settings`);
    await page.getByRole("button", { name: "Turn off notifications" }).click();
    await page
      .getByText("Notifications are off for this device.")
      .waitFor({ timeout: 5000 });
    await until(() => devicesOf("alice") === 0, 5000, "device removed");
    received.length = 0;
    await bobSends("Nothing should be pushed for this one");
    await page.waitForTimeout(3000);
    if (received.length !== 0)
      throw new Error(`${received.length} push(es) arrived after turning off`);
  }
);

await step(
  "signing out detaches the device; the next account on it takes it over",
  async () => {
    await page.getByRole("button", { name: "Turn on notifications" }).click();
    await until(() => devicesOf("alice") === 1, 5000, "device row");
    await page.getByRole("button", { name: "Sign out" }).click();
    await page.waitForURL("**/login");
    await until(
      () => devicesOf("alice") === 0,
      5000,
      "device detached on sign-out"
    );

    // Carol signs in on the same browser; its existing subscription is re-registered for her.
    await page.getByRole("button", { name: "Carol" }).click();
    await page.waitForURL("**/inbox");
    await until(
      () => devicesOf("carol") === 1,
      5000,
      "device taken over by carol"
    );
    if (devicesOf("alice") !== 0) throw new Error("alice still has the device");
  }
);

console.log("\npage errors:", errors.length ? errors : "none");
await browser.close();
pushServer.close();
console.log(failed ? `\n${failed} step(s) failed` : "\nall push checks passed");
process.exit(failed ? 1 : 0);
