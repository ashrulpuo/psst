<script setup lang="ts">
import { ref } from "vue";
import type { Reaction } from "@/api/types";

defineProps<{ reactions: Reaction[]; disabled?: boolean }>();
defineEmits<{ react: [emoji: string] }>();

const PICKER = ["🔥", "❤️", "😂", "👍", "😮", "😢"];
const open = ref(false);
</script>

<template>
  <div class="bar">
    <button
      v-for="reaction in reactions"
      :key="reaction.emoji"
      type="button"
      class="chip"
      :class="{ mine: reaction.mine }"
      :aria-pressed="reaction.mine"
      :disabled="disabled"
      @click="$emit('react', reaction.emoji)"
    >
      <span aria-hidden="true">{{ reaction.emoji }}</span>
      <span class="count">{{ reaction.count }}</span>
    </button>

    <div v-if="!disabled" class="add">
      <button
        type="button"
        class="chip plus"
        :aria-expanded="open"
        :aria-label="$t('thread.react')"
        @click="open = !open"
      >
        <span aria-hidden="true">🙂+</span>
      </button>
      <div v-if="open" class="picker">
        <button
          v-for="emoji in PICKER"
          :key="emoji"
          type="button"
          class="pick"
          @click="
            $emit('react', emoji);
            open = false;
          "
        >
          {{ emoji }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 5px;
  margin-top: 5px;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 30px;
  padding: 0 9px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--surface);
  box-shadow: 0 2px 0 var(--edge);
  font-size: 0.78rem;
  cursor: pointer;
}
.chip.mine {
  background: var(--cream);
}
.chip:active {
  transform: translateY(2px);
  box-shadow: none;
}
.chip:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 2px;
}
.count {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 0.7rem;
}
.add {
  position: relative;
}
.plus {
  color: var(--text-3);
}
.picker {
  position: absolute;
  z-index: 5;
  bottom: calc(100% + 6px);
  left: 0;
  display: flex;
  gap: 2px;
  padding: 6px;
  border: 2px solid var(--edge);
  border-radius: 14px;
  background: var(--surface);
  box-shadow: var(--edge-shadow-sm);
}
.pick {
  border: 0;
  border-radius: 8px;
  background: transparent;
  font-size: 1.1rem;
  cursor: pointer;
}
.pick:hover {
  background: var(--cream);
}
</style>
