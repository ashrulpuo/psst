# Psst — UI/UX Design Plan

## Principles
1. **Calm, playful, private.** Soft, rounded, uncluttered; the product is about small, brave messages.
2. **Fast = beautiful.** Perceived speed is a design goal (optimistic send, skeletons only >1s, instant navigation).
3. **One obvious action per screen.** Verb-first CTAs ("Send message", "Copy my link").
4. **Safety is visible.** Block/report always reachable in one tap; privacy model explained in plain words.
5. **Same feel everywhere** (web, mobile, desktop) with platform-appropriate chrome.

## Visual system (tokens, not ad-hoc values)
- **Spacing:** 4/8pt scale. **Radius:** 12 (inputs/buttons), 20 (cards), full (avatars/bubbles).
- **Type:** one family (Inter or Geist), 5 sizes max (12/14/16/20/28), 2 weights (400/600). Body ≥16px on mobile.
- **Color:** 60-30-10. Neutral surfaces (60), subtle tinted panels (30), one brand accent (10). Accent TBD — candidate: warm violet→pink gradient used *only* on the primary CTA and logo. Real dark mode (own palette, not inverted); depth via borders first, soft shadows second.
- **Motion:** 150–250ms, ease-out; animate only transform/opacity; honor `prefers-reduced-motion`.
- **Icons:** single set (Lucide), icon-only buttons need tooltip + aria-label.
- **Anti-slop:** no glassmorphism, no rainbow buttons, no cartoon empty states.

## Key screens
**Public link page `/{username}`** (sender's first impression, must load <1s on mobile in an in-app browser)
- Receiver avatar + name, one prompt line, big textarea, "Send anonymously" button, one-line privacy note ("Only admins can see who you are, never them" — wording to be legally reviewed).
- Not signed in → "Continue with Google / Apple" inline, then returns to the composer with draft preserved.

**Inbox** — list of threads: "Anonymous #N", last message preview, time, unread dot. Empty state: "Share your link to get your first message" + Copy link / Share buttons.
**Chat** — bubbles, optimistic send, typing indicator, day separators, long-press/⋯ → Block, Report. Reachable composer, safe-area aware.
**My link / Share** — QR code, copy, share sheet, optional shareable image card (for stories).
**Settings** — username, notifications, blocked list, delete account (named destructive confirmation).
**Admin** (separate SPA, dense & utilitarian, still on the same tokens) — dashboard (signups/day, messages/day, open reports), users table, thread viewer with real sender/receiver, reports queue, audit log.

## UX rules
- Blur-first validation, errors never blame the user and always offer a next step.
- Inline edits over modals; destructive actions name what they destroy.
- Loaders: none <100ms, inline <1s, skeletons beyond. Zero layout shift.
- Full keyboard access, visible focus rings, WCAG AA contrast, 44px touch targets, works 375→1280px with no horizontal scroll.
- Copy tone: warm, short, human. Localization-ready (i18n keys from day one; Malay + English first).

## Stack for UI
Quasar (Vue 3 + TS) with a custom token layer (CSS variables) overriding Quasar defaults so it doesn't look like stock Material. Admin: Vue 3 + Tailwind v4 + shadcn-vue. Design in Figma-free workflow: build a small component gallery (Histoire/Storybook) first, then screens.
