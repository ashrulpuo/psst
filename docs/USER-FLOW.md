# User flow

Real captures of the running app (`client/e2e/flow-shots.mjs`, mobile 390px, plus desktop and dark).
Full sheet: [`flow/overview.png`](flow/overview.png). Local sign-in shows demo accounts because no Google/Apple client
IDs are configured in dev; in production those buttons replace them.

**Rule: no login to send. Login is for having a link of your own and for chatting.**

## 1. Newcomer: get a link
1. **Sign in** with Google or Apple (SSO only, no passwords) — `01-sign-in.png`
2. **Pick a username** once; it becomes `https://<domain>/<username>` — `02-pick-username.png`
3. **Inbox** (empty) with *Copy my link* — `03-empty-inbox-with-link.png`

## 2. Sender: no account needed
1. Opens the public link and writes. No login wall — `04-public-link-no-login.png` (also "Get your own Psst link")
2. **Sent.** The browser keeps a secret token. They are offered *Sign in to chat* — `05-sent-as-guest.png`.
   They can send more without ever signing in.
3. *Optional:* **Sign in to chat** — `06-sign-in-to-chat.png`. Their anonymous thread is claimed into their account.
4. It now appears in **Sent**, and the receiver can reply — `07-sent-list-after-sign-in.png`

The receiver only ever sees "Anonymous #N". Guests have no account, so even admins can't identify them.

## 3. Receiver: read, reply, moderate
1. Inbox lists conversations by recency with unread dots, live via WebSocket — `08-inbox-unread.png`
2. A **signed-in sender**: reply in the same thread (two-way chat) — `09-conversation-reply.png`
3. A **guest**: the composer is replaced by "They haven't signed in, so you can't reply yet". It unlocks live the
   moment they sign in — `10b-guest-cannot-reply-yet.png`
4. ⋮ menu: **Block sender** / report a message — `10-conversation-options.png`, `11-block-confirmation.png`.
   Blocking a guest silently drops their later messages (they aren't told).
5. The signed-in sender sees the reply under the receiver's real name — `12-sender-sees-reply.png`

## 4. Settings and account
Link, theme, notifications (+ preview toggle), sign out, **Delete account** (type your username) —
`13-settings-top.png`, `14-settings-bottom.png`, `15-delete-account.png`.

## 5. Desktop and dark mode
`16-desktop-split-pane.png`, `17-desktop-dark.png`.

## Not shown
Sent tab with several threads, report dialog, notification prompt, admin dashboard (`admin/README.md`), native
sign-in sheets (OS UI), Turnstile challenge (off unless configured).
