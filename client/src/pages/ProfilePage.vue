<script setup lang="ts">
// Pattern: Form — public link page. The sender's first impression, so it stays small and fast.
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useMeta } from "quasar";
import { Link2, UserX } from "@lucide/vue";
import { ApiError } from "@/api/http";
import { profileApi } from "@/api/endpoints";
import { config } from "@/utils/config";
import { guestToken, setGuestToken } from "@/utils/guest";
import TurnstileBox from "@/components/TurnstileBox.vue";
import type { PublicProfile } from "@/api/types";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";
import { copyText } from "@/utils/clipboard";
import { celebrate } from "@/utils/celebrate";
import AppAvatar from "@/components/AppAvatar.vue";
import EmptyState from "@/components/EmptyState.vue";
import AppButton from "@/components/AppButton.vue";
import PromptChips from "@/components/PromptChips.vue";

const MAX = 1000;
const props = defineProps<{ username: string }>();

const router = useRouter();
const auth = useAuthStore();
const messaging = useMessagingStore();

const profile = ref<PublicProfile | null>(null);
const status = ref<"loading" | "ready" | "missing" | "error">("loading");
const body = ref("");
const sending = ref(false);
const error = ref("");
const sentThreadId = ref<string | null>(null);
/** Sent without an account: offer sign-in as the way to get replies. */
const sentAsGuest = ref(false);
/** Honeypot: real people never see or fill this. */
const website = ref("");
const challenge = ref<string | null>(null);
const turnstile = ref<InstanceType<typeof TurnstileBox> | null>(null);

useMeta(() => ({
  title: profile.value ? `Message ${profile.value.name} · Psst` : "Psst"
}));

const draftKey = computed(() => `psst.draft.${props.username}`);
const isOwn = computed(() => auth.user?.username === props.username);
const trimmed = computed(() => body.value.trim());
const remaining = computed(() => MAX - body.value.length);
const challengeNeeded = Boolean(config.turnstileSiteKey);
const canSend = computed(
  () =>
    trimmed.value.length > 0 &&
    remaining.value >= 0 &&
    !sending.value &&
    (auth.isAuthenticated || !challengeNeeded || challenge.value !== null)
);

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

const sentCard = useTemplateRef<HTMLElement>("sentCard");
watch(sentThreadId, id => {
  if (!id) return;
  void nextTick(() => celebrate(sentCard.value));
});

// Keep a half-written message across the sign-in round trip (and accidental refreshes).
function readDraft() {
  try {
    body.value = sessionStorage.getItem(draftKey.value) ?? "";
  } catch {
    body.value = "";
  }
}
function writeDraft() {
  try {
    if (body.value) sessionStorage.setItem(draftKey.value, body.value);
    else sessionStorage.removeItem(draftKey.value);
  } catch {
    /* non-fatal */
  }
}
watch(body, writeDraft);

async function load() {
  status.value = "loading";
  profile.value = null;
  sentThreadId.value = null;
  try {
    profile.value = await profileApi.show(props.username);
    status.value = "ready";
    readDraft();
  } catch (e) {
    status.value =
      e instanceof ApiError && e.status === 404 ? "missing" : "error";
  }
}
onMounted(load);
watch(() => props.username, load);
watch([status, isOwn], () => {
  if (status.value === "ready" && isOwn.value) void router.replace("/link");
});

async function submit() {
  if (!canSend.value) return;
  sending.value = true;
  error.value = "";
  try {
    const result = await profileApi.send(
      props.username,
      trimmed.value,
      auth.isAuthenticated
        ? undefined
        : {
            token: guestToken(),
            ...(challenge.value ? { challenge: challenge.value } : {}),
            website: website.value
          }
    );
    if ("guest_token" in result) {
      setGuestToken(result.guest_token);
      sentAsGuest.value = true;
      sentThreadId.value = "guest";
    } else {
      sentAsGuest.value = false;
      sentThreadId.value = result.thread_id;
      void messaging.loadSent();
    }
    body.value = "";
  } catch (e) {
    error.value =
      e instanceof Error
        ? e.message
        : "Something went wrong. Please try again.";
    // A challenge token is single-use.
    turnstile.value?.reset();
  } finally {
    sending.value = false;
  }
}

function signInToChat() {
  void router.push({ path: "/login", query: { next: "/sent" } });
}

const copied = ref(false);
async function copyOwn() {
  copied.value = await copyText(auth.profileLink);
}
</script>

