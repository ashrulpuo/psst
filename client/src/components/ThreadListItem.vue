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
      :style="{
        '--x': `${swipe.offset}px`,
        '--card': storyGradient(thread.id)
      }"
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
