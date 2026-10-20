// Captures the user journey as screenshots into docs/flow/. Needs API + Reverb + queue + `pnpm dev`, fresh seed + a
// username-less user dave@example.test. Run: node e2e/flow-shots.mjs
import { chromium } from "playwright-core";
import fs from "node:fs";

const BASE = "http://localhost:9000";
const OUT = new URL("../../docs/flow/", import.meta.url).pathname;
fs.mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium-1194/chrome-linux/chrome" });
const mobile = { width: 390, height: 844 };
const ctxOf = (viewport, scheme = "light") => browser.newContext({ viewport, colorScheme: scheme, deviceScaleFactor: 2 });
const shot = async (page, name) => { await page.waitForTimeout(500); await page.screenshot({ path: `${OUT}${name}.png` }); console.log("shot", name); };
const demo = async (page, who) => { await page.getByRole("button", { name: who }).click(); };

// ---- 1. Newcomer: sign in, claim username ----
{
  const ctx = await ctxOf(mobile); const page = await ctx.newPage();
  await page.goto(`${BASE}/login`);
  await shot(page, "01-sign-in");
  await page.evaluate(async () => {
    await fetch("/sanctum/csrf-cookie");
    const xsrf = decodeURIComponent(document.cookie.split("; ").find(c => c.startsWith("XSRF-TOKEN=")).split("=")[1]);
    await fetch("/api/dev/login", { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": xsrf }, body: JSON.stringify({ email: "dave@example.test" }) });
  });
  await page.goto(`${BASE}/inbox`);
  await page.waitForURL("**/welcome");
  await page.getByLabel("Username").fill("dave");
  await shot(page, "02-pick-username");
  await page.getByRole("button", { name: "Create my link" }).click();
  await page.waitForURL("**/inbox");
  await shot(page, "03-empty-inbox-with-link");
  await ctx.close();
}

// ---- 2. Sender (Bob): no account needed to send; signing in is for chatting ----
const bobCtx = await ctxOf(mobile); const bob = await bobCtx.newPage();
await bob.goto(`${BASE}/alice`);
await bob.fill("#message", "I've always admired how you handled that presentation. You were great!");
await shot(bob, "04-public-link-no-login");
await bob.getByRole("button", { name: "Send message" }).click();
await bob.waitForSelector("text=Sent");
await shot(bob, "05-sent-as-guest");
await bob.getByRole("button", { name: "Sign in to chat" }).click();
await bob.waitForURL("**/login**");
await shot(bob, "06-sign-in-to-chat");
await demo(bob, "Bob");
await bob.waitForURL("**/sent");
await bob.getByText("Alice Rahman").first().waitFor();
await shot(bob, "07-sent-list-after-sign-in");

// ---- 3. Receiver (Alice): inbox, conversation, reply, block/report ----
const aliceCtx = await ctxOf(mobile); const alice = await aliceCtx.newPage();
await alice.goto(`${BASE}/login`);
await demo(alice, "Alice");
await alice.waitForURL("**/inbox");
// A couple more senders so the inbox looks lived-in.
for (const [who, text] of [["Carol", "Coffee sometime? Your talk gave me ideas."], ["Carol", "Also: thank you for the book recommendation!"]]) {
  const c = await ctxOf(mobile); const p = await c.newPage();
  await p.goto(`${BASE}/alice`); await p.fill("#message", text); await p.getByRole("button", { name: "Send message" }).click(); await p.waitForSelector("text=Sent"); await c.close();
}
await alice.reload();
await alice.getByText("Anonymous #1").first().waitFor();
await shot(alice, "08-inbox-unread");
await alice.getByText("Anonymous #1").first().click();
await alice.waitForURL(/inbox\/[0-9a-f-]{36}/);
await alice.getByLabel("Reply").fill("That means a lot, thank you! Who is this? 😄");
await alice.getByRole("button", { name: /send/i }).last().click();
await alice.getByText("That means a lot").first().waitFor();
await shot(alice, "09-conversation-reply");
await alice.getByRole("button", { name: "Conversation options" }).click();
await shot(alice, "10-conversation-options");
await alice.keyboard.press("Escape");
await alice.getByRole("button", { name: "Back to messages" }).click();
await alice.getByText("Anonymous #2").first().click();
await alice.getByText("haven't signed in").waitFor();
await shot(alice, "10b-guest-cannot-reply-yet");
await alice.getByRole("button", { name: "Back to messages" }).click();
await alice.getByText("Anonymous #1").first().click();
await alice.getByRole("button", { name: "Conversation options" }).click();
await alice.getByText("Block sender").click();
await shot(alice, "11-block-confirmation");
await alice.getByRole("button", { name: "Cancel" }).click();

// Sender sees the reply, anonymity preserved on her side as "Alice"
await bob.goto(`${BASE}/sent`);
await bob.getByText("Alice Rahman").first().click();
await bob.getByText("That means a lot").last().waitFor();
await shot(bob, "12-sender-sees-reply");

// ---- 4. Settings ----
await alice.goto(`${BASE}/settings`);
await shot(alice, "13-settings-top");
await alice.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await shot(alice, "14-settings-bottom");
await alice.getByRole("button", { name: "Delete my account" }).click();
await shot(alice, "15-delete-account");
await alice.getByRole("button", { name: "Cancel" }).click();

// ---- 5. Desktop + dark ----
const desk = await ctxOf({ width: 1280, height: 800 }); const d = await desk.newPage();
await d.goto(`${BASE}/login`); await demo(d, "Alice"); await d.waitForURL("**/inbox");
await d.getByText("Anonymous #1").first().click();
await shot(d, "16-desktop-split-pane");
const dark = await ctxOf({ width: 1280, height: 800 }, "dark"); const dk = await dark.newPage();
await dk.goto(`${BASE}/login`); await demo(dk, "Alice"); await dk.waitForURL("**/inbox");
await dk.getByText("Anonymous #1").first().click();
await shot(dk, "17-desktop-dark");
await browser.close();