<template>
  <div
    v-if="status === 'loading'"
    class="surface card skeleton"
    aria-busy="true"
    aria-label="Loading"
  >
    <span class="sk-av" /><span class="sk-line" /><span class="sk-box" />
  </div>

  <div v-else-if="status === 'missing'" class="surface card">
    <EmptyState
      :icon="UserX"
      :title="$t('profile.notFound')"
      :body="$t('profile.notFoundBody')"
    >
      <q-btn
        color="primary"
        unelevated
        no-caps
        :label="$t('profile.goHome')"
        to="/"
      />
    </EmptyState>
  </div>

  <div v-else-if="status === 'error'" class="surface card" role="alert">
    <EmptyState :icon="UserX" :title="$t('errors.generic')">
      <q-btn outline unelevated no-caps label="Try again" @click="load" />
    </EmptyState>
  </div>

  <div v-else-if="profile && isOwn" class="surface card">
    <EmptyState
      :icon="Link2"
      :title="$t('profile.own')"
      :body="$t('profile.ownBody')"
    >
      <q-btn
        color="primary"
        unelevated
        no-caps
        :label="copied ? 'Copied' : $t('profile.copyLink')"
        @click="copyOwn"
      />
    </EmptyState>
  </div>

  <div
    v-else-if="profile && sentThreadId"
    ref="sentCard"
    class="card done"
    role="status"
  >
    <AppAvatar :name="profile.name" :src="profile.avatar_url" :size="56" />
    <h1>{{ $t("profile.sent") }}</h1>
    <p class="text-secondary-copy">{{
      $t("profile.sentBody", { name: profile.name })
    }}</p>
    <p v-if="sentAsGuest" class="chat-offer text-secondary-copy">
      {{ $t("profile.chatOffer") }}
    </p>

    <AppButton v-if="sentAsGuest" block @click="signInToChat">
      {{ $t("profile.signInToChat") }}
    </AppButton>
    <AppButton v-else block :to="`/sent/${sentThreadId}`">
      {{ $t("profile.viewConversation") }}
    </AppButton>

    <div class="after">
      <button type="button" class="again" @click="sentThreadId = null">
        {{ $t("profile.sendAnother") }}
      </button>
      <router-link
        v-if="sentAsGuest && !auth.isAuthenticated"
        to="/get-link"
        class="own-link"
      >
        {{ $t("profile.getOwnLinkInstead") }}
      </router-link>
    </div>
  </div>

  <form
    v-else-if="profile"
    class="send-page"
    novalidate
    @submit.prevent="submit"
  >
    <header class="hero arrive">
      <span class="sticker-avatar">
        <AppAvatar :name="profile.name" :src="profile.avatar_url" :size="88" />
        <span class="tape">{{ $t("profile.prompt") }}</span>
      </span>
      <h1>
        {{ profile.name }}
        <span class="handle">@{{ profile.username }}</span>
      </h1>
      <p class="prompt-line">{{ $t("profile.subtitle") }}</p>
    </header>

    <PromptChips :prompts="prompts" @pick="usePrompt" />

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
        <div
          class="emoji-row"
          role="group"
          :aria-label="$t('profile.emojiLabel')"
        >
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

    <!-- Honeypot: hidden from people and assistive tech, irresistible to form-filling bots. -->
    <input
      v-model="website"
      class="hp"
      type="text"
      name="website"
      tabindex="-1"
      autocomplete="off"
      aria-hidden="true"
    />

    <TurnstileBox
      v-if="!auth.isAuthenticated"
      ref="turnstile"
      @token="challenge = $event"
    />

    <AppButton type="submit" size="md" block :disabled="!canSend">
      {{ sending ? $t("thread.sending") : $t("profile.send") }}
    </AppButton>

    <div class="send-foot">
      <p class="tiny">🔒 {{ $t("profile.privacyShort") }}</p>
      <router-link v-if="!auth.isAuthenticated" to="/get-link" class="own-link">
        {{ $t("profile.getYourOwn") }}
      </router-link>
    </div>
  </form>
</template>

<style scoped lang="scss">
.own-link {
  justify-self: center;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  font-size: var(--text-secondary);
  color: var(--accent-text, var(--accent));
}
.hp {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  opacity: 0;
}
.chat-offer {
  max-width: 32ch;
  margin-inline: auto;
  text-align: center;
}
.card {
  display: grid;
  gap: var(--s-5);
  padding: var(--s-7) var(--s-5) var(--s-5);
  border-radius: 32px;
}
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
  border: 5px solid var(--surface);
  border-radius: 50%;
  background: var(--grad-pink);
  box-shadow:
    0 8px 0 var(--edge),
    0 16px 26px rgba(214, 0, 79, 0.18);
  transform: rotate(-5deg);
}
.sticker-avatar :deep(.avatar) {
  background: transparent;
  border: 0;
  color: #fff;
  font-family: var(--font-display);
  font-weight: 800;
}
.tape {
  position: absolute;
  right: -52px;
  bottom: -4px;
  z-index: 2;
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
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.9rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.05;
}
.prompt-line {
  max-width: 40ch;
  color: var(--text-2);
  font-size: 0.82rem;
}
.handle {
  color: var(--text-3);
  font-family: var(--font-sans);
  font-size: 0.62em;
  font-weight: 600;
  letter-spacing: 0;
}
.paper {
  position: relative;
  padding: 20px 14px 10px;
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
.paper::before {
  content: "";
  position: absolute;
  top: -11px;
  left: 50%;
  width: 92px;
  height: 22px;
  background: rgba(255, 226, 190, 0.9);
  border: 1px solid rgba(180, 140, 100, 0.28);
  box-shadow: 0 1px 3px rgba(27, 16, 36, 0.1);
  transform: translateX(-50%) rotate(-2deg);
}
.paper:focus-within {
  box-shadow:
    var(--edge-shadow-sm),
    0 0 0 3px var(--accent-soft);
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
.paper .input:focus-visible {
  outline: 0;
}
.help {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.help .count {
  margin-left: auto;
}
.help.over .count {
  color: var(--danger);
}
.error {
  color: var(--danger);
}
.card.done {
  position: relative;
  width: min(100%, 340px);
  margin-inline: auto;
  justify-items: center;
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
.after {
  display: grid;
  justify-items: center;
  gap: 6px;
}
.again {
  border: 0;
  background: transparent;
  color: var(--text-2);
  font: 800 0.8rem / 1 var(--font-display);
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}
.skeleton {
  justify-items: center;
}
.sk-av,
.sk-line,
.sk-box {
  display: block;
  border-radius: var(--r-control);
  background: var(--surface-hover);
  animation: pulse 1.2s ease-in-out infinite;
}
.sk-av {
  width: 64px;
  height: 64px;
  border-radius: 50%;
}
.sk-line {
  width: 70%;
  height: 24px;
}
.sk-box {
  width: 100%;
  height: 140px;
}
@keyframes pulse {
  50% {
    opacity: 0.5;
  }
}
@media (max-width: 480px) {
  .card {
    padding: var(--s-5) var(--s-4);
  }
}
</style>
