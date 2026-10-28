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
| `--paper` / `--canvas` | `#FFF6EE` | app canvas, halftone dots on the desk |
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
- **Dark mode:** its own palette (`#14101C` canvas, `#1D1728` surface, edge `#000`), never an inversion.
- **Anti-slop:** no glassmorphism, no blurred drop shadows for hierarchy, no rainbow gradients inside text. One loud
  element per screen: the action.

## Motion

- **The press:** controls drop 4–5px and lose their hard shadow in 120ms; release springs back at 220ms.
- **Send:** 12 emoji on random arcs for ~900ms, the card pops 0.96 → 1.04 → 1, one haptic tick.
- **Swipe:** follows the finger; open past 72px, snap back otherwise. Focus-within opens the same panel, so the
  gesture is never the only path.
- **Loops:** only the send-page sticker floats. Lists never loop. Everything stops under
  `prefers-reduced-motion: reduce`, including the celebration.

## Key screens (as shipped)

- **Public link page `/{username}`** — sticker avatar with a taped "ask me anything", `@username`, four prompt chips
  that seed the composer, paper composer with an emoji row, chunky send, privacy line.
- **Sent** — the reward card: emoji celebration, "she sees you as Anonymous #N", what-happens-next chips, sign-in as
  an upgrade rather than a wall.
- **Inbox** — "notes for you" plus an `n new` pill; gradient note stickers that cycle colour by position, 2-line
  preview, "Anonymous #N", time, lime unread dot, chevron; swipe left (or focus the button) to hide.
- **Chat** — paper-theirs / candy-mine bubbles, day pill, header meta ("sent you a note · 10:53"), reaction bar with a
  six-emoji picker, dashed guest-locked card, chunky composer.
- **Your link** — the loudest sticker: gradient card with handle, QR printed inside it, copy/share, two steps, a
  story-card preview.
- **Settings / auth** — same primitives: bordered sections, sticker empty states, ink focus rings.
- **Admin** (separate SPA) is the exception: dense and utilitarian, still on the old violet accent. Restyling it is a
  follow-up, not part of this system.

## Interactions

| | Rule |
|---|---|
| **Hide** | Per user, per thread; clears a note from your list only. Any new message restores it for both sides. Hiding never blocks anyone. |
| **React** | Both participants, six whitelisted emoji, second tap removes. Reactions ride the message payload; the other side sees them on the next refresh (no realtime push yet). |
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
