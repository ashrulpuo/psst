# Sticker Shop Reskin + Hide & Reactions — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the Psst app into the approved "Sticker Shop" direction — cream paper canvas, candy-gradient note stickers, chunky press-down buttons, emoji celebration — and add the two interactions the mockup promised: **hide a note** (per-user) and **react to a message** (both participants).

**Architecture:** A token layer (`client/src/css/app.scss`) drives the look; four small client units (`AppButton`, `PromptChips`, `celebrate()`, `useSwipe()`) carry the feel. Two new server concepts back the interactions: `thread_hides` (per-user, a new message restores the thread) and `message_reactions` (toggle per participant with a whitelisted emoji). Screens are restyled in place so routes, guards, stores and e2e selectors keep working.

**Tech Stack:** Quasar 2 + Vue 3 `<script setup>` + TypeScript, SCSS tokens, Pinia, vue-i18n, Lucide; Laravel 12 + PHP 8.3, PostgreSQL 16, PHPUnit feature tests; Playwright-core e2e.

**Spec:** [`docs/mockups/README.md`](../../mockups/README.md) (written spec) and [`docs/mockups/a-sticker-shop.html`](../../mockups/a-sticker-shop.html) (the rendered mockup).

---

## Global Constraints

- **The flow is frozen.** `/link` → public `/{username}` → `POST /profiles/{username}/messages` → `/sent` → `/inbox` → `/inbox/{threadId}`. Same routes, guards and redirects.
- **Anonymity is the product.** Nothing added by this plan may expose a sender identity to the receiver. Reactions and hides are keyed by `user_id`; never include sender fields in `InboxThreadResource` or receiver-facing payloads.
- **Tokens only.** Every colour, radius, shadow and font size in components comes from a CSS custom property in `app.scss`. Literal hex is allowed only inside gradient stops and celebration glyphs.
- **Accessibility is not optional.** 44×44 minimum targets, visible `:focus-visible`, AA contrast (4.5:1 body / 3:1 large), every gesture has a non-gesture equivalent, all loops and celebration stop under `prefers-reduced-motion: reduce`.
- **One column.** The app is `min(100%, 430px)` at every width. No breakpoint-driven second layout.
- **Copy lives in** `client/src/i18n/en-US/index.ts`.
- **e2e is the regression suite.** Keep the accessible names: `Send anonymously`, `Sign in to chat`, `Conversation options`, `Block sender`, `Your link`, `Create my link`, `Sign out`. `No messages yet` changes deliberately to `No notes yet` in Task 11 — update the one assertion there.
- **Server tests are the API contract.** Every endpoint added here gets a feature test in `server/tests/Feature/Messaging/`. `php artisan test` must stay green (150 tests today, more after).
- **Migrations are additive and reversible.** New tables only; no column drops, no backfills. `down()` must drop what `up()` created.
- **Display font:** `@fontsource-variable/bricolage-grotesque`. If `pnpm add` cannot reach the registry, stop and report — do not substitute silently.
- **Verification commands** — client: `pnpm typecheck`, `pnpm lint:check`, `pnpm build`; server: `php artisan test`; e2e needs the API on `:8000` (seeded), Reverb, `queue:work` and `pnpm dev` on `:9000`.
- **Local environment baseline (2026-10-28).** `client/node_modules` is missing `electron` and `@capacitor/*`, so `pnpm typecheck` reports 23 pre-existing errors and `oxlint` 27, all in `src-electron/` and `src/native/capacitor/`. No task may add errors there. Treat **`pnpm build` + `oxfmt --check`** as the client gate, and confirm `pnpm typecheck`/`oxlint` error counts do not grow beyond 23/27. `pnpm add` cannot reach the global store (`EPERM` on `/Volumes/ashrul/.pnpm-store`): install packages by declaring them in `package.json`/`pnpm-lock.yaml` and extracting the registry tarball into `node_modules`, or run `pnpm install --store-dir <tmp>`.

### Contrast-safe gradients (decided here, do not re-invent)

White body text must keep 4.5:1, so sticker gradients are darker than the illustrative mockup:

| Token | Value | White-text ratio (worst stop) |
|---|---|---|
| `--grad-pink` | `linear-gradient(150deg, #d6004f, #7c3aed)` | 5.5 : 1 |
| `--grad-ocean` | `linear-gradient(150deg, #1d4ed8, #0f766e)` | 5.4 : 1 |
| `--grad-sunset` | `linear-gradient(150deg, #c2410c, #9d174d)` | 5.1 : 1 |

Button fill under white text is `--accent-strong: #d6004f` (5.5:1). `--accent: #ff2d6f` is decoration and large type only (3.6:1).

### Product rules for the two new interactions

| | Rule |
|---|---|
| **Hide** | Per user, per thread. Hiding removes the thread from that user's list only. Any new message in the thread restores it for both participants. Hide never blocks anyone — blocking stays the receiver-only action in the chat ⋮ menu. |
| **React** | Both participants can react to any message in their thread. Toggling the same emoji by the same person removes it. Emoji are whitelisted (`🔥 ❤️ 😂 👍 😮 😢`); anything else is a 422. Guests (no account) cannot react. Reactions arrive with messages; the other participant sees them on the next poll/refresh (no realtime push in this scope). |

### Deliberately out of scope

- **Realtime reaction push** — reactions ride the message payload and the existing 6–30s poll; a dedicated broadcast event is a follow-up.
- **Story image export**, voice whispers, typing indicators.

---

## File Structure

**Server — create**

| File | Responsibility |
|---|---|
| `server/database/migrations/2026_10_28_000013_create_thread_hides_table.php` | Per-user hide state |
| `server/database/migrations/2026_10_28_000014_create_message_reactions_table.php` | Reactions |
| `server/app/Models/ThreadHide.php`, `MessageReaction.php` | Models |
| `server/app/Actions/Messaging/HideThread.php`, `ToggleReaction.php` | Write paths |
| `server/app/Http/Controllers/Threads/ReactionController.php` | Toggle endpoint |
| `server/app/Http/Requests/ReactionRequest.php` | Emoji whitelist |
| `server/tests/Feature/Messaging/HideThreadTest.php`, `ReactionTest.php` | Contracts |

**Server — modify**

`routes/api.php`, `app/Models/Thread.php`, `app/Models/Message.php`, `app/Http/Controllers/Threads/ThreadStateController.php`, `app/Http/Controllers/Threads/ThreadListController.php`, `app/Http/Controllers/Threads/ThreadMessageController.php`, `app/Http/Resources/MessageResource.php`, `app/Actions/Messaging/PostMessage.php`, `app/Policies/MessagePolicy.php`, `app/Realtime/Broadcasts.php`.

**Client — create**

| File | Responsibility |
|---|---|
| `client/src/components/AppButton.vue` | The press: primary/ghost/lime, optional `to` |
| `client/src/components/PromptChips.vue` | Prompt chips that seed the composer |
| `client/src/components/ReactionBar.vue` | Emoji picker + reaction chips for one message |
| `client/src/utils/celebrate.ts` | Emoji burst + haptic, reduced-motion aware |
| `client/src/composables/useSwipe.ts` | Pointer swipe with threshold and vertical guard |

**Client — modify**

`api/types.ts`, `api/endpoints.ts`, `stores/messaging.ts`, `css/app.scss`, `boot/fonts.ts`, `pages/{Profile,Messages,Link,Settings,Login,GetLink,Welcome}Page.vue`, `components/{ThreadListItem,MessageBubble,MessageComposer,EmptyState}.vue`, `features/messaging/ConversationPane.vue`, `layouts/{AppLayout,AuthLayout}.vue`, `i18n/en-US/index.ts`, `e2e/{smoke,flow-shots}.mjs`, `package.json`.

---

### Task 1: Design tokens, display font, motion primitives

**Files:**
- Modify: `client/package.json`, `client/src/boot/fonts.ts`, `client/src/css/app.scss`, `client/src/css/quasar.variables.scss`

**Interfaces:**
- Produces: CSS custom properties consumed by every later task — `--canvas`, `--surface`, `--surface-hover`, `--text`, `--text-2`, `--text-3`, `--border`, `--border-strong`, `--accent`, `--accent-strong`, `--accent-hover`, `--on-accent`, `--accent-text`, `--accent-soft`, `--lime`, `--violet`, `--edge`, `--edge-shadow`, `--edge-shadow-sm`, `--grad-pink`, `--grad-ocean`, `--grad-sunset`, `--r-control`, `--r-card`, `--r-sticker`, `--font-display`, `--font-sans`, `--nav-height`.

- [ ] **Step 1: Add the display font**

```bash
cd client && pnpm add @fontsource-variable/bricolage-grotesque
```

Expected: `package.json` gains the dependency. If the registry is unreachable, stop and report.

- [ ] **Step 2: Import it**

`client/src/boot/fonts.ts`:

```ts
import { defineBoot } from "#q-app";
// Self-hosted variable fonts; unicode-range splits mean only the needed subset is downloaded.
import "@fontsource-variable/inter/wght.css";
import "@fontsource-variable/nunito/wght.css";
import "@fontsource-variable/bricolage-grotesque/wght.css";

export default defineBoot(() => {});
```

- [ ] **Step 3: Replace the token block in `client/src/css/app.scss`**

Keep the file's comment header and every rule below the token block; replace only `:root` and `.body--dark`.

