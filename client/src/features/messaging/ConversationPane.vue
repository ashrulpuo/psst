<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  ref,
  useTemplateRef,
  watch
} from "vue";
import { useRouter } from "vue-router";
import { useQuasar } from "quasar";
import { useI18n } from "vue-i18n";
import {
  ArrowLeft,
  EllipsisVertical,
  ShieldBan,
  ShieldCheck
} from "@lucide/vue";
import type { Message } from "@/api/types";
import { trackEvent } from "@/api/endpoints";
import {
  STORY_TEMPLATES,
  isShareCancel,
  prepareNote,
  renderNoteCard,
  shareImage,
  type StoryTemplate
} from "@/utils/storyCard";
import { useMessagingStore } from "@/stores/messaging";
import { useAuthStore } from "@/stores/auth";
import { useRealtimeStore } from "@/stores/realtime";
import { usePolling } from "@/composables/usePolling";
import { dayLabel, shortTime } from "@/utils/format";
import AppButton from "@/components/AppButton.vue";
import AppAvatar from "@/components/AppAvatar.vue";
import MessageBubble from "@/components/MessageBubble.vue";
import MessageComposer from "@/components/MessageComposer.vue";
import BlockDialog from "@/components/BlockDialog.vue";
import ReportDialog from "@/components/ReportDialog.vue";

const props = defineProps<{ threadId: string; box: "inbox" | "sent" }>();

const $q = useQuasar();
const { t } = useI18n();
const router = useRouter();
const store = useMessagingStore();
const auth = useAuthStore();
const realtime = useRealtimeStore();

const scroller = useTemplateRef<HTMLElement>("scroller");
const composer =
  useTemplateRef<InstanceType<typeof MessageComposer>>("composer");

const thread = computed(() => store.findThread(props.threadId));
const messages = computed(() => store.messages[props.threadId] ?? []);
const loading = computed(() => store.threadLoading === props.threadId);
const isInbox = computed(() => props.box === "inbox");

const title = computed(() => {
  const t = thread.value;
  if (!t) return "";
  return "receiver" in t ? t.receiver.name : t.label;
});
const avatar = computed(() => {
  const t = thread.value;
  return t && "receiver" in t ? t.receiver.avatar_url : null;
});

const meta = computed(() => {
  const current = thread.value;
  const when = current?.last_message_at
    ? shortTime(current.last_message_at)
    : "";
  // On the sender's side, name the mask they are wearing instead of describing the thread.
  const ownLabel =
    current && "label" in current
      ? t("thread.youAppearAs", { label: current.label })
      : "";
  const label = isInbox.value
    ? t("thread.noteMeta")
    : ownLabel || t("thread.replyMeta");
  return when ? `${label} · ${when}` : label;
});

const blocked = computed(() => {
  const t = thread.value;
  return Boolean(t && "blocked" in t && t.blocked);
});
const cannotReply = computed(() => {
  const t = thread.value;
  return Boolean(t && "can_reply" in t && !t.can_reply);
});
const needsSignIn = computed(() => auth.user?.can_reply === false);
const awaitingSignIn = computed(() => {
  const t = thread.value;
  return Boolean(t && "can_chat" in t && !t.can_chat && !t.blocked);
});
// Messages grouped under day separators, oldest first.
const groups = computed(() => {
  const result: { day: string; items: Message[] }[] = [];
  for (const message of messages.value) {
    const day = dayLabel(message.created_at);
    const last = result[result.length - 1];
    if (last && last.day === day) last.items.push(message);
    else result.push({ day, items: [message] });
  }
  return result;
});

function nearBottom(): boolean {
  const el = scroller.value;
  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120;
}
async function scrollToEnd() {
  await nextTick();
  const el = scroller.value;
  if (el) el.scrollTop = el.scrollHeight;
}

// Open a thread: load, mark read, jump to the newest message.
watch(
  () => props.threadId,
  async id => {
    await store.openThread(id);
    void store.markRead(id);
    await scrollToEnd();
  },
  { immediate: true }
);

