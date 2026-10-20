<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from "vue";
import { useRouter } from "vue-router";
import { useQuasar } from "quasar";
import {
  ArrowLeft,
  EllipsisVertical,
  ShieldBan,
  ShieldCheck
} from "@lucide/vue";
import type { Message } from "@/api/types";
import { useMessagingStore } from "@/stores/messaging";
import { usePolling } from "@/composables/usePolling";
import { dayLabel } from "@/utils/format";
import AppAvatar from "@/components/AppAvatar.vue";
import MessageBubble from "@/components/MessageBubble.vue";
import MessageComposer from "@/components/MessageComposer.vue";
import BlockDialog from "@/components/BlockDialog.vue";
import ReportDialog from "@/components/ReportDialog.vue";

const props = defineProps<{ threadId: string; box: "inbox" | "sent" }>();

const $q = useQuasar();
const router = useRouter();
const store = useMessagingStore();

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

const blocked = computed(() => {
  const t = thread.value;
  return Boolean(t && "blocked" in t && t.blocked);
});
const cannotReply = computed(() => {
  const t = thread.value;
  return Boolean(t && "can_reply" in t && !t.can_reply);
});
const composerDisabled = computed(() => blocked.value || cannotReply.value);

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

usePolling(async () => {
  await store.refreshThread(props.threadId);
  void store.markRead(props.threadId);
}, 6000);

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
      position: "top"
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
      position: "top"
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
      position: "top"
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
      <h2 class="title">{{ title }}</h2>

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
            @report="reportTarget = $event"
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
  background: var(--canvas);
}
.head {
  display: flex;
  align-items: center;
  gap: var(--s-3);
  padding: var(--s-2) var(--s-4);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}
.title {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  font-size: var(--text-body);
  font-weight: 600;
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
  padding: 2px var(--s-3);
  border-radius: 999px;
  background: var(--surface);
  border: 1px solid var(--border);
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
  background: var(--canvas);
  border-top: 1px solid var(--border);
}
.notice {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: var(--s-2);
  padding: var(--s-3);
  border-radius: var(--r-control);
  background: var(--surface);
  border: 1px solid var(--border);
  font-size: var(--text-secondary);
  color: var(--text-2);
  text-align: center;
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
// Wide screens keep the list visible, so there is nowhere to go "back" to.
@media (min-width: 900px) {
  .back {
    display: none;
  }
}
</style>