```scss
:root {
  --safe-top: 0px;
  --font-sans:
    "Inter Variable", ui-sans-serif, system-ui, -apple-system, "Segoe UI",
    Roboto, sans-serif;
  /* Sticker words. Round, heavy, a little loud. */
  --font-display:
    "Bricolage Grotesque Variable", "Nunito Variable", "Avenir Next",
    var(--font-sans);

  --ink: #1b1024;
  --edge: #1b1024; /* hard offset shadow */
  --paper: #fff6ee;
  --cream: #ffe9d6;

  --canvas: var(--paper);
  --surface: #ffffff;
  --surface-hover: #ffeedd;
  --text: #1b1024;
  --text-2: #4a3552;
  --text-3: #7a6782;
  --border: rgba(27, 16, 36, 0.1);
  --border-strong: rgba(27, 16, 36, 0.22);
  --shadow-soft: 0 1px 3px rgba(23, 23, 28, 0.06);

  --accent: #ff2d6f; /* decoration + large type only */
  --accent-strong: #d6004f; /* fills under white text */
  --accent-hover: #e8175c;
  --on-accent: #ffffff;
  --accent-text: #c2004a;
  --accent-soft: rgba(255, 45, 111, 0.1);
  --danger: #c62828;
  --lime: #c9ff4f;
  --violet: #7c3aed;
  --blue: #2c6bff;
  --tangerine: #ff8a3d;

  --bubble-theirs: #ffffff;

  --grad-pink: linear-gradient(150deg, #d6004f 0%, #7c3aed 100%);
  --grad-ocean: linear-gradient(150deg, #1d4ed8 0%, #0f766e 100%);
  --grad-sunset: linear-gradient(150deg, #c2410c 0%, #9d174d 100%);

  --edge-shadow: 0 6px 0 var(--edge);
  --edge-shadow-sm: 0 4px 0 var(--edge);

  --s-1: 0.25rem;
  --s-2: 0.5rem;
  --s-3: 0.75rem;
  --s-4: 1rem;
  --s-5: 1.5rem;
  --s-6: 2rem;
  --s-7: 3rem;

  --r-control: 15px;
  --r-card: 24px;
  --r-sticker: 20px;

  --text-meta: 0.75rem;
  --text-secondary: 0.875rem;
  --text-body: 1rem;
  --text-h3: 1.25rem;
  --text-h2: 1.5rem;
  --text-h1: 2rem;

  --vp-narrow: 375px;
  --vp-medium: 768px;
  --vp-wide: 1280px;

  --ease: cubic-bezier(0.2, 0, 0, 1);
  --ease-pop: cubic-bezier(0.2, 1.3, 0.3, 1);
  --dur: 160ms;

  --nav-height: 84px;
  --rail-width: 88px;
}

.body--dark {
  --ink: #f6f1ff;
  --edge: #000000;
  --paper: #14101c;
  --cream: #1e1729;

  --canvas: #14101c;
  --surface: #1d1728;
  --surface-hover: #261e35;
  --text: #f6f1ff;
  --text-2: #cfc3e6;
  --text-3: #a294c2;
  --border: rgba(255, 255, 255, 0.12);
  --border-strong: rgba(255, 255, 255, 0.26);
  --shadow-soft: inset 0 1px 0 rgba(255, 255, 255, 0.05);

  --accent: #ff5c91;
  --accent-strong: #d6004f;
  --accent-hover: #ff75a3;
  --on-accent: #ffffff;
  --accent-text: #ff9cc0;
  --accent-soft: rgba(255, 92, 145, 0.16);
  --danger: #ff8a8a;

  --bubble-theirs: #241d33;
}
```

- [ ] **Step 4: Update base rules and append the press + celebration utilities**

In the same file: `body` keeps `background: var(--canvas)`; headings become `font-weight: 800; letter-spacing: -0.03em`; the desk background in `@media (min-width: 480px)` becomes `#efe3d8` (dark: `#07060c`). Then append:

```scss
/* The press: every chunky control sits on a hard edge and drops onto the page. */
.press {
  border: 2.5px solid var(--edge);
  border-radius: var(--r-sticker);
  box-shadow: var(--edge-shadow-sm);
  transition:
    transform 120ms var(--ease),
    box-shadow 120ms var(--ease),
    background-color var(--dur) var(--ease);
}
.press:active {
  transform: translateY(4px);
  box-shadow: 0 0 0 var(--edge);
}

/* Celebration particles are created imperatively by utils/celebrate.ts. */
.celebrate-host {
  position: relative;
}
.celebrate-bit {
  position: absolute;
  z-index: 8;
  font-size: 20px;
  line-height: 1;
  pointer-events: none;
  animation: psst-confetti 900ms cubic-bezier(0.2, 0.7, 0.3, 1) forwards;
}

@keyframes psst-confetti {
  from {
    opacity: 1;
    transform: translate(0, 0) rotate(0deg) scale(0.4);
  }
  to {
    opacity: 0;
    transform: translate(var(--dx), var(--dy)) rotate(var(--rot)) scale(1);
  }
}
@media (prefers-reduced-motion: reduce) {
  .press {
    transition: none;
  }
  .press:active {
    transform: none;
  }
  .celebrate-bit {
    display: none;
  }
}
```

- [ ] **Step 5: Point Quasar at the new accent**

`client/src/css/quasar.variables.scss`:

```scss
$primary: #d6004f;
$secondary: #7c3aed;
$accent: #ff2d6f;

$dark: #1d1728;
$dark-page: #14101c;

$positive: #1b8a4b;
$negative: #c62828;
$info: #2c6bff;
$warning: #b26a00;
```

- [ ] **Step 6: Verify the build**

```bash
cd client && pnpm typecheck && pnpm lint:check && pnpm build
```

Expected: all pass. Then `pnpm dev` → `http://localhost:9000/login`: cream canvas, Bricolage headings, no washed-out pink button with white text (that would mean a component still hard-codes the old `#5b4bf0`).

- [ ] **Step 7: Commit**

```bash
git add client/package.json client/pnpm-lock.yaml client/src/boot/fonts.ts client/src/css/app.scss client/src/css/quasar.variables.scss
git commit -m "feat(ui): sticker shop tokens, display font and press primitives"
```

---

### Task 2: The press — AppButton + PromptChips on the public send page

**Files:**
- Create: `client/src/components/AppButton.vue`, `client/src/components/PromptChips.vue`
- Modify: `client/src/pages/ProfilePage.vue`, `client/src/i18n/en-US/index.ts`

**Interfaces:**
- Produces: `AppButton` props `{ variant?: "primary" | "ghost" | "lime"; size?: "md" | "sm"; block?: boolean; to?: string; type?: "button" | "submit"; disabled?: boolean }`, default slot.
- Produces: `PromptChips` props `{ prompts: string[] }`, emits `pick: [text: string]`.
- Produces (i18n): `profile.promptsLabel`, `profile.prompts` (array of four), `profile.privacyShort`, `profile.emojiLabel`.

- [ ] **Step 1: Create `AppButton.vue`**

```vue
<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    variant?: "primary" | "ghost" | "lime";
    size?: "md" | "sm";
    block?: boolean;
    to?: string;
    type?: "button" | "submit";
    disabled?: boolean;
  }>(),
  {
    variant: "primary",
    size: "md",
    block: false,
    type: "button",
    disabled: false
  }
);

const tag = computed(() => (props.to ? "router-link" : "button"));
</script>

<template>
  <component
    :is="tag"
    class="app-btn press"
    :class="[`is-${variant}`, `is-${size}`, { 'is-block': block }]"
    :to="to"
    :type="to ? undefined : type"
    :disabled="to ? undefined : disabled"
  >
    <slot />
  </component>
</template>

<style scoped lang="scss">
.app-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 52px;
  padding: 0 20px;
  border-radius: var(--r-sticker);
  font-family: var(--font-display);
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.01em;
  text-decoration: none;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}
.app-btn.is-md {
  min-height: 56px;
  font-size: 1.12rem;
}
.app-btn.is-sm {
  min-height: 46px;
  font-size: 0.95rem;
  border-width: 2px;
  border-radius: var(--r-control);
}
.app-btn.is-primary {
  background: var(--accent-strong);
  color: var(--on-accent);
}
.app-btn.is-primary:hover {
  background: var(--accent-hover);
}
.app-btn.is-ghost {
  background: var(--surface);
  color: var(--text);
}
.app-btn.is-ghost:hover {
  background: var(--surface-hover);
}
.app-btn.is-lime {
  background: var(--lime);
  color: var(--ink);
}
.app-btn.is-block {
  width: 100%;
}
.app-btn[disabled] {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}
.app-btn:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 3px;
}
</style>
```

- [ ] **Step 2: Create `PromptChips.vue`**

```vue
<script setup lang="ts">
defineProps<{ prompts: string[] }>();
defineEmits<{ pick: [text: string] }>();
</script>

<template>
  <div class="chips" role="group" :aria-label="$t('profile.promptsLabel')">
    <button
      v-for="prompt in prompts"
      :key="prompt"
      type="button"
      class="chip"
      @click="$emit('pick', prompt)"
    >
      {{ prompt }}
    </button>
  </div>
</template>

<style scoped lang="scss">
.chips {
  display: flex;
  gap: 8px;
  width: 100%;
  padding: 2px 22px 6px;
  margin-inline: -22px;
  overflow-x: auto;
  scrollbar-width: none;
}
.chips::-webkit-scrollbar {
  display: none;
}
.chip {
  flex: none;
  min-height: 38px;
  padding: 0 13px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--surface);
  box-shadow: 0 3px 0 var(--edge);
  color: var(--text);
  font: 700 0.8rem / 1 var(--font-sans);
  cursor: pointer;
  transition:
    transform 120ms var(--ease),
    box-shadow 120ms var(--ease),
    background-color var(--dur) var(--ease);
}
.chip:hover {
  background: var(--cream);
}
.chip:active {
  transform: translateY(3px);
  box-shadow: 0 0 0 var(--edge);
}
.chip:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 2px;
}
</style>
```

- [ ] **Step 3: Add the copy**

In `client/src/i18n/en-US/index.ts`, inside `profile`:

```ts
    promptsLabel: "Prompt ideas",
    prompts: [
      "say something nice 💌",
      "confess 🤫",
      "roast me 🔥",
      "a secret 🫣"
    ],
    privacyShort: "They see a nickname. Not your name.",
    emojiLabel: "Add an emoji",
```

- [ ] **Step 4: Wire the send page (`ProfilePage.vue`)**

1. Imports: add `nextTick`, `useTemplateRef` from `vue`; `useI18n` from `vue-i18n`; `AppButton`, `PromptChips`.
2. Script, after `const MAX = 1000;`:

```ts
const { tm, rt } = useI18n();
const prompts = computed(() => (tm("profile.prompts") as string[]).map(rt));
const EMOJI = ["🥰", "😂", "😭", "🤍", "🔥"];

function usePrompt(text: string) {
  if (!body.value.trim()) body.value = `${text} `;
  void nextTick(() => document.getElementById("message")?.focus());
}
function addEmoji(emoji: string) {
  body.value += emoji;
  void nextTick(() => document.getElementById("message")?.focus());
}
```

3. Replace `<header class="hero">…</header>` with:

