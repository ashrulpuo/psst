<script setup lang="ts">
// One screen at a time, at every width. The list, or the open conversation.
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useMeta, useQuasar } from "quasar";
import { useI18n } from "vue-i18n";
import { Inbox, Send } from "@lucide/vue";
import { useMessagingStore } from "@/stores/messaging";
import type { InboxThread, SentThread } from "@/api/types";
import EmptyState from "@/components/EmptyState.vue";
import ThreadListItem from "@/components/ThreadListItem.vue";
import ConversationPane from "@/features/messaging/ConversationPane.vue";
import PushPrompt from "@/features/push/PushPrompt.vue";

const props = defineProps<{ box: "inbox" | "sent" }>();

const route = useRoute();
const store = useMessagingStore();

useMeta(() => ({
  title: `${props.box === "inbox" ? "Inbox" : "Sent"} · Psst`
}));

const threadId = computed(
  () => (route.params.threadId as string | undefined) ?? ""
);
const list = computed(() => (props.box === "inbox" ? store.inbox : store.sent));
const items = computed(() => list.value.items);
const unreadTotal = computed(() =>
  props.box === "inbox" ? store.inboxUnread : 0
);

const $q = useQuasar();
const { t } = useI18n();

async function hideThread(thread: InboxThread | SentThread) {
  try {
    await store.hideThread(thread.id);
    $q.notify({ message: t("thread.hidden"), position: "bottom" });
  } catch (e) {
    $q.notify({
      type: "negative",
      message: e instanceof Error ? e.message : t("errors.generic"),
      position: "bottom"
    });
  }
}
</script>

<template>
  <div class="split" :class="{ 'has-thread': threadId }">
    <section
      class="list-pane"
      :aria-label="box === 'inbox' ? $t('nav.inbox') : $t('nav.sent')"
    >
      <PushPrompt />

      <header class="list-head">
        <h1>{{ box === "inbox" ? $t("inbox.title") : $t("sent.title") }}</h1>
        <span v-if="unreadTotal" class="count-pill">{{
          $t("inbox.newCount", { n: unreadTotal })
        }}</span>
      </header>

      <div class="frame">
        <div
          v-if="list.loading && !list.loaded"
          class="skeleton-list"
          aria-hidden="true"
        >
          <div v-for="n in 3" :key="n" class="sk-card" />
        </div>

        <div v-else-if="list.error && !items.length" class="state" role="alert">
          <p>{{ list.error }}</p>
          <q-btn
            outline
            unelevated
            no-caps
            label="Try again"
            @click="box === 'inbox' ? store.loadInbox() : store.loadSent()"
          />
        </div>

        <EmptyState
          v-else-if="!items.length && box === 'inbox'"
          :icon="Inbox"
          :title="$t('inbox.emptyTitle')"
          :body="$t('inbox.emptyBody')"
        >
          <q-btn
            color="primary"
            unelevated
            no-caps
            to="/link"
            :label="$t('inbox.emptyAction')"
          />
        </EmptyState>

        <EmptyState
          v-else-if="!items.length"
          :icon="Send"
          :title="$t('sent.emptyTitle')"
          :body="$t('sent.emptyBody')"
        />

        <ul v-else class="threads" :key="box">
          <li v-for="thread in items" :key="thread.id">
            <ThreadListItem
              :thread="thread"
              :box="box"
              :active="thread.id === threadId"
              :can-hide="box === 'inbox'"
              @hide="hideThread"
            />
          </li>
        </ul>

        <router-link
          v-if="box === 'inbox' && !threadId && items.length"
          class="get"
          to="/link"
          >{{ $t("inbox.getMessages") }}</router-link
        >

        <div v-if="list.cursor" class="more">
          <q-btn
            flat
            no-caps
            label="Load more"
            :loading="list.loading"
            @click="
              box === 'inbox'
                ? store.loadInbox({ more: true })
                : store.loadSent({ more: true })
            "
          />
        </div>
      </div>
    </section>

    <div class="thread-pane">
      <ConversationPane
        v-if="threadId"
        :key="threadId"
        :thread-id="threadId"
        :box="box"
      />
      <div v-else class="pick">
        <EmptyState
          :icon="box === 'inbox' ? Inbox : Send"
          :title="$t('inbox.pick')"
          :body="$t('inbox.pickBody')"
        />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
// Narrow: exactly one pane. The page area is the viewport minus the bottom tab bar.
.split {
  --pane-height: calc(
    100dvh - var(--safe-top) - var(--nav-height) - env(safe-area-inset-bottom)
  );
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  width: 100%;
  height: var(--pane-height);
  margin-inline: auto;
}
.list-pane,
.thread-pane {
  min-width: 0;
  min-height: 0;
}
.list-pane {
  display: flex;
  flex-direction: column;
  padding: 4px 16px 140px;
  overflow-y: auto;
}
.thread-pane {
  display: none;
  height: 100%;
}
.has-thread .list-pane {
  display: none;
}
.has-thread .thread-pane {
  display: block;
}
@media (prefers-reduced-motion: no-preference) {
  .threads li {
    animation: psst-rise 420ms var(--ease) both;
  }
  @for $i from 1 through 12 {
    .threads li:nth-child(#{$i}) {
      animation-delay: #{30 + ($i - 1) * 45}ms;
    }
  }
  .has-thread .thread-pane {
    animation: psst-sheet 280ms var(--ease) both;
  }
}
.frame {
  flex: 1;
  min-height: 0;
  overflow: hidden auto;
}
.threads {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0 0 6px;
  list-style: none;
}
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
.get {
  position: fixed;
  z-index: 11;
  left: 50%;
  bottom: calc(84px + env(safe-area-inset-bottom));
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 0 26px;
  border-radius: 999px;
  background: var(--text);
  color: var(--canvas);
  font-family: var(--font-display);
  font-size: 1.05rem;
  font-weight: 800;
  text-decoration: none;
  transform: translateX(-50%);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
}
.get:focus-visible {
  outline: 3px solid var(--accent);
  outline-offset: 3px;
}
.more {
  display: flex;
  justify-content: center;
  padding: var(--s-2);
  border-top: 1px solid var(--border);
}
.state {
  display: grid;
  gap: var(--s-3);
  justify-items: center;
  padding: var(--s-6) var(--s-4);
  text-align: center;
}
.pick {
  display: grid;
  height: 100%;
  place-items: center;
}

.skeleton-list {
  display: grid;
  gap: var(--s-3);
}
.sk-card {
  height: 64px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(
    90deg,
    transparent 56px,
    var(--surface-hover) 56px
  );
}
</style>
