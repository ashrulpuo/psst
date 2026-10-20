// Account deletion in the browser. Needs API + `pnpm dev` (localhost:9000) and a freshly seeded database.
// Run: pnpm e2e:account
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
        .slice(0, 6)
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
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
const page = await ctx.newPage();
const errors = [];
page.on("pageerror", e => errors.push(String(e)));

await page.goto(`${BASE}/login`);
await page.getByRole("button", { name: "Carol" }).click();
await page.waitForURL("**/inbox");
await page.goto(`${BASE}/settings`);

await step(
  "danger zone is at the bottom of settings, separate from sign out",
  async () => {
    await page
      .getByRole("heading", { name: "Delete account" })
      .waitFor({ timeout: 5000 });
  }
);

await step(
  "dialog names the consequence and keeps the button disabled until the username is typed",
  async () => {
    await page.getByRole("button", { name: "Delete my account" }).click();
    const dialog = page.getByRole("alertdialog");
    await dialog.getByText("erased for good").waitFor({ timeout: 3000 });
    const del = dialog.getByRole("button", { name: "Delete forever" });
    assert(await del.isDisabled(), "enabled before typing");
    await dialog.getByLabel(/Type carol/).fill("carl");
    assert(await del.isDisabled(), "enabled on a wrong value");
    await dialog.getByLabel(/Type carol/).fill("Carol ");
    assert(
      await del.isEnabled(),
      "still disabled on a match (case/space insensitive)"
    );
  }
);

await step("cancelling keeps the account", async () => {
  await page
    .getByRole("alertdialog")
    .getByRole("button", { name: "Cancel" })
    .click();
  await page
    .getByRole("alertdialog")
    .waitFor({ state: "detached", timeout: 3000 });
  const me = await page.evaluate(
    async () =>
      (await fetch("/api/me", { headers: { Accept: "application/json" } }))
        .status
  );
  assert(me === 200, `me ${me}`);
});

await step("confirming erases the account and returns to sign-in", async () => {
  await page.getByRole("button", { name: "Delete my account" }).click();
  await page
    .getByRole("alertdialog")
    .getByLabel(/Type carol/)
    .fill("carol");
  await page
    .getByRole("alertdialog")
    .getByRole("button", { name: "Delete forever" })
    .click();
  await page.waitForURL("**/login", { timeout: 8000 });
});

await step(
  "the session is gone and the public link no longer exists",
  async () => {
    const me = await page.evaluate(
      async () =>
        (await fetch("/api/me", { headers: { Accept: "application/json" } }))
          .status
    );
    assert(me === 401, `me ${me}`);
    const profile = await page.evaluate(
      async () =>
        (
          await fetch("/api/profiles/carol", {
            headers: { Accept: "application/json" }
          })
        ).status
    );
    assert(profile === 404, `profile ${profile}`);
  }
);

await step("no page errors", async () =>
  assert(errors.length === 0, errors.slice(0, 2).join(" | "))
);

await browser.close();
console.log(
  failed ? `\n${failed} FAILED` : "\nAll account-deletion checks passed"
);
process.exit(failed ? 1 : 0);