```vue
    <header class="hero arrive">
      <span class="sticker-avatar">
        <AppAvatar :name="profile.name" :src="profile.avatar_url" :size="88" />
        <span class="tape">{{ $t("profile.prompt") }}</span>
      </span>
      <h1>{{ profile.name }}</h1>
      <p class="prompt-line">{{ $t("profile.subtitle") }}</p>
    </header>

    <PromptChips :prompts="prompts" @pick="usePrompt" />
```

4. Replace the composer `div.field` with:

```vue
    <div class="paper">
      <label class="sr-only" for="message">{{ $t("profile.label") }}</label>
      <textarea
        id="message"
        v-model="body"
        class="input"
        rows="5"
        :aria-invalid="remaining < 0"
        aria-describedby="message-help"
        @keydown.ctrl.enter.prevent="submit"
        @keydown.meta.enter.prevent="submit"
      />
      <div class="paper-foot">
        <div class="emoji-row" role="group" :aria-label="$t('profile.emojiLabel')">
          <button
            v-for="emoji in EMOJI"
            :key="emoji"
            type="button"
            class="emoji"
            @click="addEmoji(emoji)"
          >
            {{ emoji }}
          </button>
        </div>
        <p
          id="message-help"
          class="text-meta help"
          :class="{ over: remaining < 0 }"
        >
          <span v-if="error" class="error" role="alert">{{ error }}</span>
          <span class="count" aria-live="polite">{{ remaining }}</span>
        </p>
      </div>
    </div>
```

5. Replace the submit `q-btn` with:

```vue
    <AppButton type="submit" size="md" block :disabled="!canSend">
      {{ sending ? $t("thread.sending") : $t("profile.send") }}
    </AppButton>
```

6. Replace the bottom link with:

```vue
    <div class="send-foot">
      <p class="tiny">🔒 {{ $t("profile.privacyShort") }}</p>
      <router-link v-if="!auth.isAuthenticated" to="/get-link" class="own-link">
        {{ $t("profile.getYourOwn") }}
      </router-link>
    </div>
```

7. Replace `.hero`, `.ring`, `.send-page .field`, `.ngl`, `.send-page .send` styles with:

```scss
.send-page {
  display: flex;
  flex-direction: column;
  width: min(100%, 340px);
  margin-inline: auto;
  gap: 14px;
  padding-bottom: 12px;
}
.hero {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 6px 0 0;
  text-align: center;
}
.sticker-avatar {
  position: relative;
  display: grid;
  place-items: center;
  padding: 4px;
  border: 4px solid var(--surface);
  border-radius: 50%;
  background: var(--grad-pink);
  box-shadow: 0 8px 0 var(--edge), 0 16px 26px rgba(214, 0, 79, 0.18);
  transform: rotate(-5deg);
}
.tape {
  position: absolute;
  right: -34px;
  bottom: 4px;
  padding: 4px 9px;
  border: 2.5px solid var(--edge);
  border-radius: 7px;
  background: var(--lime);
  box-shadow: 0 3px 0 var(--edge);
  color: var(--ink);
  font: 800 0.62rem / 1 var(--font-display);
  white-space: nowrap;
  transform: rotate(7deg);
}
.hero h1 {
  font-family: var(--font-display);
  font-size: 1.9rem;
  letter-spacing: -0.04em;
}
.prompt-line {
  max-width: 26ch;
  color: var(--text-2);
  font-size: 0.9rem;
}
.paper {
  position: relative;
  padding: 16px 14px 10px;
  border: 2.5px solid var(--edge);
  border-radius: var(--r-card);
  background: var(--surface);
  box-shadow: var(--edge-shadow-sm);
}
.paper .input {
  width: 100%;
  min-height: 108px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--text);
  font: inherit;
  resize: none;
  outline: 0;
}
.paper .input::placeholder {
  color: var(--text-3);
}
.paper-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 2px dashed var(--border);
}
.emoji-row {
  display: flex;
  gap: 2px;
}
.emoji {
  padding: 4px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  font-size: 1.15rem;
  line-height: 1;
  cursor: pointer;
}
.emoji:hover {
  transform: translateY(-2px);
}
.tiny {
  color: var(--text-3);
  font-size: 0.75rem;
  text-align: center;
}
.send-foot {
  display: grid;
  justify-items: center;
  gap: 6px;
  margin-top: auto;
  padding-bottom: 8px;
}
```

- [ ] **Step 5: Verify the send flow**

```bash
cd client && node e2e/guest.mjs
```

Expected: PASS — the script fills `#message`, clicks `Send anonymously`, waits for `text=Sent`. By hand at `http://localhost:9000/alice`: chips fill an empty composer, emoji append, the button presses 4px into the page.

- [ ] **Step 6: Typecheck, lint, commit**

```bash
cd client && pnpm typecheck && pnpm lint:check
git add client/src/components/AppButton.vue client/src/components/PromptChips.vue client/src/pages/ProfilePage.vue client/src/i18n/en-US/index.ts
git commit -m "feat(ui): sticker send page with chunky button and prompt chips"
```

---

### Task 3: Celebration on send

**Files:**
- Create: `client/src/utils/celebrate.ts`
- Modify: `client/src/pages/ProfilePage.vue`

**Interfaces:**
- Produces: `celebrate(host: HTMLElement | null, count?: number): void`, `haptic(ms?: number): void` from `@/utils/celebrate`.
- Consumes: `.celebrate-host` / `.celebrate-bit` / `psst-confetti` from Task 1.

- [ ] **Step 1: Create `celebrate.ts`**

```ts
/**
 * The reward. Twelve emoji leave the button on random arcs and the device ticks,
 * unless the person asked for less motion. Particles remove themselves.
 */
const GLYPHS = ["💌", "✨", "🤍", "🎉", "💫", "🔥"];

function reduced(): boolean {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

export function haptic(ms = 8): void {
  if (typeof navigator !== "undefined" && "vibrate" in navigator) {
    navigator.vibrate(ms);
  }
}

export function celebrate(host: HTMLElement | null, count = 12): void {
  if (!host || reduced()) return;

  host.classList.add("celebrate-host");
  const width = host.offsetWidth;

  for (let i = 0; i < count; i++) {
    const bit = document.createElement("span");
    bit.className = "celebrate-bit";
    bit.textContent = GLYPHS[i % GLYPHS.length] ?? GLYPHS[0]!;
    bit.style.left = `${width / 2}px`;
    bit.style.top = "0px";
    bit.style.setProperty("--dx", `${Math.round(Math.random() * 240 - 120)}px`);
    bit.style.setProperty("--dy", `${Math.round(-90 - Math.random() * 150)}px`);
    bit.style.setProperty("--rot", `${Math.round(Math.random() * 360 - 180)}deg`);
    bit.style.animationDelay = `${i * 18}ms`;
    host.appendChild(bit);
    window.setTimeout(() => bit.remove(), 1100 + i * 18);
  }

  haptic(12);
}
```

- [ ] **Step 2: Fire it when the send succeeds**

In `ProfilePage.vue`, import `celebrate` from `@/utils/celebrate`, then:

```ts
const sentCard = useTemplateRef<HTMLElement>("sentCard");

watch(sentThreadId, id => {
  if (!id) return;
  void nextTick(() => celebrate(sentCard.value));
});
```

Attach the ref:

```vue
  <div
    v-else-if="profile && sentThreadId"
    ref="sentCard"
    class="card done"
    role="status"
  >
```

- [ ] **Step 3: Restyle the done card**

```scss
.card.done {
  position: relative;
  display: grid;
  gap: 10px;
  justify-items: center;
  width: min(100%, 340px);
  margin-inline: auto;
  padding: 26px 22px 22px;
  border: 2.5px solid var(--edge);
  border-radius: 30px;
  background: var(--surface);
  box-shadow: 0 9px 0 var(--edge);
  text-align: center;
  overflow: visible;
}
.card.done h1 {
  font-family: var(--font-display);
  font-size: 2rem;
  letter-spacing: -0.04em;
}
.card.done .who {
  padding: 7px 13px;
  border: 2px dashed var(--border-strong);
  border-radius: 999px;
  background: var(--cream);
  color: var(--ink);
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 0.85rem;
}
```

- [ ] **Step 4: Verify**

```bash
cd client && pnpm typecheck && pnpm lint:check && node e2e/guest.mjs
```

Expected: PASS, and on the seeded app sending shows emoji leaving the card. With macOS Reduce Motion on, the card appears with no emoji.

- [ ] **Step 5: Commit**

```bash
git add client/src/utils/celebrate.ts client/src/pages/ProfilePage.vue
git commit -m "feat(ui): celebrate a sent note with emoji and haptic"
```

---

### Task 4: Server — hide a thread per user

**Files:**
- Create: `server/database/migrations/2026_10_28_000013_create_thread_hides_table.php`, `server/app/Models/ThreadHide.php`, `server/app/Actions/Messaging/HideThread.php`, `server/tests/Feature/Messaging/HideThreadTest.php`
- Modify: `server/routes/api.php`, `server/app/Models/Thread.php`, `server/app/Http/Controllers/Threads/ThreadStateController.php`, `server/app/Http/Controllers/Threads/ThreadListController.php`, `server/app/Actions/Messaging/PostMessage.php`

**Interfaces:**
- Produces: `PUT /api/threads/{thread}/hide`, `DELETE /api/threads/{thread}/hide` (both `204`).
- Produces: `HideThread::hide(Thread $thread, User $user): void`, `HideThread::show(Thread $thread, User $user): void`.
- Produces: `Thread::hides()` relation; inbox/sent exclude threads hidden by the caller; a new message clears every hide for the thread.

- [ ] **Step 1: Write the failing test**

`server/tests/Feature/Messaging/HideThreadTest.php`:

