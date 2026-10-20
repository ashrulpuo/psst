// Admin dashboard end-to-end test, driven through a real browser.
// Needs: API on :8000, `pnpm dev` (admin, :9100) with VITE_DEV_LOGIN=true, PSST_DEV_LOGIN=true and
// SANCTUM_STATEFUL_DOMAINS including localhost:9100 in server/.env, and a freshly seeded database
// (php artisan migrate:fresh --seed). Run: pnpm e2e
import { chromium } from "playwright-core";
import crypto from "node:crypto";
import fs from "node:fs";

const BASE = process.env.BASE_URL ?? "http://localhost:9100";
const OUT = new URL("./shots/", import.meta.url).pathname;
fs.mkdirSync(OUT, { recursive: true });

// ---- RFC 6238 TOTP, independent of the server's implementation ----
const base32 = (s) => {
  const A = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  let bits = "";
  for (const c of s.replace(/=+$/, "")) bits += A.indexOf(c).toString(2).padStart(5, "0");
  const bytes = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
  return Buffer.from(bytes);
};
const stepNow = () => Math.floor(Date.now() / 30000);
const totp = (secret, step = stepNow()) => {
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(step));
  const h = crypto.createHmac("sha1", base32(secret)).update(counter).digest();
  const o = h[19] & 15;
  const n = ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];
  return String(n % 1_000_000).padStart(6, "0");
};

let failed = 0;
const step = async (name, fn) => {
  const started = Date.now();
  try {
    await fn();
    console.log(`PASS  ${name}  (${Date.now() - started}ms)`);
  } catch (e) {
    failed++;
    console.log(`FAIL  ${name}  — ${String(e instanceof Error ? e.message : e).split("\n")[0]}`);
  }
};
const expect = (cond, message) => {
  if (!cond) throw new Error(message);
};

const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {},
);
const consoleErrors = [];
const watch = (page, who) => {
  page.on("pageerror", (e) => consoleErrors.push(`${who}: ${String(e)}`));
  page.on("console", (m) => {
    // 401/403 on the bootstrap checks are expected for signed-out or not-yet-verified visitors.
    if (m.type() === "error" && !/status of (401|403|422|404|429)/.test(m.text()))
      consoleErrors.push(`${who}: ${m.text()}`);
  });
};

// ---- API helpers: act as ordinary users to create realistic data ----
async function userSession(email) {
  const ctx = await browser.newContext();
  const req = ctx.request;
  const xsrf = async () => {
    await req.get(`${BASE}/sanctum/csrf-cookie`, { headers: { Origin: BASE } });
    const cookie = (await ctx.cookies()).find((c) => c.name === "XSRF-TOKEN");
    return decodeURIComponent(cookie.value);
  };
  const call = async (method, path, data) => {
    const response = await req.fetch(`${BASE}/api${path}`, {
      method,
      data,
      headers: { Origin: BASE, Accept: "application/json", "X-XSRF-TOKEN": await xsrf() },
    });
    return response.status() === 204 ? null : response.json().catch(() => null);
  };
  await call("POST", "/dev/login", { email });
  return { ctx, call };
}

// ---- arrange: Bob harasses Alice, both report each other, Carol writes once ----
const alice = await userSession("alice@example.test");
const bob = await userSession("bob@example.test");
const carol = await userSession("carol@example.test");
await bob.call("POST", "/profiles/alice/messages", { body: "you are a disappointment" });
await bob.call("POST", "/profiles/alice/messages", { body: "answer me!" });
await carol.call("POST", "/profiles/alice/messages", { body: "love your work" });
const inbox = await alice.call("GET", "/inbox");
const bobThread = inbox.data.find((t) => t.last_message.body === "answer me!");
await alice.call("POST", `/threads/${bobThread.id}/messages`, { body: "Please stop." });
const history = await alice.call("GET", `/threads/${bobThread.id}/messages`);
const bobFirst = history.data.find((m) => m.body === "you are a disappointment");
const aliceReply = history.data.find((m) => m.body === "Please stop.");
await alice.call("POST", `/messages/${bobFirst.id}/report`, { reason: "Harassing messages" });
const bobSide = await bob.call("GET", `/threads/${bobThread.id}/messages`);
await bob.call(
  "POST",
  `/messages/${bobSide.data.find((m) => m.body === "Please stop.").id}/report`,
  { reason: "Rude reply" },
);
expect(aliceReply, "fixture reply missing");

