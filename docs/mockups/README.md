# Psst — two UI/UX directions

Two interactive mockups of the same product, drawn at **390 × 844** (iPhone) and built from the real flow in
[`../USER-FLOW.md`](../USER-FLOW.md): no login to send → optional sign-in to chat → inbox → reply → share your link.

| File | Direction | Mood |
|---|---|---|
| [`a-sticker-shop.html`](a-sticker-shop.html) | **Sticker Shop** | Daylight, loud, tactile — the NGL "bright" read |
| [`b-after-hours.html`](b-after-hours.html) | **After Hours** | After dark, glowing, cinematic — the NGL "late night" read |

Open either file in a browser. Everything is live: press the buttons, swipe a note, tap a chip, type in the composer,
**press and hold the orb in direction B**. No build step, no dependencies beyond Google Fonts.

**Platform: both.** One responsive layout, mobile-first. The phone screens are the source of truth; the desktop/Electron
shell keeps the same tokens but swaps the story rail for the existing two-pane inbox/sent layout (mockup shows the
mobile composition only). Capacitor builds inherit the mobile screens as-is.

---

## Direction A — Sticker Shop

**Concept.** Psst as a table full of stickers and marker pens. Cream paper canvas with a faint halftone dot, notes as
candy-gradient stickers, chunky ink-outlined buttons that physically press into the page, emoji that pop when tapped.
Loud enough to screenshot, cheap enough to feel casual.

**Palette**

| Token | Hex | Use |
|---|---|---|
| ink | `#1B1024` | text, 2–3px borders, hard shadows |
| paper | `#FFF6EE` | canvas |
| cream | `#FFE9D6` | chips, secondary surfaces |
| hot pink | `#FF2D6F` | primary action, own messages |
| pink deep | `#D6004F` | button shadow, links |
| tangerine | `#FF8A3D` | gradient start (notes, link card) |
| violet | `#7C3AED` | gradient end, note card |
| ink blue | `#2C6BFF` | second note gradient |
| lime | `#C9FF4F` | unread dot, "new", highlighters |

Note gradients always start bright and end dark so white text keeps ≥ 4.5:1.

**Typography** — display: **Bricolage Grotesque 800** (31 / 28 / 22 / 17, tracking −0.03em); body: **Nunito 600–800**
(16.5 message, 14.5 copy, 11.5 meta, 10 mono-ish counters). Two families only.

**Screens**

1. **The note** (`/{username}`) — sticker avatar with a taped "ask me anything" label, prompt chips that fill the card,
   paper composer with emoji quick-row, one loud pink send button, privacy line underneath.
2. **Sent, as guest** — card pops in, emoji confetti, "she sees you as 👻 Anonymous #12", receipt strip, sign-in offered
   as an upgrade plus three "what happens next" chips.
3. **Inbox** — three tilted gradient stickers with swipe-left-to-hide, lime pulsing unread dot, pill tab bar.
4. **Chat (guest locked)** — paper-theirs / candy-mine bubbles, tap-to-react chip, typing dots, dashed "still a guest"
   card that unlocks live when they sign in.
5. **Your link** — oversized gradient link sticker with QR inside it, copy + share, story-card preview, playful empty state.

**Motion & micro-interactions**

- **The press:** every button sits on a 4–6px hard ink shadow; touch → drops 5px and loses the shadow in 120ms, release
  → springs back at 220ms with slight overshoot.
- **Send = celebration:** 12 emoji fly out on random arcs, the card scales 0.96 → 1.04 → 1, receipt slides up 200ms later.
  Capped at 900ms, skipped for `prefers-reduced-motion`.
- **Swipe the stack:** notes follow the finger, snap open past 72px to a full-card "hide 🙈", snap back otherwise.
- **Stickers are alive:** avatar floats ±5px on a 4.5s loop, sparkles wiggle out of phase, tape label sits at 7°.
- **Tap-to-react:** reaction chip pops in with 1.25 overshoot; long-press and the ⋮ menu both reach block/report.

**Signature touches.** Tilted tape-stuck cards; a QR printed *inside* the sticker so a screenshot still works; count
copy written like a friend ("0 notes yet — the first one always feels weird. then it's fun.").

**Watch out.** Bright gradients invite low contrast — every gradient needs a dark end-stop. Too many loops read as cheap;
one celebration per action. Tone the tilts down to ≤0.5° on desktop or it looks like a broken grid.

---

## Direction B — After Hours

**Concept.** The same playful product lit from below. Deep ink canvas, aurora bleed behind glass notes with a neon edge,
mono labels like a private radio channel — and one gesture you'll remember: **hold to whisper**. This is the version you
open at 1am when the good messages happen.