```php
<?php

namespace Tests\Feature\Messaging;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HideThreadTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $sender;

    private Thread $thread;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = User::factory()->create(['username' => 'alice']);
        $this->sender = User::factory()->create(['username' => 'bob']);

        $this->receiver->sendMessage($this->sender, 'hi');
    }

    private function inboxFor(User $user): array
    {
        Sanctum::actingAs($user);

        return $this->getJson('/api/inbox')->assertOk()->json('data');
    }

    public function test_hiding_removes_the_thread_from_only_my_list(): void
    {
        $thread = Thread::query()->sole();

        Sanctum::actingAs($this->receiver);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();

        $this->assertCount(0, $this->inboxFor($this->receiver));
        $this->assertCount(1, $this->inboxFor($this->sender) ?: []);
    }

    public function test_a_new_message_restores_a_hidden_thread(): void
    {
        $thread = Thread::query()->sole();

        Sanctum::actingAs($this->receiver);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();
        $this->assertCount(0, $this->inboxFor($this->receiver));

        Sanctum::actingAs($this->sender);
        $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'back again'])->assertCreated();

        $this->assertCount(1, $this->inboxFor($this->receiver));
    }

    public function test_unhiding_is_idempotent(): void
    {
        $thread = Thread::query()->sole();

        Sanctum::actingAs($this->receiver);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNoContent();
        $this->deleteJson("/api/threads/{$thread->id}/hide")->assertNoContent();
        $this->deleteJson("/api/threads/{$thread->id}/hide")->assertNoContent();

        $this->assertCount(1, $this->inboxFor($this->receiver));
    }

    public function test_a_stranger_cannot_touch_a_thread(): void
    {
        $thread = Thread::query()->sole();
        $stranger = User::factory()->create(['username' => 'eve']);

        Sanctum::actingAs($stranger);
        $this->putJson("/api/threads/{$thread->id}/hide")->assertNotFound();
    }
}
```

The helper `sendMessage` does not exist yet — replace the `setUp()` body with the existing pattern from `MessagingTest`: create users, then `$this->actingAs($this->sender, 'sanctum')->postJson('/api/profiles/alice/messages', ['body' => 'hi']);`. Use `Sanctum::actingAs` before the call.

- [ ] **Step 2: Run it, expect failure**

```bash
cd server && php artisan test --filter=HideThreadTest
```

Expected: FAIL — `404` from the missing route.

- [ ] **Step 3: Migration**

`server/database/migrations/2026_10_28_000013_create_thread_hides_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A hide is per user and per thread: it clears a note out of one person's list and
 * nothing else. Any new message in the thread deletes the hide (see PostMessage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_hides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['thread_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_hides');
    }
};
```

- [ ] **Step 4: Model**

`server/app/Models/ThreadHide.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thread_id', 'user_id'])]
class ThreadHide extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Thread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
```

Add to `server/app/Models/Thread.php`:

```php
    /** @return HasMany<ThreadHide, $this> */
    public function hides(): HasMany
    {
        return $this->hasMany(ThreadHide::class);
    }
```

- [ ] **Step 5: Action**

`server/app/Actions/Messaging/HideThread.php`:

```php
<?php

namespace App\Actions\Messaging;

use App\Models\Thread;
use App\Models\ThreadHide;
use App\Models\User;

class HideThread
{
    public function hide(Thread $thread, User $user): void
    {
        ThreadHide::query()->firstOrCreate([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function show(Thread $thread, User $user): void
    {
        ThreadHide::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->delete();
    }
}
```

- [ ] **Step 6: Routes and controller**

`server/routes/api.php`, inside the `threads/{thread}` group, after `unblock`:

```php
        Route::put('hide', [ThreadStateController::class, 'hide']);
        Route::delete('hide', [ThreadStateController::class, 'unhide']);
```

`ThreadStateController`:

```php
    public function hide(Request $request, Thread $thread, HideThread $hides): Response
    {
        $hides->hide($thread, $request->user());

        return response()->noContent();
    }

    public function unhide(Request $request, Thread $thread, HideThread $hides): Response
    {
        $hides->show($thread, $request->user());

        return response()->noContent();
    }
```

with `use App\Actions\Messaging\HideThread;`.

- [ ] **Step 7: Exclude hidden threads from both lists**

`ThreadListController` — add to *both* queries:

```php
            ->whereDoesntHave('hides', fn ($query) => $query->where('user_id', $request->user()->id))
```

- [ ] **Step 8: A new message un-hides the thread**

In `PostMessage::handle()` (after the message is stored and counters updated, inside the same transaction):

```php
            // A new note pulls the thread back into both inboxes.
            ThreadHide::query()->where('thread_id', $thread->id)->delete();
```

with `use App\Models\ThreadHide;`.

- [ ] **Step 9: Run the tests**

```bash
cd server && php artisan test --filter=HideThreadTest && php artisan test
```

Expected: the new file passes and the whole suite stays green (150+).

- [ ] **Step 10: Commit**

```bash
git add server/database/migrations/2026_10_28_000013_create_thread_hides_table.php server/app/Models/ThreadHide.php server/app/Models/Thread.php server/app/Actions/Messaging/HideThread.php server/app/Actions/Messaging/PostMessage.php server/app/Http/Controllers/Threads/ThreadStateController.php server/app/Http/Controllers/Threads/ThreadListController.php server/routes/api.php server/tests/Feature/Messaging/HideThreadTest.php
git commit -m "feat(messaging): hide a thread per user, restored by the next message"
```

---

### Task 5: Server — message reactions

**Files:**
- Create: `server/database/migrations/2026_10_28_000014_create_message_reactions_table.php`, `server/app/Models/MessageReaction.php`, `server/app/Actions/Messaging/ToggleReaction.php`, `server/app/Http/Controllers/Threads/ReactionController.php`, `server/app/Http/Requests/ReactionRequest.php`, `server/tests/Feature/Messaging/ReactionTest.php`
- Modify: `server/routes/api.php`, `server/app/Models/Message.php`, `server/app/Http/Resources/MessageResource.php`, `server/app/Http/Controllers/Threads/ThreadMessageController.php`, `server/app/Policies/MessagePolicy.php`, `server/app/Realtime/Broadcasts.php`

**Interfaces:**
- Produces: `POST /api/messages/{message}/reactions` body `{ "emoji": "🔥" }` → `200` with `{ "data": MessageResource }` including the fresh `reactions` array.
- Produces: `MessageResource` adds `reactions: [{ emoji: string, count: number, mine: boolean }]`.
- Produces: `ToggleReaction::handle(Message $message, User $user, string $emoji): void`.
- Produces: `Message::reactions()` relation.

- [ ] **Step 1: Write the failing test**

`server/tests/Feature/Messaging/ReactionTest.php`:

```php
<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReactionTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $sender;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = User::factory()->create(['username' => 'alice']);
        $this->sender = User::factory()->create(['username' => 'bob']);

        Sanctum::actingAs($this->sender);
        $this->postJson('/api/profiles/alice/messages', ['body' => 'you were great'])->assertCreated();

        $this->message = Message::query()->sole();
    }

    private function react(User $user, string $emoji = '🔥')
    {
        Sanctum::actingAs($user);

        return $this->postJson("/api/messages/{$this->message->id}/reactions", ['emoji' => $emoji]);
    }

    public function test_the_receiver_reacts_and_sees_it_as_theirs(): void
    {
        $this->react($this->receiver)
            ->assertOk()
            ->assertJsonPath('data.reactions.0.emoji', '🔥')
            ->assertJsonPath('data.reactions.0.count', 1)
            ->assertJsonPath('data.reactions.0.mine', true);
    }

    public function test_reacting_twice_with_the_same_emoji_removes_it(): void
    {
        $this->react($this->receiver)->assertOk();
        $this->react($this->receiver)
            ->assertOk()
            ->assertJsonPath('data.reactions', []);
    }

    public function test_both_participants_count_towards_the_same_emoji(): void
    {
        $this->react($this->receiver, '❤️')->assertOk();
        $this->react($this->sender, '❤️')
            ->assertOk()
            ->assertJsonPath('data.reactions.0.count', 2)
            ->assertJsonPath('data.reactions.0.mine', true);
    }

    public function test_only_whitelisted_emoji_are_accepted(): void
    {
        $this->react($this->receiver, '💩')
            ->assertStatus(422)
            ->assertJsonValidationErrors('emoji');
    }

    public function test_a_stranger_cannot_react(): void
    {
        $stranger = User::factory()->create(['username' => 'eve']);

        $this->react($stranger)->assertNotFound();
    }

    public function test_reactions_ship_with_the_thread_history(): void
    {
        $this->react($this->receiver)->assertOk();

        Sanctum::actingAs($this->receiver);
        $this->getJson('/api/threads/'.Thread::query()->sole()->id.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.reactions.0.emoji', '🔥');
    }
}
```

- [ ] **Step 2: Run it, expect failure**

```bash
cd server && php artisan test --filter=ReactionTest
```

Expected: FAIL — route missing (`404`).

- [ ] **Step 3: Migration and model**

`server/database/migrations/2026_10_28_000014_create_message_reactions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per person per emoji per message: tapping the same emoji again deletes it.
 * Keyed by user_id, never stored with an identity the receiver can read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
```

`server/app/Models/MessageReaction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['message_id', 'user_id', 'emoji'])]
class MessageReaction extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
```

Add to `server/app/Models/Message.php`:

```php
    /** @return HasMany<MessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }
```

with `use Illuminate\Database\Eloquent\Relations\HasMany;`.

- [ ] **Step 4: Request (the whitelist)**

`server/app/Http/Requests/ReactionRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactionRequest extends FormRequest
{
    /** The whole vocabulary. Anything else is rejected so the column stays trustworthy. */
    public const EMOJI = ['🔥', '❤️', '😂', '👍', '😮', '😢'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::in(self::EMOJI)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emoji.in' => 'That reaction is not available.',
        ];
    }
}
```

- [ ] **Step 5: Action**

`server/app/Actions/Messaging/ToggleReaction.php`:

```php
<?php

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\User;

class ToggleReaction
{
    /** Same person, same emoji, same message: the second tap takes it back. */
    public function handle(Message $message, User $user, string $emoji): void
    {
        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();

            return;
        }

        $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);
    }
}
```

- [ ] **Step 6: Authorization**

`server/app/Policies/MessagePolicy.php` (keep the existing `report` ability, add this):

```php
    public function react(User $user, Message $message): bool
    {
        $thread = $message->thread;

        return $thread->receiver_id === $user->id || $thread->sender_id === $user->id;
    }
```

- [ ] **Step 7: Controller and route**

`server/app/Http/Controllers/Threads/ReactionController.php`:

