import { chromium } from "playwright-core";
import fs from "node:fs";

// Usage: reseed the API (php artisan migrate:fresh --seed) with both dev servers running, then: pnpm e2e
// CHROME_PATH overrides the browser binary; BASE_URL the app origin. Screenshots land in e2e/shots/.
const BASE = process.env.BASE_URL ?? "http://localhost:9000";
const OUT = new URL("./shots/", import.meta.url).pathname;
fs.mkdirSync(OUT, { recursive: true });

const results = [];
const errors = [];
const ok = (name, pass, detail = "") => {
  results.push({ name, pass, detail });
  console.log(`${pass ? "PASS" : "FAIL"}  ${name}${detail ? "  — " + detail : ""}`);
};

const browser = await chromium.launch(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {});

async function newCtx(kind, scheme = "light") {
  const viewport = kind === "mobile" ? { width: 375, height: 812 } : { width: 1280, height: 800 };
  const ctx = await browser.newContext({ viewport, colorScheme: scheme, hasTouch: kind === "mobile", isMobile: kind === "mobile" });
  const page = await ctx.newPage();
  page.on("console", (m) => m.type() === "error" && errors.push(`[${kind}] ${m.text()}`));
  page.on("pageerror", (e) => errors.push(`[${kind}] pageerror ${e.message}`));
  return { ctx, page };
}

async function noOverflow(page, label) {
  const [sw, cw] = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth]);
  ok(`no horizontal overflow: ${label}`, sw <= cw, `scrollWidth=${sw} clientWidth=${cw}`);
}

// ---------- Alice (desktop) signs in ----------
const A = await newCtx("desktop");
await A.page.goto(`${BASE}/login`);
await A.page.waitForSelector("text=Say it. Psst.");
await A.page.screenshot({ path: OUT + "01-login-desktop.png" });
await noOverflow(A.page, "login @1280");
await A.page.getByRole("button", { name: "Alice" }).click();
await A.page.waitForURL("**/inbox");
ok("Alice signs in and lands on /inbox", true);
await A.page.waitForSelector("text=No messages yet");
await A.page.screenshot({ path: OUT + "02-inbox-empty-desktop.png" });
await noOverflow(A.page, "inbox empty @1280");

// ---------- Bob (mobile) opens Alice's link as a guest ----------
const B = await newCtx("mobile");
await B.page.goto(`${BASE}/alice`);
await B.page.waitForSelector("text=Send Alice Rahman an anonymous message");
await B.page.screenshot({ path: OUT + "03-profile-mobile.png" });
await noOverflow(B.page, "profile @375");
await B.page.fill("#message", "You always make the room feel warmer.");
const guestCta = await B.page.getByRole("button", { name: "Sign in to send" }).isVisible();
ok("guest sees 'Sign in to send'", guestCta);
await B.page.getByRole("button", { name: "Sign in to send" }).click();
await B.page.waitForURL("**/login?next=/alice");
await B.page.screenshot({ path: OUT + "04-login-mobile.png" });
await noOverflow(B.page, "login @375");
await B.page.getByRole("button", { name: "Bob" }).click();
await B.page.waitForURL("**/alice");
await B.page.waitForSelector("#message");
const restored = await B.page.inputValue("#message");
ok("draft restored after sign-in round trip", restored === "You always make the room feel warmer.", restored);
await B.page.getByRole("button", { name: "Send message" }).click();
await B.page.waitForSelector("text=Sent");
await B.page.screenshot({ path: OUT + "05-sent-mobile.png" });
ok("Bob's message sent", true);

// ---------- Alice sees it (polling or reload) ----------
await A.page.reload();
await A.page.waitForSelector("text=Anonymous #1", { timeout: 10000 });
await A.page.screenshot({ path: OUT + "06-inbox-list-desktop.png" });
ok("Alice's inbox shows Anonymous #1", true);
const html = await A.page.content();
ok("sender identity absent from Alice's DOM", !/Bob Tan|bob@example/.test(html));
await A.page.getByText("Anonymous #1").first().click();
await A.page.waitForSelector('.bubble:has-text("You always make the room feel warmer.")');
await A.page.screenshot({ path: OUT + "07-conversation-desktop.png" });
await noOverflow(A.page, "split pane @1280");

// reply with Enter key
const reply = A.page.getByLabel("Reply");
await reply.fill("That's so kind — thank you!");
await reply.press("Enter");
await A.page.waitForSelector('.bubble:has-text("That\'s so kind")');
ok("Alice replies with Enter", true);
await A.page.waitForTimeout(500);
await A.page.screenshot({ path: OUT + "08-conversation-replied-desktop.png" });

// ---------- Bob sees the reply on mobile ----------
await B.page.goto(`${BASE}/sent`);
await B.page.waitForSelector("text=Alice Rahman");
await B.page.screenshot({ path: OUT + "09-sent-list-mobile.png" });
await noOverflow(B.page, "sent list @375");
await B.page.getByText("Alice Rahman").first().click();
await B.page.waitForSelector('.bubble:has-text("That\'s so kind")');
await B.page.screenshot({ path: OUT + "10-conversation-mobile.png" });
await noOverflow(B.page, "conversation @375");
ok("Bob sees Alice's reply", true);

// bottom nav present on narrow, rail on wide
ok("mobile: bottom tab bar visible", await B.page.locator("nav").first().isVisible());

// ---------- report + block ----------
await B.page.goBack();
const A2 = A.page;
await A2.getByRole("button", { name: "Conversation options" }).click();
await A2.getByText("Block sender").click();
await A2.waitForSelector("text=Block Anonymous #1?");
await A2.screenshot({ path: OUT + "11-block-dialog-desktop.png" });
await A2.getByRole("button", { name: "Block sender" }).click();
await A2.waitForSelector("text=You blocked this sender");
ok("Alice blocks Anonymous #1", true);
await A2.screenshot({ path: OUT + "12-blocked-desktop.png" });

// Bob can no longer reply
await B.page.reload();
await B.page.getByText("Alice Rahman").first().click();
await B.page.waitForSelector("text=isn't accepting replies", { timeout: 10000 });
await B.page.screenshot({ path: OUT + "12b-blocked-mobile.png" });
ok("Bob sees he cannot reply once blocked", true);

// ---------- settings + dark mode ----------
await A2.goto(`${BASE}/settings`);
await A2.waitForSelector("text=Your link");
await A2.screenshot({ path: OUT + "13-settings-desktop.png" });
await A2.getByRole("radio", { name: "Dark" }).click();
await A2.waitForTimeout(300);
await A2.screenshot({ path: OUT + "14-settings-dark-desktop.png" });
await A2.goto(`${BASE}/inbox`);
await A2.waitForSelector("text=Anonymous #1");
await A2.getByText("Anonymous #1").first().click();
await A2.waitForSelector('.bubble:has-text("You always make the room feel warmer.")');
await A2.screenshot({ path: OUT + "15-conversation-dark-desktop.png" });

// unknown profile
await A2.goto(`${BASE}/nobody_here`);
await A2.waitForSelector("text=We couldn't find that link");
ok("unknown link shows a friendly not-found", true);

// sign out
await A2.goto(`${BASE}/settings`);
await A2.getByRole("button", { name: "Sign out" }).click();
await A2.waitForURL("**/login");
ok("sign out returns to /login", true);
await A2.goto(`${BASE}/inbox`);
await A2.waitForURL("**/login?next=/inbox");
ok("protected route redirects guests to login", true);

console.log("\nconsole errors:", errors.length ? errors : "none");
const failed = results.filter((r) => !r.pass);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
await browser.close();
process.exit(failed.length ? 1 : 0);