// ---- the admin, desktop ----
const adminCtx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await adminCtx.newPage();
watch(page, "admin");
let secret = "";
let recoveryCodes = [];
let enrolledStep = 0;

const noOverflow = async (p, label) => {
  const [sw, cw] = await p.evaluate(() => [
    document.documentElement.scrollWidth,
    document.documentElement.clientWidth,
  ]);
  expect(sw <= cw, `${label}: page scrolls horizontally (${sw} > ${cw})`);
};

await step("a signed-out visitor lands on the sign-in screen", async () => {
  await page.goto(`${BASE}/users`);
  await page.waitForURL("**/login");
  await page.getByRole("heading", { name: "Admin sign-in" }).waitFor();
  await page.screenshot({ path: OUT + "01-login.png" });
});

await step("a non-admin is told this area is for staff", async () => {
  const aliceCtx = alice.ctx;
  const p = await aliceCtx.newPage();
  watch(p, "alice");
  await p.goto(`${BASE}/`);
  await p.getByRole("heading", { name: "This area is for staff" }).waitFor({ timeout: 8000 });
  const status = await aliceCtx.request.get(`${BASE}/api/admin/overview`, {
    headers: { Origin: BASE, Accept: "application/json" },
  });
  expect(status.status() === 403, `API answered ${status.status()} to a non-admin`);
  await p.close();
});

await step("an admin without two-factor is sent to set it up", async () => {
  await page.goto(`${BASE}/login`);
  await page.getByRole("button", { name: "Site Admin" }).click();
  await page.waitForURL("**/2fa/setup");
  await page.getByRole("heading", { name: "Set up two-factor" }).waitFor();
  await page.locator("img[alt^='QR code']").waitFor();
  secret = (await page.locator("code").first().innerText()).trim();
  expect(/^[A-Z2-7]{32}$/.test(secret), `unexpected secret ${secret}`);
  await page.screenshot({ path: OUT + "02-2fa-setup.png" });
});

await step("a wrong code is refused; the right one shows recovery codes once", async () => {
  await page.getByLabel("Authentication code").fill("000000");
  await page.getByRole("button", { name: "Turn on two-factor" }).click();
  await page.getByText("That code did not match").waitFor();
  enrolledStep = stepNow();
  await page.getByLabel("Authentication code").fill(totp(secret, enrolledStep));
  await page.getByRole("button", { name: "Turn on two-factor" }).click();
  await page.getByRole("heading", { name: "Save your recovery codes" }).waitFor();
  recoveryCodes = await page.locator("ul.font-mono li").allInnerTexts();
  expect(recoveryCodes.length === 8, `expected 8 recovery codes, got ${recoveryCodes.length}`);
  await page.screenshot({ path: OUT + "03-recovery-codes.png" });
  const cont = page.getByRole("button", { name: "Continue to admin" });
  expect(await cont.isDisabled(), "Continue should stay disabled until the codes are acknowledged");
  await page.getByLabel("I've stored these somewhere safe.").check();
  await cont.click();
  await page.getByRole("heading", { name: "Dashboard" }).waitFor();
});

await step("the dashboard shows real figures", async () => {
  const tile = (label) => page.locator(".surface", { hasText: label }).first();
  await tile("Open reports").waitFor();
  expect((await tile("Open reports").innerText()).includes("2"), "open reports should be 2");
  expect((await tile("Users").innerText()).includes("4"), "users should be 4");
  expect((await tile("Messages").innerText()).includes("4"), "messages in 24h should be 4");
  await page.locator("svg[aria-label^='New users per day']").waitFor();
  await page.locator("svg[aria-label^='Messages per day']").waitFor();
  await noOverflow(page, "dashboard @1280");
  await page.screenshot({ path: OUT + "04-dashboard.png" });
});

await step("chart bars have a hover tooltip, keyboard access and a table view", async () => {
  const chart = page.locator("section", {
    has: page.getByRole("heading", { name: "Messages per day" }),
  });
  const bars = chart.locator("rect[role='img']");
  expect((await bars.count()) === 30, "30 days of hit targets expected");
  await bars.last().hover();
  const tip = chart.locator("[role='status']");
  await tip.waitFor();
  expect((await tip.innerText()).includes("4 messages"), `tooltip said: ${await tip.innerText()}`);
  await page.screenshot({ path: OUT + "05-chart-hover.png" });
  // keyboard: focus lands on the newest day; arrow keys move between days
  await bars.last().focus();
  await page.keyboard.press("ArrowLeft");
  const label = await chart.locator("rect[role='img'][tabindex='0']").getAttribute("aria-label");
  expect((label ?? "").endsWith(": 0 messages"), `keyboard focus label: ${label}`);
  await chart.getByRole("button", { name: "Show as table" }).click();
  await chart.locator("table").waitFor();
  expect((await chart.locator("tbody tr").count()) === 30, "table should list 30 days");
  await chart.getByRole("button", { name: "Show as chart" }).click();
});