```php
<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Messaging\ToggleReaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReactionRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReactionController extends Controller
{
    public function store(
        ReactionRequest $request,
        Message $message,
        ToggleReaction $toggle
    ): JsonResponse {
        Gate::authorize('react', $message);

        $toggle->handle($message, $request->user(), $request->validated('emoji'));

        MessageResource::viewingAs($message->thread->receiver_id === $request->user()->id);

        return (new MessageResource($message->load('reactions')))->response();
    }
}
```

Route, next to the report route:

```php
    Route::post('messages/{message}/reactions', [ReactionController::class, 'store'])
        ->whereUuid('message')
        ->middleware('throttle:reports');
```

with `use App\Http\Controllers\Threads\ReactionController;`.

- [ ] **Step 8: Put reactions into the payload**

`MessageResource::toArray()` — add:

```php
            'reactions' => $this->whenLoaded('reactions', fn () => $this->reactions
                ->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'mine' => $group->contains('user_id', auth()->id()),
                ])
                ->values()),
```

`ThreadMessageController@index` — eager load:

```php
            $thread->messages()->with('reactions')->orderByDesc('id')->cursorPaginate(self::PAGE_SIZE)
```

`Broadcasts::messagePosted()` — the `'message'` payload gains:

```php
                'reactions' => [],
```

`PostMessage` does not need to load reactions: a brand-new message has none.

- [ ] **Step 9: Run the tests**

```bash
cd server && php artisan test --filter=ReactionTest && php artisan test
```

Expected: new file passes, whole suite green.

- [ ] **Step 10: Commit**

```bash
git add server/database/migrations/2026_10_28_000014_create_message_reactions_table.php server/app/Models/MessageReaction.php server/app/Models/Message.php server/app/Actions/Messaging/ToggleReaction.php server/app/Http/Requests/ReactionRequest.php server/app/Http/Controllers/Threads/ReactionController.php server/app/Http/Resources/MessageResource.php server/app/Http/Controllers/Threads/ThreadMessageController.php server/app/Policies/MessagePolicy.php server/app/Realtime/Broadcasts.php server/routes/api.php server/tests/Feature/Messaging/ReactionTest.php
git commit -m "feat(messaging): toggle an emoji reaction on a message"
```

---

### Task 6: Client data layer — hide + reactions

**Files:**
- Modify: `client/src/api/types.ts`, `client/src/api/endpoints.ts`, `client/src/stores/messaging.ts`

**Interfaces:**
- Produces: `type Reaction = { emoji: string; count: number; mine: boolean }`; `Message.reactions: Reaction[]`.
- Produces: `threadsApi.hide(id)`, `threadsApi.unhide(id)`, `messagesApi.react(id, emoji)`.
- Produces: `messaging.hideThread(id): Promise<void>`, `messaging.toggleReaction(threadId, messageId, emoji): Promise<void>`.

- [ ] **Step 1: Types**

```ts
export interface Reaction {
  emoji: string;
  count: number;
  mine: boolean;
}

export interface Message {
  id: string;
  body: string;
  mine: boolean;
  read_at: string | null;
  created_at: string;
  reactions: Reaction[];
  // keep the existing optimistic-only fields: pending?, failed?
}
```

- [ ] **Step 2: Endpoints**

```ts
export const threadsApi = {
  // existing entries…
  hide: (id: string) => http.put<void>(`/threads/${id}/hide`),
  unhide: (id: string) => http.delete<void>(`/threads/${id}/hide`)
};

export const messagesApi = {
  // existing report…
  react: (id: string, emoji: string) =>
    http.post<{ data: Message }>(`/messages/${id}/reactions`, { emoji })
};
```

Match the file's existing `http` helper shape exactly (read it before editing; `http.post` may be `post<T>(url, body)` returning `data`).

- [ ] **Step 3: Store**

```ts
async function hideThread(threadId: string) {
  // Optimistic: drop it from the list now, restore it if the API says no.
  const index = inbox.items.findIndex(thread => thread.id === threadId);
  const removed = index === -1 ? null : inbox.items.splice(index, 1)[0] ?? null;
  try {
    await threadsApi.hide(threadId);
  } catch (error) {
    if (removed && index !== -1) inbox.items.splice(index, 0, removed);
    throw error;
  }
}

async function toggleReaction(threadId: string, messageId: string, emoji: string) {
  const list = messages[threadId] ?? [];
  const target = list.find(message => message.id === messageId);
  if (!target) return;

  const before = target.reactions.map(reaction => ({ ...reaction }));
  const existing = target.reactions.find(reaction => reaction.emoji === emoji);
  if (existing) {
    existing.count += existing.mine ? -1 : 1;
    existing.mine = !existing.mine;
    if (existing.count <= 0) {
      target.reactions = target.reactions.filter(r => r.emoji !== emoji);
    }
  } else {
    target.reactions.push({ emoji, count: 1, mine: true });
  }

  try {
    const { data } = await messagesApi.react(messageId, emoji);
    target.reactions = data.reactions;
  } catch (error) {
    target.reactions = before;
    throw error;
  }
}
```

Export both from the store's return object.

- [ ] **Step 4: Verify**

```bash
cd client && pnpm typecheck && pnpm lint:check
```

Expected: pass. (No UI consumes these yet — Task 7 and 8 do.)

- [ ] **Step 5: Commit**

```bash
git add client/src/api/types.ts client/src/api/endpoints.ts client/src/stores/messaging.ts
git commit -m "feat(client): api + store support for hiding threads and reactions"
```

---

### Task 7: Inbox — sticker cards, swipe-to-hide, header

**Files:**
- Create: `client/src/composables/useSwipe.ts`
- Modify: `client/src/components/ThreadListItem.vue`, `client/src/pages/MessagesPage.vue`, `client/src/i18n/en-US/index.ts`, `client/e2e/smoke.mjs`

**Interfaces:**
- Produces: `useSwipe(opts?: { threshold?: number; max?: number })` → `{ offset: Ref<number>, open: Ref<boolean>, close(): void, handlers: { pointerdown, pointermove, pointerup, pointercancel } }`.
- Produces: `ThreadListItem` gains `canHide?: boolean` and emit `hide: [thread: InboxThread | SentThread]`.
- Consumes: `storyGradient(id)`, `messaging.hideThread(id)`, `AppButton`.

- [ ] **Step 1: Create `useSwipe.ts`**

```ts
import { ref } from "vue";

/**
 * One-axis swipe. Vertical intent wins immediately so scrolling never fights the card.
 * The gesture is an accelerator: the same action always has a focusable button.
 */
export function useSwipe(opts: { threshold?: number; max?: number } = {}) {
  const threshold = opts.threshold ?? 72;
  const max = opts.max ?? 110;

  const offset = ref(0);
  const open = ref(false);
  let startX: number | null = null;
  let startY = 0;
  let vertical = false;

  function close() {
    open.value = false;
    offset.value = 0;
  }

  function pointerdown(event: PointerEvent) {
    if (open.value) {
      close();
      return;
    }
    startX = event.clientX;
    startY = event.clientY;
    vertical = false;
    (event.currentTarget as HTMLElement | null)?.setPointerCapture?.(
      event.pointerId
    );
  }

  function pointermove(event: PointerEvent) {
    if (startX === null) return;
    const dx = event.clientX - startX;
    const dy = event.clientY - startY;
    if (!vertical && Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 8) {
      vertical = true;
      offset.value = 0;
      return;
    }
    if (vertical) return;
    offset.value = Math.max(-max, Math.min(0, dx));
  }

  function settle() {
    if (startX === null) return;
    startX = null;
    open.value = offset.value < -threshold;
    offset.value = open.value ? -max : 0;
  }

  return {
    offset,
    open,
    close,
    handlers: {
      pointerdown,
      pointermove,
      pointerup: settle,
      pointercancel: settle
    }
  };
}
```

- [ ] **Step 2: Rewrite `ThreadListItem.vue`**

```vue
<script setup lang="ts">
import { computed } from "vue";
import { ChevronRight, Heart, Send } from "@lucide/vue";
import type { InboxThread, SentThread } from "@/api/types";
import { shortTime } from "@/utils/format";
import { storyGradient } from "@/utils/cardColor";
import { useSwipe } from "@/composables/useSwipe";

const props = withDefaults(
  defineProps<{
    thread: InboxThread | SentThread;
    box: "inbox" | "sent";
    active: boolean;
    canHide?: boolean;
  }>(),
  { canHide: false }
);
const emit = defineEmits<{ hide: [thread: InboxThread | SentThread] }>();

const words = computed(() => props.thread.last_message?.body ?? "");
const title = computed(() =>
  "receiver" in props.thread ? props.thread.receiver.name : props.thread.label
);
const unread = computed(() => props.thread.unread_count > 0);
const swipe = useSwipe();

function onCardClick(event: MouseEvent) {
  if (Math.abs(swipe.offset.value) > 8 || swipe.open.value) {
    event.preventDefault();
    swipe.close();
  }
}
</script>

<template>
  <div class="wrap" :class="{ open: swipe.open }">
    <button
      v-if="canHide"
      type="button"
      class="reveal"
      @click="emit('hide', thread)"
    >
      {{ $t("thread.hide") }}
    </button>

    <router-link
      :to="`/${box}/${thread.id}`"
      class="note"
      :class="{ 'is-unread': unread }"
      :style="{ '--x': `${swipe.offset}px`, '--card': storyGradient(thread.id) }"
      :aria-current="active ? 'page' : undefined"
      @click="onCardClick"
      v-on="swipe.handlers"
    >
      <span class="mark" aria-hidden="true">
        <Heart v-if="box === 'inbox'" :size="15" />
        <Send v-else :size="15" />
      </span>
      <span class="words">{{ words }}</span>
      <span class="sub">
        <span class="who">{{ title }}</span>
        <time :datetime="thread.last_message_at ?? undefined">{{
          shortTime(thread.last_message_at)
        }}</time>
        <span v-if="unread" class="new" aria-hidden="true" />
      </span>
      <ChevronRight class="chev" :size="16" aria-hidden="true" />
      <span v-if="unread" class="sr-only">{{ unread }} unread</span>
    </router-link>
  </div>
</template>

<style scoped lang="scss">
.wrap {
  position: relative;
  /* 5px of breathing room so the card's hard edge is not clipped by the reveal mask. */
  padding-bottom: 5px;
  overflow: hidden;
  border-radius: var(--r-card);
  touch-action: pan-y;
}
.reveal {
  position: absolute;
  inset: 0 0 5px auto;
  width: 112px;
  border: 0;
  border-radius: var(--r-card);
  background: var(--ink);
  color: var(--canvas);
  font: 800 0.8rem / 1.2 var(--font-display);
  cursor: pointer;
}
/* Keyboard reachable: focusing the hidden button slides the card open. */
.wrap:focus-within .note {
  transform: translateX(-112px);
}
.note {
  position: relative;
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr) 16px;
  gap: 10px;
  align-items: start;
  padding: 15px 14px 13px;
  border: 2.5px solid var(--edge);
  border-radius: var(--r-card);
  background: var(--card);
  box-shadow: var(--edge-shadow-sm);
  color: #fff;
  text-decoration: none;
  transform: translateX(var(--x));
  transition: transform 180ms var(--ease-pop);
}
.wrap.open .note {
  transform: translateX(-112px);
}
.note:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 3px;
}
.mark {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.22);
}
.words {
  display: -webkit-box;
  overflow: hidden;
  font-size: 0.97rem;
  font-weight: 700;
  line-height: 1.4;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.is-unread .words {
  font-weight: 800;
}
.sub {
  grid-column: 2 / span 2;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  font-size: 0.72rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.86);
}
.who {
  font-family: var(--font-display);
}
time {
  margin-left: auto;
}
.new {
  width: 8px;
  height: 8px;
  border: 2px solid #fff;
  border-radius: 50%;
  background: var(--lime);
}
.chev {
  align-self: center;
  color: rgba(255, 255, 255, 0.8);
}
@media (prefers-reduced-motion: reduce) {
  .note {
    transition: none;
  }
}
</style>
```

