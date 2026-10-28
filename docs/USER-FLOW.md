# User flow

Real captures of the running app (`client/e2e/flow-shots.mjs`, mobile 390px, plus desktop and dark).
Full sheet: [`flow/overview.png`](flow/overview.png). Local sign-in shows demo accounts because no Google/Apple client
IDs are configured in dev; in production those buttons replace them.

**Rule: no login to send. Login is for having a link of your own and for chatting.**

## 1. Newcomer: get a link
1. **Sign in** with Google or Apple (SSO only, no passwords) — `01-sign-in.png`. The page opens on a taped note with
   a stamped **SIGN IN** — the thing they can't yet answer — then "sign in to write back". "I only want a link" sits
   underneath as `pick a username instead`, never as a second competing primary.
2. **Pick a username** once; it becomes `https://<domain>/<username>` — `02-pick-username.png`
3. **Inbox** (empty) with *Copy my link* — `03-empty-inbox-with-link.png`

## 2. Sender: no account needed
1. Opens the public link and writes. No login wall — `04-public-link-no-login.png` (also "Get your own Psst link")
2. **Sent.** The browser keeps a secret token. One primary action (*Sign in to chat*), a quiet *send another*, and
   *or get your own link →* — the username form is not inline here — `05-sent-as-guest.png`.
   They can send more without ever signing in.
3. *Optional:* **Sign in to chat** — `06-sign-in-to-chat.png`. Their anonymous thread is claimed into their account.
4. It now appears in **Sent**, and the receiver can reply — `07-sent-list-after-sign-in.png`

The receiver only ever sees "Anonymous #N". Guests have no account, so even admins can't identify them.

## 3. Receiver: read, reply, moderate
1. Inbox lists conversations by recency with unread dots, live via WebSocket — `08-inbox-unread.png`.
   Each note is a sticker; **swipe a card left** (or tab to its Hide button) to clear it from the list. Any new
   message brings the thread back.
2. A **signed-in sender**: reply in the same thread (two-way chat) — `09-conversation-reply.png`.
   Tap a message to react: six emoji, toggled per person, counted across both sides.
3. A **guest**: the composer is replaced by "They haven't signed in, so you can't reply yet". It unlocks live the
   moment they sign in — `10b-guest-cannot-reply-yet.png`
4. ⋮ menu: **Block sender** / report a message — `10-conversation-options.png`, `11-block-confirmation.png`.
   Blocking a guest silently drops their later messages (they aren't told).
5. The signed-in sender sees the reply under the receiver's real name, and their own header reads
   **"you appear as Anonymous #N"** — the mask stays explicit on the side wearing it — `12-sender-sees-reply.png`

## 4. Settings and account
Link, theme, notifications (+ preview toggle), sign out, **Delete account** (type your username) —
`13-settings-top.png`, `14-settings-bottom.png`, `15-delete-account.png`.

## 5. Desktop and dark mode
`16-desktop-split-pane.png`, `17-desktop-dark.png`.

## How this is tested

`client/e2e/two-people.mjs` (`pnpm e2e:two-people`) drives two isolated browser contexts through the whole story:
guest sends → receiver sees Anonymous #1 → composer locked → sender signs in → thread claimed → receiver's composer
unlocks live → reply → sender answers back → reaction. It needs API + Reverb + a queue worker, and the worker must be
started **after** `migrate:fresh --seed`; `scripts/dev-stack.sh --seed` does that in the right order.

## Not shown
Sent tab with several threads, report dialog, notification prompt, admin dashboard (`admin/README.md`), native
sign-in sheets (OS UI), Turnstile challenge (off unless configured).
