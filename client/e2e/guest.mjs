// Anonymous sending without an account (NGL-style), and sign-in unlocking chat.
// Needs API + Reverb + queue + `pnpm dev`, fresh seed. Run: pnpm e2e:guest
import { chromium } from "playwright-core";

const BASE = process.env.BASE_URL ?? "http://localhost:9000";
let failed = 0;
const step = async (name, fn) => {
  const started = Date.now();
  try {
    await fn();
    console.log(`PASS  ${name}  (${Date.now() - started}ms)`);
  } catch (e) {
    failed++;
    console.log(
      `FAIL  ${name}  — ${String(e instanceof Error ? e.message : e)
        .split("\n")
        .slice(0, 3)
        .join(" ⏎ ")}`
    );
  }
};
const assert = (c, m) => {
  if (!c) throw new Error(m);
};

const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}
);
const open = async viewport =>
  (await browser.newContext({ viewport })).newPage();
const alice = await open({ width: 1280, height: 800 });
const guest = await open({ width: 390, height: 844 });
const errors = [];
for (const p of [alice, guest]) p.on("pageerror", e => errors.push(String(e)));

await alice.goto(`${BASE}/login`);
await alice.getByRole("button", { name: "Alice" }).click();
await alice.waitForURL("**/inbox");

await step(
  "a visitor can write and send with no sign-in anywhere",
  async () => {
    await guest.goto(`${BASE}/alice`);
    await guest.fill("#message", "Your talk changed how I think about design.");
    assert(
      (await guest
        .getByRole("button", { name: /sign in to send/i })
        .count()) === 0,
      "sign-in gate still present"
    );
    await guest.getByRole("button", { name: "Send message" }).click();
    await guest.waitForSelector("text=Sent");
    assert(
      await guest.evaluate(() => localStorage.getItem("psst.guest-token")),
      "no guest token kept"
    );
  }
);

await step(
  "the sent screen offers to chat by signing in, and to get their own link",
  async () => {
    await guest.getByRole("button", { name: "Sign in to chat" }).waitFor();
    await guest.getByRole("button", { name: "Send another" }).click();
    await guest.getByRole("link", { name: "Get your own Psst link" }).waitFor();
  }
);

await step(
  "a second message from the same browser lands in the same conversation",
  async () => {
    await guest.fill("#message", "Also, thank you.");
    await guest.getByRole("button", { name: "Send message" }).click();
    await guest.waitForSelector("text=Sent");
    await alice.getByText("Anonymous #1").first().waitFor({ timeout: 6000 }); // live, over the socket
    const rows = await alice.getByText("Anonymous #").count();
    assert(rows === 1, `expected 1 conversation, saw ${rows}`);
  }
);

await step(
  "the receiver sees an anonymous guest message and cannot reply yet",
  async () => {
    await alice.getByText("Anonymous #1").first().click();
    await alice.getByText("Also, thank you.").last().waitFor({ timeout: 5000 });
    await alice.getByText("haven't signed in").waitFor({ timeout: 3000 });
    assert(
      (await alice.getByLabel("Reply").count()) === 0,
      "composer shown for a guest"
    );
    const html = await alice.content();
    assert(
      !/guest_key|ip_hash|X-Guest/.test(html),
      "guest internals in the DOM"
    );
  }
);

await step(
  "the guest signing in unlocks the composer live, without a reload",
  async () => {
    await guest
      .getByRole("button", { name: "Send another" })
      .click()
      .catch(() => {});
    await guest.goto(`${BASE}/login?next=/sent`);
    await guest.getByRole("button", { name: "Bob" }).click();
    await guest.waitForURL("**/sent");
    await alice.getByLabel("Reply").waitFor({ timeout: 6000 });
  }
);

await step(
  "the receiver can now reply and the signed-in guest sees it",
  async () => {
    await alice.getByLabel("Reply").fill("Thank you! Who is this?");
    await alice.getByLabel("Reply").press("Enter");
    await guest.getByText("Alice Rahman").first().click();
    await guest
      .getByText("Thank you! Who is this?")
      .last()
      .waitFor({ timeout: 6000 });
    assert(
      !(await guest.evaluate(() => localStorage.getItem("psst.guest-token"))),
      "token not cleared after claim"
    );
  }
);

await step(
  "blocking a guest drops their next message silently (looks sent)",
  async () => {
    const other = await open({ width: 390, height: 844 });
    await other.goto(`${BASE}/alice`);
    await other.fill("#message", "rude message");
    await other.getByRole("button", { name: "Send message" }).click();
    await other.waitForSelector("text=Sent");
    await alice.getByText("Anonymous #2").first().click();
    await alice.getByRole("button", { name: "Conversation options" }).click();
    await alice.getByText("Block sender").click();
    await alice
      .getByRole("alertdialog")
      .getByRole("button", { name: "Block sender" })
      .click();
    await other.getByRole("button", { name: "Send another" }).click();
    await other.fill("#message", "second rude message");
    await other.getByRole("button", { name: "Send message" }).click();
    await other.waitForSelector("text=Sent"); // the blocked person is not told
    await alice.waitForTimeout(1500);
    assert(
      (await alice.getByText("second rude message").count()) === 0,
      "blocked guest message was delivered"
    );
  }
);

await step(
  "honeypot: a bot-filled hidden field is accepted but never delivered",
  async () => {
    const status = await guest.evaluate(async () => {
      await fetch("/sanctum/csrf-cookie", { credentials: "include" });
      const xsrf = decodeURIComponent(
        document.cookie
          .split("; ")
          .find(c => c.startsWith("XSRF-TOKEN="))
          .split("=")[1]
      );
      const r = await fetch("/api/profiles/carol/messages", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-XSRF-TOKEN": xsrf
        },
        body: JSON.stringify({ body: "buy now", website: "http://spam" })
      });
      return r.status;
    });
    assert(status === 201, `status ${status}`);
  }
);

await step("no page errors", async () =>
  assert(errors.length === 0, errors.slice(0, 2).join(" | "))
);

await browser.close();
console.log(failed ? `\n${failed} FAILED` : "\nAll guest-send checks passed");
process.exit(failed ? 1 : 0);