- [ ] **Step 3: List header + hide wiring in `MessagesPage.vue`**

Script:

```ts
import { useQuasar } from "quasar";
import type { InboxThread, SentThread } from "@/api/types";

const $q = useQuasar();
const unreadTotal = computed(() =>
  props.box === "inbox" ? store.inboxUnread : 0
);

async function hideThread(thread: InboxThread | SentThread) {
  try {
    await store.hideThread(thread.id);
    $q.notify({ message: $t("thread.hidden"), position: "bottom" });
  } catch (e) {
    $q.notify({
      type: "negative",
      message: e instanceof Error ? e.message : "Something went wrong",
      position: "bottom"
    });
  }
}
```

Template — replace `<PushPrompt />` + the frame opening with:

```vue
      <PushPrompt />

      <header class="list-head">
        <h1>{{ box === "inbox" ? $t("inbox.title") : $t("sent.title") }}</h1>
        <span v-if="unreadTotal" class="count-pill">{{
          $t("inbox.newCount", { n: unreadTotal })
        }}</span>
      </header>

      <div class="frame">
```

```vue
            <ThreadListItem
              :thread="thread"
              :box="box"
              :active="thread.id === threadId"
              :can-hide="box === 'inbox'"
              @hide="hideThread"
            />
```

Styles:

```scss
.list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 4px 2px 12px;
}
.list-head h1 {
  font-family: var(--font-display);
  font-size: 1.85rem;
  letter-spacing: -0.04em;
}
.count-pill {
  padding: 6px 11px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--lime);
  box-shadow: 0 3px 0 var(--edge);
  color: var(--ink);
  font: 800 0.72rem / 1 var(--font-display);
}
.threads {
  gap: 12px;
  padding-bottom: 6px;
}
```

- [ ] **Step 4: Copy**

```ts
  inbox: {
    title: "notes for you",
    newCount: "{n} new",
    // existing keys unchanged
  },
  sent: {
    title: "sent by you",
    // existing keys unchanged
  },
  thread: {
    hide: "Hide note",
    hidden: "Hidden. A new note brings it back.",
    // existing keys unchanged
  },
```

- [ ] **Step 5: Verify**

```bash
cd client && pnpm typecheck && pnpm lint:check && node e2e/smoke.mjs
```

Expected: smoke stays green (it never relies on the list markup). By hand: swipe an inbox card left → **Hide note** panel → tapping drops the card, and sending a new message to that thread brings it back after the next poll.

Add one assertion to `client/e2e/smoke.mjs` before the existing block test (so it cannot disturb it):

```js
// Swipe reveals a real, focusable button — the gesture is only an accelerator.
await A2.goto(`${BASE}/inbox`);
const card = A2.locator(".note").first();
await card.scrollIntoViewIfNeeded();
await card.evaluate(el => {
  const box = el.getBoundingClientRect();
  const fire = (type, x) =>
    el.dispatchEvent(
      new PointerEvent(type, {
        clientX: x,
        clientY: box.top + box.height / 2,
        bubbles: true,
        pointerId: 1
      })
    );
  fire("pointerdown", box.right - 20);
  fire("pointermove", box.right - 130);
  fire("pointerup", box.right - 130);
});
await A2.getByRole("button", { name: "Hide note" }).first().click();
await A2.waitForSelector("text=Hidden. A new note brings it back.");
```

- [ ] **Step 6: Commit**

```bash
git add client/src/composables/useSwipe.ts client/src/components/ThreadListItem.vue client/src/pages/MessagesPage.vue client/src/i18n/en-US/index.ts client/e2e/smoke.mjs
git commit -m "feat(ui): sticker inbox cards with swipe-to-hide"
```

---

### Task 8: Chat — bubbles, reactions, guest-locked card, composer

**Files:**
- Create: `client/src/components/ReactionBar.vue`
- Modify: `client/src/components/MessageBubble.vue`, `client/src/features/messaging/ConversationPane.vue`, `client/src/components/MessageComposer.vue`, `client/src/i18n/en-US/index.ts`

**Interfaces:**
- Produces: `ReactionBar` props `{ reactions: Reaction[]; disabled?: boolean }`, emits `react: [emoji: string]`.
- Consumes: `messaging.toggleReaction(threadId, messageId, emoji)`.
- Produces (i18n): `thread.guestTitle`, `thread.react`.

- [ ] **Step 1: Create `ReactionBar.vue`**

```vue
<script setup lang="ts">
import { ref } from "vue";
import type { Reaction } from "@/api/types";

defineProps<{ reactions: Reaction[]; disabled?: boolean }>();
defineEmits<{ react: [emoji: string] }>();

const PICKER = ["🔥", "❤️", "😂", "👍", "😮", "😢"];
const open = ref(false);
</script>

<template>
  <div class="bar">
    <button
      v-for="reaction in reactions"
      :key="reaction.emoji"
      type="button"
      class="chip"
      :class="{ mine: reaction.mine }"
      :aria-pressed="reaction.mine"
      :disabled="disabled"
      @click="$emit('react', reaction.emoji)"
    >
      <span aria-hidden="true">{{ reaction.emoji }}</span>
      <span class="count">{{ reaction.count }}</span>
    </button>

    <div v-if="!disabled" class="add">
      <button
        type="button"
        class="chip plus"
        :aria-expanded="open"
        :aria-label="$t('thread.react')"
        @click="open = !open"
      >
        🙂+
      </button>
      <div v-if="open" class="picker">
        <button
          v-for="emoji in PICKER"
          :key="emoji"
          type="button"
          class="pick"
          @click="
            $emit('react', emoji);
            open = false;
          "
        >
          {{ emoji }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 5px;
  margin-top: 5px;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 30px;
  padding: 0 9px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--surface);
  box-shadow: 0 2px 0 var(--edge);
  font-size: 0.78rem;
  cursor: pointer;
}
.chip.mine {
  background: var(--cream);
}
.chip:active {
  transform: translateY(2px);
  box-shadow: none;
}
.chip:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 2px;
}
.count {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 0.7rem;
}
.add {
  position: relative;
}
.plus {
  color: var(--text-3);
}
.picker {
  position: absolute;
  z-index: 5;
  bottom: calc(100% + 6px);
  left: 0;
  display: flex;
  gap: 2px;
  padding: 6px;
  border: 2px solid var(--edge);
  border-radius: 14px;
  background: var(--surface);
  box-shadow: var(--edge-shadow-sm);
}
.pick {
  border: 0;
  border-radius: 8px;
  background: transparent;
  font-size: 1.1rem;
  cursor: pointer;
}
.pick:hover {
  background: var(--cream);
}
</style>
```

- [ ] **Step 2: Wire it into `MessageBubble.vue`**

Add to props: `reactions: Reaction[]`, `reactionsDisabled?: boolean`; add emit `react: [message: Message, emoji: string]`. Render under the bubble:

```vue
      <ReactionBar
        :reactions="message.reactions"
        :disabled="message.mine || reactionsDisabled"
        @react="$emit('react', message, $event)"
      />
```

`message.mine` disables reacting to your own note, which keeps the feature about receiving.

- [ ] **Step 3: Hook it up in `ConversationPane.vue`**

```ts
async function react(message: Message, emoji: string) {
  try {
    await store.toggleReaction(props.threadId, message.id, emoji);
  } catch (e) {
    $q.notify({
      type: "negative",
      message: e instanceof Error ? e.message : "Couldn't react",
      position: "bottom"
    });
  }
}
```

```vue
          <MessageBubble
            v-for="message in group.items"
            :key="message.id"
            :message="message"
            :can-report="true"
            :reactions-disabled="Boolean(blocked || awaitingSignIn)"
            @report="reportTarget = $event"
            @react="react"
            @retry="retry"
            @remove="store.discard(threadId, $event.id)"
          />
```

- [ ] **Step 4: Restyle the bubbles, header, day pill, guest card and composer**

`MessageBubble.vue` styles:

```scss
.row {
  display: flex;
  align-items: flex-end;
  gap: var(--s-1);
  max-width: 100%;
}
.row.mine {
  flex-direction: row-reverse;
}
.bubble {
  max-width: min(80%, 34rem);
  padding: 11px 14px;
  border: 2.5px solid var(--edge);
  border-radius: 20px 20px 20px 7px;
  background: var(--bubble-theirs);
  color: var(--text);
  box-shadow: 0 3px 0 var(--edge);
  overflow-wrap: anywhere;
}
.mine .bubble {
  border-radius: 20px 20px 7px 20px;
  background: var(--grad-pink);
  color: #fff;
}
.mine .bubble .meta {
  color: rgba(255, 255, 255, 0.82);
}
.body {
  white-space: pre-wrap;
  font-size: 0.97rem;
  line-height: 1.5;
}
.meta {
  display: block;
  margin-top: 4px;
  font-family: var(--font-display);
  font-size: 0.68rem;
  font-weight: 700;
}
.pending {
  opacity: 0.7;
}
.failed {
  box-shadow: 0 3px 0 var(--danger);
}
.report {
  color: var(--text-3);
  opacity: 0.55;
}
.report:hover,
.report:focus-visible {
  opacity: 1;
}
```