await step("users: search, then open a profile with real activity numbers", async () => {
  await page.getByRole("link", { name: "Users" }).first().click();
  await page.getByRole("heading", { name: "Users" }).waitFor();
  await page.getByLabel("Search users").fill("bob");
  await page.locator("tbody tr").first().waitFor();
  await page.waitForFunction(() => document.querySelectorAll("tbody tr").length === 1);
  await page.screenshot({ path: OUT + "06-users.png" });
  await page.locator("tbody tr").first().click();
  await page.getByRole("heading", { name: "Bob Tan" }).waitFor();
  const text = await page.locator("main").innerText();
  expect(text.includes("bob@example.test"), "profile should show the real email");
  expect(/Messages written\s*2/.test(text), "Bob wrote 2 messages");
  expect(/Reports against\s*1/.test(text), "one report is against Bob");
  await page.screenshot({ path: OUT + "07-user-detail.png" });
});

await step("conversations show BOTH real identities and a record of viewing", async () => {
  await page.getByRole("link", { name: "Conversations", exact: true }).first().click();
  await page.getByRole("heading", { name: "Conversations" }).first().waitFor();
  await page.locator("tbody tr").first().waitFor();
  const text = await page.locator("tbody").innerText();
  expect(
    text.includes("bob@example.test") && text.includes("alice@example.test"),
    "both emails should be visible",
  );
  await page.screenshot({ path: OUT + "08-conversations.png" });
  await page.locator("tbody tr", { hasText: "bob@example.test" }).first().click();
  await page.locator("li", { hasText: "you are a disappointment" }).first().waitFor();
  const detail = await page.locator("main").innerText();
  expect(
    detail.includes("Sender") && detail.includes("Bob Tan"),
    "sender identity should be named",
  );
  expect(detail.includes("recorded in the audit log"), "audit notice missing");
  await page.screenshot({ path: OUT + "09-thread-detail.png" });
});

