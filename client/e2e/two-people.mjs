// Two people, two isolated browser contexts: a receiver who is signed in, and a sender
// who starts as a guest, then signs in to unlock the chat.
//
// Needs the API, Reverb, `pnpm dev` and a queue worker — and the worker must be started
// AFTER `php artisan migrate:fresh --seed` (dropping the jobs table makes it exit).
// Without a worker the run still passes, but the live unlock falls back to the 15s poll.
// Run from client/: CHROME_PATH="..." node e2e/.tmp-two-people.mjs
import { chromium } from "playwright-core";
import fs from "node:fs";

const BASE = "http://localhost:9000";
const OUT = "e2e/shots/two-people/";
fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}
);
const viewport = { width: 390, height: 844 };
const aliceCtx = await browser.newContext({ viewport });
const guestCtx = await browser.newContext({ viewport });
const alice = await aliceCtx.newPage();
const guest = await guestCtx.newPage();

let failed = 0;
const step = async (name, fn) => {
  const started = Date.now();
  try {
    await fn();
    console.log(`PASS  ${name}  (${Date.now() - started}ms)`);
  } catch (e) {
    failed++;
    console.log(`FAIL  ${name}  — ${String(e).split("\n")[0]}`);
    await alice.screenshot({ path: `${OUT}FAIL-alice.png` }).catch(() => {});
    await guest.screenshot({ path: `${OUT}FAIL-guest.png` }).catch(() => {});
  }
};
const shot = (page, name) =>
  page.screenshot({ path: `${OUT}${name}.png`, animations: "disabled" });
const errors = [];
alice.on("pageerror", e => errors.push(`alice: ${e.message}`));
guest.on("pageerror", e => errors.push(`guest: ${e.message}`));

// ---------- 1. Alice is signed in ----------
await step("Alice signs in and lands on her link", async () => {
  await alice.goto(`${BASE}/login`);
  await alice.getByRole("button", { name: "Alice" }).click();
  await alice.waitForURL("**/link");
  await shot(alice, "1-alice-link");
});

// ---------- 2. A stranger sends without an account ----------
await step("Guest sends from /alice with no login", async () => {
  await guest.goto(`${BASE}/alice`);
  await guest.fill(
    "#message",
    "you were the best part of that meeting. just so you know"
  );
  await guest.getByRole("button", { name: "Send anonymously" }).click();
  await guest.waitForSelector("text=Sent", { timeout: 10000 });
  await shot(guest, "2-guest-sent");
});

// ---------- 3. Alice sees an anonymous thread ----------
await step("Alice's inbox names the sender only as Anonymous #1", async () => {
  await alice.goto(`${BASE}/inbox`);
  await alice.waitForSelector("text=Anonymous #1", { timeout: 10000 });
  await shot(alice, "3-alice-inbox");
});

await step("Alice cannot reply while they stay a guest", async () => {
  await alice.getByText("Anonymous #1").first().click();
  await alice.waitForSelector("text=They're still a guest", {
    timeout: 10000
  });
  await shot(alice, "4-alice-guest-locked");
});

// ---------- 4. The sender signs in; the thread is claimed ----------
await step("Guest signs in and the thread is claimed into Sent", async () => {
  await guest.getByRole("button", { name: "Sign in to chat" }).click();
  await guest.waitForURL("**/login**");
  await guest.getByRole("button", { name: "Bob" }).click();
  await guest.waitForURL("**/sent", { timeout: 15000 });
  // The claim is only real if the thread is now in Sent, under Alice's name.
  await guest.waitForSelector("text=Alice Rahman", { timeout: 15000 });
  await shot(guest, "5-guest-now-bob-sent");
});

await step("Alice's composer unlocks live, without a reload", async () => {
  // ~1s when Reverb + worker are live; ~15s when it has to fall back to polling.
  await alice.getByLabel("Reply").waitFor({ state: "visible", timeout: 20000 });
  await shot(alice, "6-alice-unlocked");
});

// ---------- 5. Two-way chat ----------
await step("Alice replies", async () => {
  await alice.getByLabel("Reply").fill("thank you for saying that 🤍");
  await alice.getByLabel("Reply").press("Enter");
  await alice.waitForSelector('.bubble:has-text("thank you for saying that")');
  await shot(alice, "7-alice-replied");
});

await step("Bob sees the reply under Alice's real name", async () => {
  await guest.getByText("Alice Rahman").first().click();
  await guest.waitForSelector('.bubble:has-text("thank you for saying that")', {
    timeout: 10000
  });
  await shot(guest, "8-bob-sees-reply");
});

await step("Bob reacts, and it reaches Alice live", async () => {
  await guest.getByRole("button", { name: "React to this note" }).click();
  await guest.getByRole("button", { name: "🔥" }).click();
  // Alice is not the one who reacted, so the chip arrives with aria-pressed="false".
  await alice.waitForSelector('button:has-text("🔥")', { timeout: 10000 });
  await shot(alice, "9-alice-sees-reaction");
});

await step("Bob writes back, and Alice sees it live", async () => {
  await guest.getByLabel("Reply").fill("anytime. glad i said it");
  await guest.getByLabel("Reply").press("Enter");
  await alice.waitForSelector('.bubble:has-text("anytime. glad i said it")', {
    timeout: 10000
  });
  await shot(alice, "10-alice-sees-bob-reply");
});

console.log(`\npage errors: ${errors.length ? errors.join(" | ") : "none"}`);
console.log(failed === 0 ? "ALL TWO-PERSON CHECKS PASSED" : `${failed} FAILED`);
await browser.close();
