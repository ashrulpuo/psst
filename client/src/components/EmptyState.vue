<script setup lang="ts">
import type { Component } from "vue";

defineProps<{ icon: Component; title: string; body?: string }>();
</script>

<template>
  <div class="empty" role="status">
    <span class="empty-icon" aria-hidden="true"
      ><component :is="icon" :size="24"
    /></span>
    <h3 class="empty-title">{{ title }}</h3>
    <p v-if="body" class="text-secondary-copy">{{ body }}</p>
    <div v-if="$slots.default" class="empty-action"><slot /></div>
  </div>
</template>

<style scoped lang="scss">
.empty {
  display: grid;
  justify-items: center;
  gap: var(--s-2);
  padding: var(--s-6) var(--s-4);
  text-align: center;
  max-width: 360px;
  margin-inline: auto;
}
.empty-icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: var(--accent-soft);
  color: var(--accent-text);
}
.empty-title {
  font-size: var(--text-body);
  font-weight: 600;
}
.empty-action {
  margin-top: var(--s-3);
}
</style>
