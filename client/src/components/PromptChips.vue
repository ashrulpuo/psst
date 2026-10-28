<script setup lang="ts">
defineProps<{ prompts: string[] }>();
defineEmits<{ pick: [text: string] }>();
</script>

<template>
  <div class="chips" role="group" :aria-label="$t('profile.promptsLabel')">
    <button
      v-for="prompt in prompts"
      :key="prompt"
      type="button"
      class="chip"
      @click="$emit('pick', prompt)"
    >
      {{ prompt }}
    </button>
  </div>
</template>

<style scoped lang="scss">
.chips {
  display: flex;
  gap: 6px;
  width: 100%;
  padding: 2px 22px 6px;
  margin-inline: -22px;
  overflow-x: auto;
  scrollbar-width: none;
  /* Fade the cut-off edge so a scrolling row does not look broken. */
  mask-image: linear-gradient(90deg, #000 86%, transparent 100%);
}
.chips::-webkit-scrollbar {
  display: none;
}
.chip {
  flex: none;
  min-height: 34px;
  padding: 0 11px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--surface);
  box-shadow: 0 3px 0 var(--edge);
  color: var(--text);
  font: 700 0.74rem / 1 var(--font-sans);
  cursor: pointer;
  transition:
    transform 120ms var(--ease),
    box-shadow 120ms var(--ease),
    background-color var(--dur) var(--ease);
}
.chip:hover {
  background: var(--cream);
}
.chip:active {
  transform: translateY(3px);
  box-shadow: 0 0 0 var(--edge);
}
.chip:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 2px;
}
</style>