// Follow new messages only if the reader is already at the bottom.
watch(
  () => messages.value.length,
  async (_, previous) => {
    if (previous === undefined) return;
    const stick = nearBottom();
    await nextTick();
    if (stick) await scrollToEnd();
  }
);

// Tell the store which conversation is on screen so pushed messages are marked read straight away.
watch(
  () => props.threadId,
  id => store.setActive(id),
  { immediate: true }
);
onBeforeUnmount(() => store.setActive(null));

usePolling(
  async () => {
    await store.refreshThread(props.threadId);
    void store.markRead(props.threadId);
  },
  () => (realtime.connected ? 30000 : 6000)
);

const sending = ref(false);
async function send(body: string) {
  sending.value = true;
  composer.value?.clear();
  try {
    await store.send(props.threadId, body);
  } catch (e) {
    $q.notify({
      type: "negative",
      message: e instanceof Error ? e.message : "Message not sent",
      position: "bottom"
    });
  } finally {
    sending.value = false;
  }
  await scrollToEnd();
}
async function retry(message: Message) {
  store.discard(props.threadId, message.id);
  await send(message.body);
}

async function loadOlder() {
  const el = scroller.value;
  const before = el?.scrollHeight ?? 0;
  await store.loadOlder(props.threadId);
  await nextTick();
  // Keep the reader's place: the content grew above them.
  if (el) el.scrollTop = el.scrollHeight - before;
}

// ---- block / report ----
const blockOpen = ref(false);
const blockBusy = ref(false);
async function setBlocked(value: boolean) {
  blockBusy.value = true;
  try {
    await store.setBlocked(props.threadId, value);
    blockOpen.value = false;
  } catch (e) {
    $q.notify({
      type: "negative",
      message: e instanceof Error ? e.message : "Something went wrong",
      position: "bottom"
    });
  } finally {
    blockBusy.value = false;
  }
}

const reportTarget = ref<Message | null>(null);
const reportOpen = computed({
  get: () => reportTarget.value !== null,
  set: v => !v && (reportTarget.value = null)
});
const reportBusy = ref(false);
const reportError = ref("");

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
// ---- share a received note as a story card ----
// The note is drawn on this device only. Nothing about it is sent anywhere, and the tracking
// event carries just its name.
const TEMPLATE_KEY = "psst.story_template";
const NOTICE_KEY = "psst.note_share_notice";
const sharingId = ref<string | null>(null);

function savedTemplate(): StoryTemplate {
  try {
    const saved = localStorage.getItem(TEMPLATE_KEY);
    return STORY_TEMPLATES.find(x => x === saved) ?? STORY_TEMPLATES[0];
  } catch {
    return STORY_TEMPLATES[0];
  }
}

function noticeSeen(): boolean {
  try {
    return localStorage.getItem(NOTICE_KEY) === "1";
  } catch {
    return false;
  }
}

const noticeFor = ref<Message | null>(null);
const noticeOpen = computed({
  get: () => noticeFor.value !== null,
  set: v => !v && (noticeFor.value = null)
});
function confirmNotice() {
  const message = noticeFor.value;
  noticeFor.value = null;
  try {
    localStorage.setItem(NOTICE_KEY, "1");
  } catch {
    /* private mode: the notice shows again next time */
  }
  if (message) void shareNote(message);
}

async function shareNote(message: Message) {
  const username = auth.user?.username;
  const url = auth.profileLink;
  if (sharingId.value || !username || !url || message.mine) return;
  if (!prepareNote(message.body)) return;
  if (!noticeSeen()) {
    noticeFor.value = message;
    return;
  }
  sharingId.value = message.id;
  try {
    const blob = await renderNoteCard(
      savedTemplate(),
      message.body,
      username,
      url
    );
    const result = await shareImage(blob, url);
    void trackEvent("note_shared");
    if (result === "downloaded") {
      $q.notify({
        type: "positive",
        message: t("thread.shareSaved"),
        position: "bottom"
      });
    }
  } catch (e) {
    if (!isShareCancel(e)) {
      $q.notify({
        type: "warning",
        message: t("thread.shareFailed"),
        position: "bottom"
      });
    }
  } finally {
    sharingId.value = null;
  }
}

