# User flow

Screenshots are real captures of the running app (`client/e2e/flow-shots.mjs`, mobile 390px, plus desktop and dark).
Full sheet: [`flow/overview.png`](flow/overview.png). Local sign-in shows demo accounts because no Google/Apple client
IDs are configured in dev; in production those buttons replace them.

## 1. Newcomer: get a link
1. **Sign in** with Google or Apple (SSO only, no passwords) — `flow/01-sign-in.png`
2. **Pick a username** once; it becomes `https://<domain>/<username>` and can't change — `02-pick-username.png`
3. **Inbox** (empty) with *Copy my link* to share it on social — `03-empty-inbox-with-link.png`

## 2. Sender: anonymous to the receiver
1. Opens the public link, writes a message — `04-public-link-draft.png` (signed out, draft saved)
2. **Sign in to send** — `05-sender-sign-in.png`
3. Back on the page with the draft restored — `06-draft-restored-after-sign-in.png`
4. **Sent** — can *View conversation* or *Send another* — `07-sent-confirmation.png`

The receiver only ever sees "Anonymous #N". Admins can see real identities (disclosed on the sign-in page).

## 3. Receiver: read, reply, moderate
1. Inbox lists conversations by recency with unread dots; live via WebSocket — `08-inbox-unread.png`
2. Open one, reply in the same thread (two-way chat) — `09-conversation-reply.png`
3. ⋮ menu: **Block sender** / report a message — `10-conversation-options.png`
4. Blocking names what happens before you confirm — `11-block-confirmation.png`
5. The sender sees the reply under the receiver's real name; if blocked, their composer disables live —
   `12-sender-sees-reply.png`

## 4. Settings and account
Link, theme, notifications (+ preview toggle), sign out, **Delete account** (type your username to confirm) —
`13-settings-top.png`, `14-settings-bottom.png`, `15-delete-account.png`.

## 5. Desktop and dark mode
Split pane (list + conversation) from 900px up, light and dark — `16-desktop-split-pane.png`, `17-desktop-dark.png`.

## Not shown
Sent tab, report dialog, notification permission prompt, admin dashboard (see `admin/README.md`), native sign-in
sheets (they are OS UI).
