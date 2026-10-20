<script setup lang="ts">
import { computed } from "vue";
import type { InboxThread, SentThread } from "@/api/types";
import { shortTime } from "@/utils/format";
import AppAvatar from "./AppAvatar.vue";

const props = defineProps<{
  thread: InboxThread | SentThread;
  box: "inbox" | "sent";
  active: boolean;
}>();

const title = computed(() =>
  "receiver" in props.thread ? props.thread.receiver.name : props.thread.label
);
const avatar = computed(() =>
  "receiver" in props.thread ? props.thread.receiver.avatar_url : null
);
const preview = computed(() => {
  const last = props.thread.last_message;
  if (!last) return "";
  return last.mine ? `You: ${last.body}` : last.body;
});
const unread = computed(() => props.thread.unread_count > 0);
</script>

<template>
  <router-link
    :to="`/${box}/${thread.id}`"
    class="item"
    :class="{ active, unread }"
    :aria-current="active ? 'page' : undefined"
  >
    <AppAvatar :name="title" :src="avatar" :size="44" />
    <span class="main">
      <span class="top">
        <span class="title">{{ title }}</span>
        <time
          class="text-meta"
          :datetime="thread.last_message_at ?? undefined"
          >{{ shortTime(thread.last_message_at) }}</time
        >
      </span>
      <span class="preview text-secondary-copy">{{ preview }}</span>
    </span>
    <span v-if="unread" class="dot" aria-hidden="true" />
    <span v-if="unread" class="sr-only">{{ thread.unread_count }} unread</span>
  </router-link>
</template>

<style scoped lang="scss">
.item {
  display: flex;
  align-items: center;
  gap: var(--s-3);
  min-height: 72px;
  padding: var(--s-3) var(--s-4);
  color: var(--text);
  text-decoration: none;
  transition: background-color var(--dur) var(--ease);
}
.item:hover {
  background: var(--surface-hover);
}
.item.active {
  background: var(--accent-soft);
}
.main {
  display: grid;
  flex: 1;
  min-width: 0;
  gap: 2px;
}
.top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--s-2);
}
.title {
  overflow: hidden;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.unread .title {
  font-weight: 600;
}
.preview {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.unread .preview {
  color: var(--text);
}
.dot {
  flex: none;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--accent);
}
</style>