async function submitReport(reason: string) {
  if (!reportTarget.value) return;
  reportBusy.value = true;
  reportError.value = "";
  try {
    await store.report(reportTarget.value.id, reason);
    reportTarget.value = null;
    $q.notify({
      type: "positive",
      message: "Thanks. We'll take a look.",
      position: "bottom"
    });
  } catch (e) {
    reportError.value = e instanceof Error ? e.message : "Something went wrong";
  } finally {
    reportBusy.value = false;
  }
}
</script>

<template>
  <section class="pane" :aria-label="title">
    <header class="head">
      <q-btn
        flat
        round
        dense
        class="hit back"
        :aria-label="$t('thread.back')"
        @click="router.push(`/${box}`)"
      >
        <ArrowLeft :size="20" />
      </q-btn>
      <AppAvatar :name="title || '?'" :src="avatar" :size="40" />
      <div class="who">
        <h2 class="title">{{ title }}</h2>
        <span class="sub">{{ meta }}</span>
      </div>

      <q-btn
        v-if="isInbox"
        flat
        round
        dense
        class="hit"
        :aria-label="$t('thread.options')"
      >
        <EllipsisVertical :size="20" />
        <q-menu auto-close anchor="bottom right" self="top right">
          <q-list class="menu">
            <q-item v-if="!blocked" clickable @click="blockOpen = true">
              <q-item-section avatar><ShieldBan :size="18" /></q-item-section>
              <q-item-section>{{ $t("thread.block") }}</q-item-section>
            </q-item>
            <q-item v-else clickable @click="setBlocked(false)">
              <q-item-section avatar><ShieldCheck :size="18" /></q-item-section>
              <q-item-section>{{ $t("thread.unblock") }}</q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </header>

    <div
      ref="scroller"
      class="scroll"
      role="log"
      aria-live="polite"
      :aria-busy="loading"
    >
      <div
        v-if="store.threadError && !messages.length"
        class="state"
        role="alert"
      >
        <p>{{ $t("thread.loadFailed") }}</p>
        <q-btn
          outline
          no-caps
          unelevated
          :label="$t('thread.tryAgain')"
          @click="store.openThread(threadId)"
        />
      </div>

      <template v-else>
        <div v-if="store.hasOlder(threadId)" class="older">
          <q-btn
            flat
            no-caps
            dense
            :label="$t('thread.loadOlder')"
            :loading="loading"
            @click="loadOlder"
          />
        </div>

        <div
          v-if="loading && !messages.length"
          class="skeleton"
          aria-hidden="true"
        >
          <span class="sk theirs" /><span class="sk mine" /><span
            class="sk theirs short"
          />
        </div>

        <div v-for="group in groups" :key="group.day" class="group">
          <p class="day text-meta">{{ group.day }}</p>
          <MessageBubble
            v-for="message in group.items"
            :key="message.id"
            :message="message"
            :can-report="true"
            :reactions-disabled="Boolean(blocked || awaitingSignIn)"
            :can-share="isInbox && !blocked && !!auth.profileLink"
            :sharing="sharingId !== null"
            @share="shareNote"
            @report="reportTarget = $event"
            @react="react"
            @retry="retry"
            @remove="store.discard(threadId, $event.id)"
          />
        </div>
      </template>
    </div>

    <footer class="foot">
      <p v-if="blocked" class="notice" role="status">
        {{ $t("thread.blockedBanner") }}
        <q-btn
          flat
          dense
          no-caps
          color="primary"
          :label="$t('thread.unblock')"
          :loading="blockBusy"
          @click="setBlocked(false)"
        />
      </p>
      <p v-else-if="needsSignIn" class="notice" role="status">
        {{ $t("thread.signInToReply") }}
        <q-btn
          flat
          dense
          no-caps
          color="primary"
          :label="$t('profile.signInToChat')"
          :to="{ path: '/login', query: { next: `/${box}/${threadId}` } }"
        />
      </p>
      <p v-else-if="awaitingSignIn" class="notice" role="status">
        <span aria-hidden="true">🫥</span>
        <span class="notice-title">{{ $t("thread.guestTitle") }}</span>
        {{ $t("thread.guestSender") }}
      </p>
      <p v-else-if="cannotReply" class="notice" role="status">{{
        $t("thread.cannotReply")
      }}</p>
      <MessageComposer
        v-else
        ref="composer"
        :label="$t('thread.composerLabel')"
        :submit-label="$t('thread.send')"
        placeholder="Write a reply…"
        @submit="send"
      />
    </footer>

    <BlockDialog
      v-model="blockOpen"
      :label="title"
      :busy="blockBusy"
      @confirm="setBlocked(true)"
    />
    <q-dialog v-model="noticeOpen">
      <div
        class="dialog-card"
        role="alertdialog"
        aria-labelledby="share-notice-title"
      >
        <h3 id="share-notice-title">{{ $t("thread.shareNoticeTitle") }}</h3>
        <p class="text-secondary-copy">{{ $t("thread.shareNotice") }}</p>
        <div class="dialog-actions">
          <AppButton variant="ghost" size="sm" v-close-popup>{{
            $t("thread.shareCancel")
          }}</AppButton>
          <AppButton size="sm" @click="confirmNotice">{{
            $t("thread.shareContinue")
          }}</AppButton>
        </div>
      </div>
    </q-dialog>
    <ReportDialog
      v-model="reportOpen"
      :busy="reportBusy"
      :error="reportError"
      @submit="submitReport"
    />
  </section>
