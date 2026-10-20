<script setup lang="ts">
// Pattern: Split Pane — thread list on the left, the open conversation on the right.
// Narrow screens show one or the other; wide screens show both.
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useQuasar, useMeta } from "quasar";
import { Inbox, Link2, Send } from "@lucide/vue";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";
import { copyText } from "@/utils/clipboard";
import EmptyState from "@/components/EmptyState.vue";
import ThreadListItem from "@/components/ThreadListItem.vue";
import ConversationPane from "@/features/messaging/ConversationPane.vue";

const props = defineProps<{ box: "inbox" | "sent" }>();

const route = useRoute();
const $q = useQuasar();
const auth = useAuthStore();
const store = useMessagingStore();

useMeta(() => ({
  title: `${props.box === "inbox" ? "Inbox" : "Sent"} · Psst`
}));

const threadId = computed(
  () => (route.params.threadId as string | undefined) ?? ""
);
const list = computed(() => (props.box === "inbox" ? store.inbox : store.sent));
const items = computed(() => list.value.items);

async function copyLink() {
  const ok = await copyText(auth.profileLink);
  $q.notify({
    type: ok ? "positive" : "warning",
    message: ok ? "Link copied" : auth.profileLink,
    position: "top"
  });
}
</script>

<template>
  <div class="split" :class="{ 'has-thread': threadId }">
    <section
      class="list-pane"
      :aria-label="box === 'inbox' ? $t('nav.inbox') : $t('nav.sent')"
    >
      <header class="list-head">
        <h1>{{ box === "inbox" ? $t("nav.inbox") : $t("nav.sent") }}</h1>
        <q-btn
          v-if="box === 'inbox'"
          flat
          round
          dense
          class="hit"
          :aria-label="$t('settings.copy')"
          @click="copyLink"
        >
          <Link2 :size="20" />
          <q-tooltip>{{ $t("settings.copy") }}</q-tooltip>
        </q-btn>
      </header>

      <div class="frame surface">
        <div
          v-if="list.loading && !list.loaded"
          class="skeleton-list"
          aria-hidden="true"
        >
          <div v-for="n in 5" :key="n" class="sk-row"
            ><span class="sk-av" /><span class="sk-lines"><i /><i /></span
          ></div>
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
            :label="$t('inbox.emptyAction')"
            @click="copyLink"
          />
        </EmptyState>

        <EmptyState
          v-else-if="!items.length"
          :icon="Send"
          :title="$t('sent.emptyTitle')"
          :body="$t('sent.emptyBody')"
        />

        <ul v-else class="threads">
          <li v-for="thread in items" :key="thread.id">
            <ThreadListItem
              :thread="thread"
              :box="box"
              :active="thread.id === threadId"
            />
          </li>
        </ul>

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
  --pane-height: calc(100dvh - var(--nav-height) - env(safe-area-inset-bottom));
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  width: 100%;
  max-width: var(--vp-wide);
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
  padding: var(--s-4);
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
.list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--s-3);
}
.list-head h1 {
  font-size: var(--text-h2);
}
.frame {
  flex: 1;
  min-height: 0;
  overflow: hidden auto;
}
.threads {
  margin: 0;
  padding: 0;
  list-style: none;
}
.threads li + li {
  border-top: 1px solid var(--border);
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

// Wide: both panes side by side.
@media (min-width: 900px) {
  .split {
    --pane-height: 100dvh;
    grid-template-columns: 380px minmax(0, 1fr);
  }
  .has-thread .list-pane,
  .list-pane {
    display: flex;
  }
  .thread-pane {
    display: block;
    border-left: 1px solid var(--border);
  }
}

.skeleton-list {
  display: grid;
}
.sk-row {
  display: flex;
  align-items: center;
  gap: var(--s-3);
  min-height: 72px;
  padding: var(--s-3) var(--s-4);
  border-bottom: 1px solid var(--border);
}
.sk-av {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--surface-hover);
}
.sk-lines {
  display: grid;
  flex: 1;
  gap: var(--s-2);
}
.sk-lines i {
  display: block;
  height: 10px;
  border-radius: 5px;
  background: var(--surface-hover);
}
.sk-lines i:last-child {
  width: 60%;
}
</style>
