<script setup lang="ts">
// Pattern: Form — public link page. The sender's first impression, so it stays small and fast.
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useMeta } from "quasar";
import { Link2, UserX } from "@lucide/vue";
import { ApiError } from "@/api/http";
import { profileApi } from "@/api/endpoints";
import type { PublicProfile } from "@/api/types";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";
import { copyText } from "@/utils/clipboard";
import AppAvatar from "@/components/AppAvatar.vue";
import EmptyState from "@/components/EmptyState.vue";

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

useMeta(() => ({
  title: profile.value ? `Message ${profile.value.name} · Psst` : "Psst"
}));

const draftKey = computed(() => `psst.draft.${props.username}`);
const isOwn = computed(() => auth.user?.username === props.username);
const trimmed = computed(() => body.value.trim());
const remaining = computed(() => MAX - body.value.length);
const canSend = computed(
  () => trimmed.value.length > 0 && remaining.value >= 0 && !sending.value
);

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

async function submit() {
  if (!canSend.value) return;
  if (!auth.isAuthenticated) {
    await router.push({
      path: "/login",
      query: { next: `/${props.username}` }
    });
    return;
  }
  sending.value = true;
  error.value = "";
  try {
    const result = await profileApi.send(props.username, trimmed.value);
    sentThreadId.value = result.thread_id;
    body.value = "";
    void messaging.loadSent();
  } catch (e) {
    error.value =
      e instanceof Error
        ? e.message
        : "Something went wrong. Please try again.";
  } finally {
    sending.value = false;
  }
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
    class="surface card done"
    role="status"
  >
    <AppAvatar :name="profile.name" :src="profile.avatar_url" :size="56" />
    <h1>{{ $t("profile.sent") }}</h1>
    <p class="text-secondary-copy">{{
      $t("profile.sentBody", { name: profile.name })
    }}</p>
    <div class="row">
      <q-btn
        color="primary"
        unelevated
        no-caps
        :label="$t('profile.viewConversation')"
        :to="`/sent/${sentThreadId}`"
      />
      <q-btn
        outline
        unelevated
        no-caps
        :label="$t('profile.sendAnother')"
        @click="sentThreadId = null"
      />
    </div>
  </div>

  <form
    v-else-if="profile"
    class="surface card"
    novalidate
    @submit.prevent="submit"
  >
    <header class="head">
      <AppAvatar :name="profile.name" :src="profile.avatar_url" :size="64" />
      <h1>{{ $t("profile.title", { name: profile.name }) }}</h1>
      <p class="text-secondary-copy">{{ $t("profile.subtitle") }}</p>
    </header>

    <div class="field">
      <label for="message">{{ $t("profile.label") }}</label>
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
      <p
        id="message-help"
        class="text-meta help"
        :class="{ over: remaining < 0 }"
      >
        <span v-if="error" class="error" role="alert">{{ error }}</span>
        <span v-else-if="!auth.isAuthenticated && trimmed">{{
          $t("profile.signInNote")
        }}</span>
        <span class="count" aria-live="polite">{{ remaining }}</span>
      </p>
    </div>

    <q-btn
      type="submit"
      color="primary"
      unelevated
      no-caps
      size="lg"
      :loading="sending"
      :disable="!canSend"
      :label="
        auth.isAuthenticated ? $t('profile.send') : $t('profile.signInToSend')
      "
    />
  </form>
</template>

<style scoped lang="scss">
.card {
  display: grid;
  gap: var(--s-5);
  padding: var(--s-6) var(--s-5);
}
.head {
  display: grid;
  justify-items: center;
  gap: var(--s-2);
  text-align: center;
}
.head h1 {
  font-size: var(--text-h2);
}
.field {
  display: grid;
  gap: var(--s-2);
}
label {
  font-size: var(--text-secondary);
  font-weight: 500;
}
.input {
  padding: var(--s-3) var(--s-4);
  resize: vertical;
  min-height: 140px;
  border: 1px solid var(--border-strong);
  border-radius: var(--r-control);
  background: var(--canvas);
  color: var(--text);
  font: inherit;
  transition: border-color var(--dur) var(--ease);
}
.input:focus-visible {
  outline: 2px solid var(--accent-text);
  outline-offset: 1px;
  border-color: var(--accent-text);
}
.help {
  display: flex;
  justify-content: space-between;
  gap: var(--s-3);
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
.done {
  justify-items: center;
  text-align: center;
}
.row {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--s-2);
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