`ConversationPane.vue` styles:

```scss
.head {
  display: flex;
  align-items: center;
  gap: var(--s-2);
  padding: 10px var(--s-4);
  background: var(--canvas);
  border-bottom: 2px solid var(--border);
}
.title {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  font-family: var(--font-display);
  font-size: 1.02rem;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.day {
  justify-self: center;
  padding: 4px var(--s-3);
  border-radius: 999px;
  background: var(--cream);
  border: 1.5px solid var(--border);
  color: var(--text-2);
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 0.65rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.foot {
  padding: var(--s-3) var(--s-4) calc(var(--s-3) + env(safe-area-inset-bottom));
  background: var(--canvas);
  border-top: 2px solid var(--border);
}
.notice {
  display: grid;
  gap: 6px;
  justify-items: center;
  padding: 14px;
  border: 2.5px dashed var(--border-strong);
  border-radius: var(--r-card);
  background: color-mix(in srgb, var(--surface) 70%, transparent);
  font-size: var(--text-secondary);
  color: var(--text-2);
  text-align: center;
}
.notice-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 0.95rem;
  color: var(--text);
}
```

Guest-locked branch:

```vue
      <p v-else-if="awaitingSignIn" class="notice" role="status">
        <span aria-hidden="true">🫥</span>
        <span class="notice-title">{{ $t("thread.guestTitle") }}</span>
        {{ $t("thread.guestSender") }}
      </p>
```

`MessageComposer.vue` styles: field `border: 2.5px solid var(--edge); border-radius: 22px; box-shadow: 0 3px 0 var(--edge);`, send button `background: var(--accent-strong) !important; border: 2.5px solid var(--edge); box-shadow: 0 3px 0 var(--edge);`.

- [ ] **Step 5: Copy**

```ts
  thread: {
    guestTitle: "They're still a guest",
    react: "React to this note",
    // guestSender and everything else unchanged — e2e quotes it
  },
```

- [ ] **Step 6: Verify**

```bash
cd client && pnpm typecheck && pnpm lint:check && node e2e/guest.mjs && node e2e/realtime.mjs
```

Expected: PASS. By hand on a two-way thread: the receiver taps 🙂+ and picks 🔥, the chip appears with count 1; tapping it again removes it. On your own bubble the picker is hidden.

- [ ] **Step 7: Commit**

```bash
git add client/src/components/ReactionBar.vue client/src/components/MessageBubble.vue client/src/components/MessageComposer.vue client/src/features/messaging/ConversationPane.vue client/src/i18n/en-US/index.ts
git commit -m "feat(ui): sticker chat with emoji reactions and a friendlier guest lock"
```

---

### Task 9: Your link — the loudest sticker, with QR

**Files:**
- Modify: `client/src/pages/LinkPage.vue`, `client/src/i18n/en-US/index.ts`, `client/package.json`

**Interfaces:**
- Produces: `qrcode-generator` dependency; the link sticker renders an inline SVG QR of `auth.profileLink`.
- Consumes: `AppButton`.

- [ ] **Step 1: Add the dependency**

```bash
cd client && pnpm add qrcode-generator
```

Expected: added to `package.json`. The package is pure JS, ~10 KB, works offline.

- [ ] **Step 2: Replace the page**

```vue
<script setup lang="ts">
// Home: one share card. The inbox is where messages are read.
import { computed } from "vue";
import { useMeta, useQuasar } from "quasar";
import { useI18n } from "vue-i18n";
import qrcode from "qrcode-generator";
import { useAuthStore } from "@/stores/auth";
import { copyText } from "@/utils/clipboard";
import AppButton from "@/components/AppButton.vue";

useMeta({ title: "Your link · Psst" });

const $q = useQuasar();
const { t } = useI18n();
const auth = useAuthStore();

const url = computed(() => auth.profileLink);
const handle = computed(() => auth.user?.username ?? "");

const qr = computed(() => {
  if (!url.value) return "";
  const code = qrcode(0, "M");
  code.addData(url.value);
  code.make();
  return code.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
});

async function copy() {
  const ok = await copyText(url.value);
  $q.notify({
    type: ok ? "positive" : "warning",
    message: ok ? t("settings.copied") : url.value,
    position: "bottom"
  });
}

async function share() {
  if (typeof navigator.share !== "function") {
    await copy();
    return;
  }
  try {
    await navigator.share({ title: "Psst", text: t("link.prompt"), url: url.value });
  } catch {
    /* dismissed */
  }
}
</script>

<template>
  <main class="link-page">
    <header class="link-head">
      <h1>{{ $t("link.title") }}</h1>
      <span class="live">{{ $t("link.live") }}</span>
    </header>

    <article class="sticker arrive">
      <span class="tag-line">psst.app/</span>
      <h2 class="handle">{{ handle }}</h2>
      <p class="url">{{ url }}</p>
      <div class="qr" v-html="qr" aria-hidden="true" />
    </article>

    <div class="actions arrive-late">
      <AppButton block @click="copy">{{ $t("link.copy") }}</AppButton>
      <AppButton variant="ghost" size="sm" class="share" @click="share">
        {{ $t("link.share") }}
      </AppButton>
    </div>

    <ol class="steps arrive-later">
      <li>{{ $t("link.step1") }}</li>
      <li>{{ $t("link.step2") }}</li>
    </ol>
  </main>
</template>
```

- [ ] **Step 3: Styles**

```scss
.link-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  /* Clear the bottom tab bar: the shell does not pad for it. */
  padding: 14px 22px calc(84px + env(safe-area-inset-bottom));
}
.link-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.link-head h1 {
  font-family: var(--font-display);
  font-size: 1.7rem;
  letter-spacing: -0.04em;
}
.live {
  padding: 5px 10px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--lime);
  color: var(--ink);
  font: 800 0.66rem / 1 var(--font-display);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.sticker {
  position: relative;
  overflow: hidden;
  padding: 22px 116px 26px 20px;
  border: 2.5px solid var(--edge);
  border-radius: 28px;
  background: var(--grad-sunset);
  box-shadow: 0 9px 0 var(--edge);
  color: #fff;
  transform: rotate(-1.2deg);
}
.sticker::before {
  content: "";
  position: absolute;
  width: 220px;
  height: 220px;
  right: -70px;
  top: -90px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.14);
}
.tag-line {
  font: 800 0.62rem / 1 var(--font-display);
  letter-spacing: 0.18em;
  text-transform: uppercase;
  opacity: 0.85;
}
.handle {
  margin: 6px 0 0;
  font-family: var(--font-display);
  font-size: 2.35rem;
  letter-spacing: -0.045em;
  line-height: 1;
}
.url {
  display: inline-block;
  margin-top: 10px;
  padding: 6px 11px;
  border: 1.5px solid rgba(255, 255, 255, 0.5);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.18);
  font: 700 0.72rem / 1 var(--font-sans);
  overflow-wrap: anywhere;
}
.qr {
  position: absolute;
  right: 16px;
  bottom: 18px;
  width: 86px;
  height: 86px;
  padding: 6px;
  border: 2.5px solid var(--edge);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 4px 0 var(--edge);
}
.qr :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
}
.actions {
  display: grid;
  gap: 10px;
}
.share {
  justify-self: center;
  padding-inline: 22px;
}
.steps {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
  counter-reset: step;
}
.steps li {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 10px 12px;
  border: 2px solid var(--border);
  border-radius: var(--r-control);
  background: var(--surface);
  color: var(--text-2);
  font-size: 0.8rem;
  font-weight: 700;
  counter-increment: step;
}
.steps li::before {
  content: counter(step);
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  flex: none;
  border-radius: 7px;
  background: var(--ink);
  color: var(--canvas);
  font: 800 0.68rem / 1 var(--font-display);
}
```

- [ ] **Step 4: Copy**

```ts
  link: {
    title: "your link",
    live: "live",
    // existing prompt/hint/step1/step2/copy/share unchanged
  },
```

- [ ] **Step 5: Verify**

```bash
cd client && pnpm typecheck && pnpm lint:check && node e2e/smoke.mjs
```

Expected: PASS. By hand: scan the QR with a phone → it opens the public link page; copy still toasts.

- [ ] **Step 6: Commit**

```bash
git add client/package.json client/pnpm-lock.yaml client/src/pages/LinkPage.vue client/src/i18n/en-US/index.ts
git commit -m "feat(ui): link page as a shareable sticker with a scannable QR"
```

---

### Task 10: Bottom pill tab bar

**Files:**
- Modify: `client/src/layouts/AppLayout.vue`, `client/src/layouts/AuthLayout.vue`, `client/src/pages/MessagesPage.vue`

**Interfaces:**
- Produces: nav fixed to the **bottom**; `--nav-height` (84px) reserves space *below* content; `.shell.in-thread { --nav-height: 0 }` when a conversation is open.

- [ ] **Step 1: Move the nav to the bottom**

```vue
    <nav v-show="!inThread" class="nav" :aria-label="$t('nav.main')">
      <router-link to="/link" class="tab" active-class="is-active">
        <span class="ico" aria-hidden="true">🔗</span>
        {{ $t("nav.link") }}
      </router-link>
      <router-link to="/inbox" class="tab" active-class="is-active">
        <span class="ico" aria-hidden="true">💌</span>
        {{ $t("nav.inbox") }}
        <span v-if="messaging.inboxUnread > 0" class="dot" aria-hidden="true" />
        <span v-if="messaging.inboxUnread > 0" class="sr-only">
          {{ messaging.inboxUnread }} unread
        </span>
      </router-link>
      <router-link to="/sent" class="tab" active-class="is-active">
        <span class="ico" aria-hidden="true">📮</span>
        {{ $t("nav.sent") }}
      </router-link>
      <router-link
        to="/settings"
        class="tab"
        active-class="is-active"
        :aria-label="$t('nav.settings')"
      >
        <span class="ico" aria-hidden="true">⚙️</span>
        {{ $t("nav.settings") }}
      </router-link>
    </nav>
```

