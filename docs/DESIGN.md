# Psst — UI/UX design

The shipped visual system is **Sticker Shop** (direction A in `docs/mockups/`). Everything below describes what the
client actually does; the tokens live in `client/src/css/app.scss` and nothing outside a gradient stop may hard-code a
colour.

## Principles

1. **Playful, tactile, private.** The product is small brave notes, so the interface is stickers and paper, not forms.
2. **Fast = beautiful.** Optimistic send, skeletons only beyond 1s, no spinner for navigation.
3. **One obvious action per screen.** Verb-first CTAs, one loud element, everything else quiet.
4. **Safety is visible.** Hide, block and report are one tap away; the privacy model is stated in plain words.
5. **Same feel everywhere** — web, PWA, Capacitor and Electron share one 390px column and one token set.

## Visual system

| Token | Value | Use |
|---|---|---|
| `--paper` / `--canvas` | `#FFF6EE` | flat canvas colour: chrome bars, inner tiles, the desk |
| `--canvas-top` → `--canvas-bottom` | `#FFF7EF` → `#FFEEDE` | the canvas itself: every full-screen shell paints `--canvas-grad`, a soft top-to-bottom wash |
| `--surface` | `#FFFFFF` | cards, composer, tab bar |
| `--cream` | `#FFE9D6` | chips, day pill, empty-state tile |
| `--ink` / `--edge` | `#1B1024` | text, 2.5px borders, hard shadows |
| `--accent` | `#FF2D6F` | decoration and large type only |
| `--accent-strong` | `#D6004F` | fills under white text (5.5:1) |
| `--lime` | `#C9FF4F` | "n new", unread dot, tape labels |
| `--grad-pink` / `--grad-ocean` / `--grad-sunset` / `--grad-link` | see `app.scss` | note stickers, link sticker |

- **Elevation:** a 2.5px ink border plus a hard offset shadow (`0 4px` controls, `0 6–9px` stickers). No blurred depth.
- **Radius:** 15 controls, 20 stickers, 24 cards, 999 pills.
- **Spacing:** 4/8pt scale (`--s-1` … `--s-7`).
- **Type:** **Bricolage Grotesque 800** display (31 / 28 / 22 / 17, tracking −0.03em); **Inter 400–600** body
  (16.5 message, 14 copy, 12 meta). Nunito stays installed as the display fallback.
- **Icons:** Lucide for controls; colour emoji for the tab bar, prompt chips and reactions (forced with an emoji font
  stack so 🔗 and ⚙️ don't render as grey glyphs).
- **Contrast rule:** white body text only sits on gradient stops at ≥4.5:1 — that is why the shipped gradients are
  deeper than the illustrative mockup.
- **Frosted chrome:** the chat header and footer sit on `color-mix(--canvas 86%)` + `blur(10px)` so the wash runs
  underneath instead of stopping at a seam.
- **Dark mode:** its own palette (`#14101C` canvas, `#1D1728` surface, edge `#000`, wash `#17131F` → `#0F0C16`),
  never an inversion.
- **Anti-slop:** no glassmorphism, no blurred drop shadows for hierarchy, no rainbow gradients inside text. One loud
  element per screen: the action.

## Motion

- **The press:** controls drop 4–5px and lose their hard shadow in 120ms; release springs back at 220ms.
- **Send:** 12 emoji on random arcs for ~900ms, the card pops 0.96 → 1.04 → 1, one haptic tick.
- **Swipe:** follows the finger; open past 72px, snap back otherwise. Focus-within opens the same panel, so the
  gesture is never the only path.
- **Loops:** three, all slow and all behind content — the send-page sticker floats, the homepage note wall drifts,
  and the sign-in page runs two 17s/21s glows with the taped note swaying ~1°. Lists never loop. Everything stops
  under `prefers-reduced-motion: reduce`, including the celebration and the glows.

## Key screens (as shipped)

- **Sign in `/login`** — leads with the reason: a taped gradient note ("a note is waiting for you", a stamped
  **SIGN IN**), then "sign in to write back", the provider slot, `no account? pick a username instead`, and three
  dashed trust pills (no password / no spam / delete anytime). No password fields, no marketing hero.
- **Public link page `/{username}`** — sticker avatar with a taped "ask me anything", `@username`, four prompt chips
  that seed the composer, paper composer with an emoji row, chunky send, privacy line.
- **Sent** — the reward card: emoji celebration, one sentence on why signing in helps, then **one** primary action
  (sign in / view conversation), a quiet *send another*, and *or get your own link →*. The username form lives at
  `/get-link`, not here.
- **Inbox** — "notes for you" plus an `n new` pill; gradient note stickers that cycle colour by position, 2-line
  preview, "Anonymous #N", time, lime unread dot, chevron; swipe left (or focus the button) to hide.
- **Chat** — paper-theirs / candy-mine bubbles, day pill, reaction bar with a six-emoji picker, dashed guest-locked
  card, chunky composer. Header meta is side-specific: the receiver reads "sent you a note · 10:53"; the sender reads
  **"you appear as Anonymous #N · 10:53"** — the API returns their own label, so the mask is never a mystery.
- **Your link** — the loudest sticker: gradient card with handle, QR printed inside it, copy/share, two steps, a
  story-card preview.
- **Settings / auth** — same primitives: bordered sections, sticker empty states, ink focus rings.
- **Admin** (separate SPA) is the exception by register, not by palette: dense, neutral surfaces, but the same
  hot-pink accent so the two apps read as one product. Sticker treatments stay in the app.

## Interactions

| | Rule |
|---|---|
| **Hide** | Per user, per thread; clears a note from your list only. Any new message restores it for both sides. Hiding never blocks anyone. |
| **React** | Both participants, six whitelisted emoji, second tap removes. The tally is pushed to both participants over Reverb, each with their own `mine` flag. |
| **Block / report** | Receiver-only; block lives in the chat ⋮ menu, report is a chip under the message. |

## UX rules

- Blur-first validation; errors never blame the person and always offer a next step.
- Inline edits over modals; destructive actions name what they destroy.
- Loaders: none <100ms, inline <1s, skeletons beyond. Zero layout shift.
- Full keyboard access, 3px ink focus rings, WCAG AA contrast, 44px touch targets, 375→1280px with no horizontal
  scroll.
- Copy tone: warm, short, human. All strings live in `client/src/i18n/` so a second language is a new file.

## Stack

Quasar (Vue 3 + TS) with a custom CSS-variable token layer overriding Quasar so nothing looks like stock Material.
Admin: Vue 3 + Tailwind v4 + shadcn-vue. Design source: `docs/mockups/` (two directions) and
`docs/superpowers/plans/` (how it was built).

Local stack: `scripts/dev-stack.sh [--seed]` starts API, Reverb, a **self-restarting** queue worker and the dev
server — the worker restarts because `migrate:fresh` drops the `jobs` table and a running worker exits, after which
nothing arrives live and the app quietly falls back to polling.