await step("reports: context, review-and-ban with a named confirmation, auto-advance", async () => {
  await page.getByRole("link", { name: "Reports" }).first().click();
  await page.getByRole("heading", { name: "Reports" }).first().waitFor();
  const items = page.locator("section[aria-label='Reports'] li a");
  await items.first().waitFor();
  expect((await items.count()) === 2, "two open reports expected");
  expect(
    (await items.first().innerText()).includes("Harassing messages"),
    "oldest report should be first",
  );
  await items.first().click();
  await page.getByRole("heading", { name: "In context" }).waitFor();
  const detail = await page.locator("main").innerText();
  expect(detail.includes("Bob Tan"), "reported user should be Bob");
  expect(detail.includes("reported"), "reported message should be highlighted");
  await page.screenshot({ path: OUT + "10-report-detail.png" });

  await page.getByRole("button", { name: "Review and ban Bob Tan" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByRole("heading", { name: "Ban Bob Tan?" }).waitFor();
  await page.screenshot({ path: OUT + "11-ban-dialog.png" });
  await dialog.getByRole("button", { name: "Ban Bob Tan" }).click();
  // the queue advances to the remaining report and the badge counts down
  await page.waitForFunction(
    () => document.querySelectorAll("section[aria-label='Reports'] li").length === 1,
  );
  await page.getByText("Rude reply").first().waitFor();
});

await step("the banned account is locked out of the API at once", async () => {
  const response = await bob.ctx.request.get(`${BASE}/api/me`, {
    headers: { Origin: BASE, Accept: "application/json" },
  });
  expect(
    response.status() === 403 || response.status() === 401,
    `Bob's session still works (${response.status()})`,
  );
});

await step("dismissing the last report clears the queue", async () => {
  await page.getByRole("button", { name: "Dismiss" }).waitFor();
  await page.getByRole("button", { name: "Dismiss" }).click();
  await page.getByText("Queue is clear").waitFor();
  await page.screenshot({ path: OUT + "12-queue-clear.png" });
});

await step("the audit log recorded identity views, the ban and both decisions", async () => {
  await page.getByRole("link", { name: "Audit log" }).first().click();
  await page.getByRole("heading", { name: "Audit log" }).waitFor();
  await page.locator("tbody tr").first().waitFor();
  const text = await page.locator("tbody").innerText();
  for (const expected of [
    "User banned",
    "Report reviewed",
    "Report dismissed",
    "Thread viewed",
    "User viewed",
    "Admin 2fa enrolled",
  ]) {
    expect(text.includes(expected), `audit log is missing "${expected}"`);
  }
  expect(text.includes("admin@example.test"), "actor should be shown");
  await page.getByLabel("Action").selectOption("user.banned");
  await page.waitForFunction(() =>
    [...document.querySelectorAll("tbody tr")].every((r) => r.innerText.includes("User banned")),
  );
  await page.screenshot({ path: OUT + "13-audit.png" });
});

await step("dark mode", async () => {
  await page.emulateMedia({ colorScheme: "dark" });
  await page.getByRole("link", { name: "Dashboard" }).first().click();
  await page.getByRole("heading", { name: "Dashboard" }).waitFor();
  await page.waitForTimeout(400);
  expect(
    await page.evaluate(() => document.documentElement.classList.contains("dark")),
    "dark class should be set",
  );
  await page.screenshot({ path: OUT + "14-dashboard-dark.png" });
  await page.emulateMedia({ colorScheme: "light" });
});

await step("narrow screens: menu sheet, no horizontal scroll, single-pane reports", async () => {
  const mobile = await browser.newContext({
    viewport: { width: 375, height: 812 },
    isMobile: true,
    hasTouch: true,
    storageState: await adminCtx.storageState(),
  });
  const m = await mobile.newPage();
  watch(m, "mobile");
  await m.goto(`${BASE}/`);
  await m.getByRole("heading", { name: "Dashboard" }).waitFor();
  await noOverflow(m, "dashboard @375");
  await m.screenshot({ path: OUT + "15-dashboard-mobile.png" });
  await m.getByRole("button", { name: "Open menu" }).click();
  await m.getByRole("dialog").getByRole("link", { name: "Users" }).waitFor();
  await m.screenshot({ path: OUT + "16-menu-mobile.png" });
  await m.getByRole("dialog").getByRole("link", { name: "Users" }).click();
  await m.getByRole("heading", { name: "Users" }).waitFor();
  await m.locator("tbody tr").first().waitFor();
  await noOverflow(m, "users @375");
  await m.goto(`${BASE}/audit`);
  await m.locator("tbody tr").first().waitFor();
  await noOverflow(m, "audit @375");
  await mobile.close();
});

await step("signing back in needs the second factor; a used code can't be replayed", async () => {
  await page.getByRole("button", { name: "Sign out" }).click();
  await page.waitForURL("**/login");
  await page.getByRole("button", { name: "Site Admin" }).click();
  await page.waitForURL("**/2fa");
  await page.getByRole("heading", { name: "Enter your code" }).waitFor();
  await page.screenshot({ path: OUT + "17-verify.png" });
  // the code from enrolment, a few moments ago: already used
  await page.getByLabel("Authentication or recovery code").fill(totp(secret, enrolledStep));
  await page.getByRole("button", { name: "Verify" }).click();
  await page.getByText("That code did not work").waitFor();
});

await step("a recovery code signs in once and only once", async () => {
  await page.getByLabel("Authentication or recovery code").fill(recoveryCodes[0]);
  await page.getByRole("button", { name: "Verify" }).click();
  await page.getByRole("heading", { name: "Dashboard" }).waitFor();
  await page.getByRole("button", { name: "Sign out" }).click();
  await page.waitForURL("**/login");
  await page.getByRole("button", { name: "Site Admin" }).click();
  await page.waitForURL("**/2fa");
  await page.getByLabel("Authentication or recovery code").fill(recoveryCodes[0]);
  await page.getByRole("button", { name: "Verify" }).click();
  // Either answer is a refusal: the code was already consumed, or the 5-attempts-a-minute limit (which the
  // enrolment and sign-in steps above have already used up) kicked in first. Single use itself is proven
  // in the PHPUnit suite, where the clock is not shared with other steps.
  await page.getByText(/That code did not work|Too many attempts/).waitFor();
  expect(page.url().endsWith("/2fa"), "a reused recovery code must not let anyone in");
});

console.log("\nunexpected console errors:", consoleErrors.length ? consoleErrors : "none");
await browser.close();
console.log(failed ? `\n${failed} step(s) failed` : "\nall admin checks passed");
process.exit(failed ? 1 : 0);