</template>

<style scoped lang="scss">
.pane {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  /* Transparent so the shell's canvas wash runs behind the thread. */
  background: transparent;
}
.head {
  display: flex;
  align-items: center;
  gap: var(--s-2);
  padding: 10px var(--s-4);
  background: color-mix(in srgb, var(--canvas) 86%, transparent);
  backdrop-filter: blur(10px);
  border-bottom: 2px solid var(--border);
}
.head :deep(.avatar) {
  border: 0;
  background: var(--grad-pink);
  color: #fff;
  font-family: var(--font-display);
  font-weight: 800;
}
.who {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 1px;
}
.sub {
  overflow: hidden;
  color: var(--text-3);
  font-family: var(--font-mono, var(--font-sans));
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.title {
  min-width: 0;
  overflow: hidden;
  font-family: var(--font-display);
  font-size: 1.02rem;
  font-weight: 800;
  letter-spacing: -0.01em;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.back {
  margin-left: calc(var(--s-2) * -1);
}
.scroll {
  display: flex;
  flex: 1;
  flex-direction: column;
  /* A short conversation sits near the composer, like a chat should. */
  justify-content: flex-end;
  gap: var(--s-4);
  min-height: 0;
  padding: var(--s-4);
  overflow-y: auto;
  overscroll-behavior: contain;
}
.group {
  display: grid;
  gap: var(--s-2);
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
.older {
  display: flex;
  justify-content: center;
}
.state {
  display: grid;
  gap: var(--s-3);
  justify-items: center;
  margin: auto;
  text-align: center;
}
.foot {
  padding: var(--s-3) var(--s-4) calc(var(--s-3) + env(safe-area-inset-bottom));
  background: color-mix(in srgb, var(--canvas) 86%, transparent);
  backdrop-filter: blur(10px);
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
.menu {
  min-width: 200px;
}
.skeleton {
  display: grid;
  gap: var(--s-3);
}
.sk {
  height: 44px;
  width: 55%;
  border-radius: 18px;
  background: var(--surface-hover);
  animation: pulse 1.2s ease-in-out infinite;
}
.sk.mine {
  justify-self: end;
  width: 40%;
}
.sk.short {
  width: 30%;
}
@keyframes pulse {
  50% {
    opacity: 0.5;
  }
}
</style>
