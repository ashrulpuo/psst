<script setup lang="ts">
import { Flag, RotateCw, Trash2 } from "@lucide/vue";
import type { Message } from "@/api/types";
import { clockTime } from "@/utils/format";

defineProps<{ message: Message; canReport: boolean }>();
defineEmits<{
  report: [message: Message];
  retry: [message: Message];
  remove: [message: Message];
}>();
</script>

<template>
  <div class="row" :class="{ mine: message.mine }">
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
          }}<template v-if="message.mine && message.read_at"> · Read</template>
        </template>
      </span>
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
      v-else-if="canReport && !message.mine"
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
  align-items: center;
  gap: var(--s-1);
  max-width: 100%;
}
.row.mine {
  flex-direction: row-reverse;
}
.bubble {
  max-width: min(78%, 34rem);
  padding: var(--s-2) var(--s-4);
  border-radius: 18px 18px 18px 6px;
  background: var(--bubble-theirs);
  color: var(--text);
  overflow-wrap: anywhere;
}
.mine .bubble {
  border-radius: 18px 18px 6px 18px;
  background: var(--accent);
  color: var(--on-accent);
}
.mine .bubble .meta {
  color: color-mix(in srgb, var(--on-accent) 78%, transparent);
}
.body {
  white-space: pre-wrap;
}
.meta {
  display: block;
  margin-top: 2px;
}
.pending {
  opacity: 0.7;
}
.failed {
  outline: 1px solid var(--danger);
}
// Quiet until focused or hovered, but always in the DOM and reachable by touch and keyboard.
.report {
  color: var(--text-3);
  opacity: 0.55;
  transition: opacity var(--dur) var(--ease);
}
.report:hover,
.report:focus-visible {
  opacity: 1;
}
</style>