- [ ] **Step 2: Styles**

```scss
.shell {
  width: min(100%, 430px);
  min-height: 100dvh;
  margin-inline: auto;
  background: var(--canvas);
}
/* A thread owns the whole screen: no tab bar, no reserved height. */
.shell.in-thread {
  --nav-height: 0px;
}
.nav {
  position: fixed;
  z-index: 10;
  left: 50%;
  bottom: calc(12px + env(safe-area-inset-bottom));
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: min(calc(100% - 24px), 406px);
  height: 62px;
  padding: 0 6px;
  border: 2.5px solid var(--edge);
  border-radius: 22px;
  background: var(--surface);
  box-shadow: 0 5px 0 var(--edge);
  transform: translateX(-50%);
}
.tab {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 2px;
  flex: 1;
  min-height: 48px;
  padding: 5px 2px;
  border-radius: 16px;
  color: var(--text-3);
  font-family: var(--font-display);
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-decoration: none;
}
.tab .ico {
  font-size: 1.05rem;
  line-height: 1;
  filter: grayscale(1);
  opacity: 0.7;
}
.tab.is-active {
  background: var(--accent-strong);
  color: #fff;
}
.tab.is-active .ico {
  filter: none;
  opacity: 1;
}
.tab:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 2px;
}
.dot {
  position: absolute;
  top: 6px;
  right: calc(50% - 20px);
  width: 8px;
  height: 8px;
  border: 2px solid var(--surface);
  border-radius: 50%;
  background: var(--accent);
}
.content {
  /* The tab bar is an overlay: full-height pages size themselves and
     scrolling pages pad their own end (Link, Settings). */
  padding-top: var(--safe-top);
}
@media (min-width: 480px) {
  .shell {
    box-shadow: 0 0 0 1px var(--border);
  }
}
```

- [ ] **Step 3: Re-anchor the floating CTA and the pane height**

`MessagesPage.vue`:

```scss
.get {
  bottom: calc(84px + env(safe-area-inset-bottom));
}
```

```scss
.split {
  --pane-height: calc(
    100dvh - var(--safe-top) - var(--nav-height) - env(safe-area-inset-bottom)
  );
}
.list-pane {
  padding-bottom: 140px;
}
```

- [ ] **Step 4: Verify the shell in every state**

```bash
cd client && pnpm typecheck && pnpm lint:check && node e2e/smoke.mjs
```

Expected: PASS, including the check that the nav is hidden inside a thread and the mobile overflow checks. By hand at 390×844 and 1280×800: the pill clears the home indicator, the floating "Get messages" button clears the pill, and the composer in a thread is not hidden.

- [ ] **Step 5: Commit**

```bash
git add client/src/layouts/AppLayout.vue client/src/layouts/AuthLayout.vue client/src/pages/MessagesPage.vue
git commit -m "feat(ui): bottom sticker tab bar"
```

---

### Task 11: Token pass on auth, settings and empty states

**Files:**
- Modify: `client/src/layouts/AuthLayout.vue`, `client/src/pages/{Login,GetLink,Welcome,Settings}Page.vue`, `client/src/components/EmptyState.vue`, `client/src/i18n/en-US/index.ts`, `client/e2e/smoke.mjs`

**Interfaces:**
- Produces: `inbox.emptyTitle` becomes `"No notes yet"`; `inbox.emptyBody` becomes `"Share your link. The first note always feels weird — then it's fun."`.

- [ ] **Step 1: EmptyState sticker tile**

```scss
.empty-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border: 2.5px solid var(--edge);
  border-radius: 16px;
  background: var(--cream);
  box-shadow: 0 4px 0 var(--edge);
  color: var(--text);
  transform: rotate(-4deg);
}
.empty-title {
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
```

- [ ] **Step 2: Auth screens**

Replace literal colours with tokens. **Do not touch `GoogleSignIn.vue` / `AppleSignIn.vue`** — those buttons are drawn by Google's GSI and Apple's JS. Keep every label identical (`Create my link`, demo names).

`LoginPage.vue`:

```scss
.story {
  display: grid;
  align-content: center;
  justify-items: center;
  gap: 14px;
  width: 100%;
  aspect-ratio: 1;
  padding: 24px;
  text-align: center;
  border: 2.5px solid var(--edge);
  border-radius: 28px;
  background: var(--grad-pink);
  box-shadow: 0 9px 0 var(--edge);
}
.ring {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border: 3px solid var(--edge);
  border-radius: 50%;
  background: var(--surface);
  color: var(--accent-strong);
  transform: rotate(-4deg);
}
.story h1 {
  max-width: 9ch;
  margin: 0;
  color: #fff;
  font-family: var(--font-display);
  font-size: 2.15rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.02;
}
```

`AuthLayout.vue`:

```scss
.card {
  border: 2.5px solid var(--edge);
  border-radius: var(--r-card);
  background: var(--surface);
  box-shadow: var(--edge-shadow);
}
```

`GetLinkPage.vue` / `WelcomePage.vue`: swap primary `q-btn` for `AppButton` with the same slot text, so `getByRole("button", { name: "Create my link" })` keeps working.

- [ ] **Step 3: Settings sections**

```scss
.page {
  /* Settings scrolls; the shell only pads the top, so the end pads itself. */
  padding-bottom: calc(84px + env(safe-area-inset-bottom));
}
.panel {
  padding: 16px;
  border: 2.5px solid var(--border-strong);
  border-radius: var(--r-card);
  background: var(--surface);
  box-shadow: 0 4px 0 var(--border);
}
.panel + .panel {
  margin-top: 14px;
}
```

Do not change the theme radio group, the delete flow or any `settings.*` key — e2e drives them by label.

- [ ] **Step 4: Copy + its test**

```ts
  inbox: {
    emptyTitle: "No notes yet",
    emptyBody: "Share your link. The first note always feels weird — then it's fun.",
    // other keys unchanged
  },
```

`client/e2e/smoke.mjs`:

```js
await A.page.waitForSelector("text=No notes yet");
```

- [ ] **Step 5: Verify every auth path**

```bash
cd client && pnpm typecheck && pnpm lint:check && node e2e/smoke.mjs && node e2e/account.mjs
```

Expected: PASS. By hand: sign in → username → link → settings → Dark: no white flash, no unreadable label.

- [ ] **Step 6: Commit**

```bash
git add client/src/layouts/AuthLayout.vue client/src/pages/LoginPage.vue client/src/pages/GetLinkPage.vue client/src/pages/WelcomePage.vue client/src/pages/SettingsPage.vue client/src/components/EmptyState.vue client/src/i18n/en-US/index.ts client/e2e/smoke.mjs
git commit -m "feat(ui): sticker pass over auth, settings and empty states"
```

---

### Task 12: Full verification, screenshots, docs

**Files:**
- Modify: `client/e2e/flow-shots.mjs`, `docs/USER-FLOW.md`, `docs/flow/*.png`

- [ ] **Step 1: Let flow-shots use a local browser**

```js
const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}
);
```

- [ ] **Step 2: Fresh DB, migrations, all tests**

```bash
cd server && php artisan migrate:fresh --seed && php artisan test
php artisan serve
php artisan reverb:start
php artisan queue:work
cd ../client && pnpm dev
```

In another shell:

```bash
cd client && pnpm typecheck && pnpm lint:check
node e2e/smoke.mjs && node e2e/guest.mjs && node e2e/realtime.mjs && node e2e/account.mjs && node e2e/push.mjs
```

Expected: server suite green (150 + ~10 new), every e2e suite PASS, no collected console errors.

- [ ] **Step 3: Regenerate the flow screenshots**

```bash
cd client && CHROME_PATH="$(command -v chromium || echo /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome)" node e2e/flow-shots.mjs
```

Expected: 18 PNGs in `docs/flow/`, same filenames. Open `docs/flow/overview.png` and confirm the new palette in every frame.

- [ ] **Step 4: Update the flow doc**

In `docs/USER-FLOW.md`: inbox step mentions swipe-to-hide, chat step mentions tapping a reaction, empty-state sentence uses the new copy. Keep screenshot filenames unchanged.

- [ ] **Step 5: Final diff review and commit**

```bash
git status --short
git diff --stat
```

Expected: only the files listed in this plan; no scratch files, no `client/dist`.

```bash
git add docs/USER-FLOW.md docs/flow client/e2e/flow-shots.mjs
git commit -m "docs(ui): refresh the user-flow screenshots for the sticker shop design"
```

---

## Self-Review

**Spec coverage**

| Spec item (`docs/mockups/README.md`) | Task |
|---|---|
| Palette, gradients, radii, ink borders | 1 |
| Bricolage display type | 1 |
| Chunky press-down buttons | 1, 2 |
| Prompt chips + paper composer + emoji row | 2 |
| Emoji celebration on send | 3 |
| Sticker avatar / "ask me anything" tape | 2 |
| Inbox gradient notes, lime unread, list header | 7 |
| Swipe gesture → hide (the mockup's "hide 🙈") | 4 + 7 |
| Paper/candy bubbles, guest-locked card | 8 |
| Message reactions (mockup's tap-to-react) | 5 + 6 + 8 |
| Link sticker, copy/share, steps | 9 |
| QR on the link card | 9 |
| Tab bar | 10 |
| Auth/settings/empty-state consistency, dark mode | 11 |
| Motion guardrails, reduced motion, AA contrast | 1, 2, 3, 12 |
| Story export, voice whispers, typing | Out of scope, listed |

**Placeholder scan:** no TBD/TODO; every code step contains the code to paste; every command has an expected result.

**Interface consistency:** `celebrate(host, count)`, `haptic(ms)`, `useSwipe({threshold,max}) → {offset, open, close, handlers}`, `AppButton` (`primary|ghost|lime`, `md|sm`), `PromptChips` (`prompts`, `pick`), `ReactionBar` (`reactions`, `react`), `ThreadListItem` (`canHide`, `hide`), store (`hideThread`, `toggleReaction`), endpoints (`PUT/DELETE /threads/{id}/hide`, `POST /messages/{id}/reactions`), i18n (`profile.prompts`, `inbox.title`, `inbox.newCount`, `sent.title`, `thread.hide`, `thread.hidden`, `thread.guestTitle`, `thread.react`, `link.title`, `link.live`) — each name is introduced once and used with the same shape later.
