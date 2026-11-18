<script setup lang="ts">
import { Flag, RotateCw, Share2, Trash2 } from "@lucide/vue";
import type { Message } from "@/api/types";
import { clockTime } from "@/utils/format";
import ReactionBar from "@/components/ReactionBar.vue";

withDefaults(
  defineProps<{
    message: Message;
    canReport: boolean;
    reactionsDisabled?: boolean;
    canShare?: boolean;
    sharing?: boolean;
  }>(),
  { reactionsDisabled: false, canShare: false, sharing: false }
);

defineEmits<{
  report: [message: Message];
  retry: [message: Message];
  remove: [message: Message];
  react: [message: Message, emoji: string];
  share: [message: Message];
}>();
</script>

<template>
  <div class="row" :class="{ mine: message.mine }">
    <div class="stack">
      <div
        class="bubble"
        :class="{ pending: message.pending, failed: message.failed }"
      >
        <p class="body">{{ message.body }}</p>
        <span class="text-meta meta">
          <template v-if="message.failed">{{ $t("thread.notSent") }}</template>
          <template v-else-if="message.pending">{{
            $t("thread.sending")
          }}</template>
          <template v-else>
            {{ clockTime(message.created_at)
            }}<template v-if="message.mine && message.read_at">
              · Read</template
            >
          </template>
        </span>
      </div>

      <ReactionBar
        :reactions="message.reactions"
        :disabled="message.mine || reactionsDisabled"
        @react="$emit('react', message, $event)"
      />
    </div>

    <template v-if="message.failed">
      <q-btn
        flat
        round
        dense
        class="hit"
        :aria-label="$t('thread.retry')"
        @click="$emit('retry', message)"
      >
        <RotateCw :size="18" />
      </q-btn>
      <q-btn
        flat
        round
        dense
        class="hit"
        :aria-label="$t('thread.remove')"
        @click="$emit('remove', message)"
      >
        <Trash2 :size="18" />
      </q-btn>
    </template>
    <q-btn
      v-if="canShare && !message.mine && !message.pending && !message.failed"
      flat
      round
      dense
      class="hit share"
      :disable="sharing"
      :aria-label="$t('thread.shareStory')"
      @click="$emit('share', message)"
    >
      <Share2 :size="18" />
    </q-btn>
    <q-btn
      v-if="canReport && !message.mine && !message.failed"
      flat
      round
      dense
      class="hit report"
      :aria-label="$t('thread.report')"
      @click="$emit('report', message)"
    >
      <Flag :size="16" />
    </q-btn>
  </div>
</template>

<style scoped lang="scss">
.row {
  display: flex;
  align-items: flex-end;
  gap: var(--s-1);
  max-width: 100%;
}
.row.mine {
  flex-direction: row-reverse;
}
.stack {
  display: grid;
  justify-items: start;
  gap: 4px;
  max-width: min(80%, 34rem);
}
.mine .stack {
  justify-items: end;
}
.bubble {
  max-width: 100%;
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
// Quiet until focused or hovered, but always in the DOM and reachable by touch and keyboard.
.report {
  width: 28px;
  height: 28px;
  min-width: 28px;
  min-height: 28px;
  border: 2px solid var(--border);
  border-radius: 50%;
  background: var(--surface);
  color: var(--text-3);
  opacity: 0.7;
  transition: opacity var(--dur) var(--ease);
}
.share {
  width: 44px;
  height: 44px;
  min-width: 44px;
  min-height: 44px;
  color: var(--text-2);
}
.report:hover,
.report:focus-visible {
  opacity: 1;
}
</style>