**Palette**

| Token | Hex | Use |
|---|---|---|
| ink | `#07060C` | canvas |
| panel | `#0D0A17` | raised surfaces, tab bar |
| violet | `#8B5CFF` | action, glow, own bubbles |
| violet deep | `#5426E8` / `#6D3BFF` | button fill (keeps white ≥ 4.5:1) |
| hot pink | `#FF4FA3` | anonymous sender accent, badges |
| cyan | `#35F0D0` | live state, unread LED, hold ring, QR |
| text | `#F4F1FF` | primary text (17.8:1 on ink) |
| muted | `#A49BC4` | secondary text (7.4:1) |
| dim | `#7C739C` | mono labels only |

Rule: one accent per moment — cyan = live/private, violet = action, pink = anonymous.

**Typography** — display: **Space Grotesk 600** (30 / 28 / 20 / 15, tracking −0.04em); body: **Inter 400–600**
(16 message, 13.5 copy); labels: **JetBrains Mono 400** (9–11px, uppercase, 0.14em — the "receipt" voice).

**Screens**

1. **The drop** — receiver becomes a glowing orb instead of a photo; prompt chips; glass composer with text/whisper mode;
   **hold-to-whisper** ring; privacy strip explaining who can and can't be identified.
2. **Signal sent** — pulse rings, mono receipt (trace `4F2A`, delivered, account: none needed), "open a channel" as the
   sign-in upgrade.
3. **Signals (inbox)** — dense rows, not cards: cyan LED for unread, pink "anonymous" badge, filter pills, tab bar.
4. **The channel (chat)** — glass-theirs / violet-mine, voice-note bubble with live waveform, typing dots, mic composer.
5. **Your frequency** — beacon card with glowing QR, live counters, story card, quick-share row.

**Motion & micro-interactions**

- **Hold to whisper:** ring fills over 700ms (conic gradient), core squashes to 0.92, haptic tick at 50%, release early
  → snaps back with 1.06 overshoot and sends nothing. A tap can never post a confession.
- **Aurora drift:** two blurred orbs drift 6–10px over 12s; the only continuous animation, always behind a solid panel.
- **The receipt:** mono rows stagger 80ms apart, trace code types itself in 240ms, single success haptic. No confetti —
  the calm is the reward at this hour.
- **Bubbles with weight:** theirs rises 12px at 0.96 scale; yours enters from the right at 1.02. Read state shifts cyan → white.
- **Live LED:** breathes every 2.2s; opening a thread drains it over 300ms in sync with the realtime read receipt.

**Signature touches.** The orb (no photo on the send screen), 4-character trace codes as a comfort object, mono
"channel open / trace" language that makes anonymity feel logged rather than shady.

**Watch out.** Glow fatigue — reserve glow for live state, the send action and the beacon. Hold-only sending is unusual:
keep the visible "hold to send" label, and let text mode still offer a plain send button. Backdrop blur is expensive on
low-end Android — cap at two blurred layers and drop them under 360px.

---

## Side by side

| | A · Sticker Shop | B · After Hours |
|---|---|---|
| First impression | friendly, funny, safe to be silly | intimate, quiet, "only you" |
| Best for | story sharing, daytime, broad age range | night use, music/creative crowd, screenshot culture |
| Signature gesture | press + swipe | press-and-hold |
| Reward moment | emoji confetti | mono receipt + pulse |
| Contrast risk | gradients vs white text | glow vs muted labels |
| Effort in the Quasar client | CSS tokens + 3 components | tokens + glow layer + hold logic |
| Light/dark | light-first (dark = inverted palette) | dark-first (light = warm paper variant) |

Neither is a rewrite: both keep the guest-first flow, the "Anonymous #N" model, one accent per surface, and the same
screen count. They differ in tokens, shadows, type pairing and the two signature gestures.

**Recommendation.** Build **A** first — it is closer to NGL's daylight energy, cheaper to ship (no blur, no glow, no
timers) and its playfulness lives in layout, which survives on low-end phones. Keep **B**'s hold-to-whisper and trace
codes as an optional "night mode" layer later if the app's audience skews late-night.

## If you pick one

Implementation would be: token block in `client/src/css/app.scss` (plus `quasar.variables.scss` for the accent), then
`ProfilePage`, `MessagesPage`/`ThreadListItem`, `ConversationPane`, `LinkPage`, and one motion composable for press /
confetti / swipe. Copy stays in `client/src/i18n/en-US/index.ts`; new strings are the prompt chips, the next-step chips,
the receipt lines and the hold label.
