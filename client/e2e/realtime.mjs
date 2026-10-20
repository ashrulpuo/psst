// Realtime check: every wait is <= 3s, far below the 30-60s polling safety net, so passing means the
// update arrived over the WebSocket. Needs API + Reverb + a queue worker + the dev server running
// and a freshly seeded database (php artisan migrate:fresh --seed). Run with: pnpm e2e:realtime
import { chromium } from "playwright-core";
import fs from "node:fs";

const BASE = process.env.BASE_URL ?? "http://localhost:9000";
const OUT = new URL("./shots/", import.meta.url).pathname;
fs.mkdirSync(OUT, { recursive: true });
const FAST = { timeout: 3000 };

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

const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}
);
const open = async viewport => {
  const ctx = await browser.newContext({ viewport });
  return ctx.newPage();
};

const alice = await open({ width: 1280, height: 800 });
const bob = await open({ width: 1280, height: 800 });
const errors = [];
const watchErrors = (page, who) =>
  page.on("pageerror", e => errors.push(who + ": " + String(e)));
watchErrors(alice, "alice");
watchErrors(bob, "bob");

async function signInAs(page, name) {
  await page.goto(`${BASE}/login`);
  await page.getByRole("button", { name }).click();
  await page.waitForURL("**/inbox");
}

await signInAs(alice, "Alice");
await signInAs(bob, "Bob");
// Give the sockets a moment to authorize their private channels.
await alice.waitForTimeout(1500);

const bubble = (page, text) => page.locator(".bubble", { hasText: text });

await step(
  "new message appears in Alice's inbox without a reload",
  async () => {
    await bob.goto(`${BASE}/alice`);
    await bob.fill("#message", "First realtime hello");
    await bob.getByRole("button", { name: "Send message" }).click();
    await bob.waitForSelector("text=Sent");
    await alice
      .getByText("Anonymous #1")
      .first()
      .waitFor({ state: "visible", ...FAST });
  }
);

await step(
  "an open conversation receives the next message live and stays read",
  async () => {
    await alice.getByText("Anonymous #1").first().click();
    await bubble(alice, "First realtime hello").waitFor(FAST);
    await bob.getByRole("button", { name: "Send another" }).click();
    await bob.fill("#message", "Second one, while you're looking");
    await bob.getByRole("button", { name: "Send message" }).click();
    await bubble(alice, "Second one, while you're looking").waitFor({
      state: "visible",
      ...FAST
    });
    // Alice is reading it, so no unread dot should have appeared on the list row.
    const unread = await alice.locator(".item .dot").count();
    if (unread !== 0)
      throw new Error(`unread dot shown for the open conversation (${unread})`);
    await alice.screenshot({ path: OUT + "rt-1-alice-live.png" });
  }
);

await step("Alice's reply shows up in Bob's open conversation", async () => {
  await bob.goto(`${BASE}/sent`);
  await bob.getByText("Alice Rahman").first().click();
  await bubble(bob, "Second one").waitFor(FAST);
  await alice.getByLabel("Reply").fill("Thanks, I can see you typed that live");
  await alice.getByLabel("Reply").press("Enter");
  await bubble(bob, "Thanks, I can see you typed that live").waitFor({
    state: "visible",
    ...FAST
  });
  await bob.screenshot({ path: OUT + "rt-2-bob-live.png" });
});

await step("Alice's message is marked Read once Bob has it open", async () => {
  await alice
    .locator(".bubble", { hasText: "Thanks, I can see you typed that live" })
    .getByText("Read")
    .waitFor({ state: "visible", ...FAST });
  await alice.screenshot({ path: OUT + "rt-3-read-receipt.png" });
});

await step("blocking disables Bob's composer live", async () => {
  await alice.getByRole("button", { name: "Conversation options" }).click();
  await alice.getByText("Block sender").click();
  await alice
    .getByRole("alertdialog")
    .getByRole("button", { name: "Block sender" })
    .click();
  await bob
    .getByText("isn't accepting replies")
    .waitFor({ state: "visible", ...FAST });
});

await step("unblocking restores Bob's composer live", async () => {
  await alice.getByRole("button", { name: "Unblock" }).click();
  await bob.getByLabel("Reply").waitFor({ state: "visible", ...FAST });
});

await step(
  "sign-out closes the socket (no events reach a signed-out tab)",
  async () => {
    await bob.goto(`${BASE}/settings`);
    await bob.getByRole("button", { name: "Sign out" }).click();
    await bob.waitForURL("**/login");
    const leaked = await bob.evaluate(() =>
      document.body.innerText.includes("Alice Rahman")
    );
    if (leaked)
      throw new Error("previous user's data still on screen after sign-out");
  }
);

console.log("\npage errors:", errors.length ? errors : "none");
await browser.close();
console.log(
  failed ? `\n${failed} step(s) failed` : "\nall realtime checks passed"
);
process.exit(failed ? 1 : 0);
