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
  width: 52px;
  height: 52px;
  border: 2.5px solid var(--edge);
  border-radius: 16px;
  background: var(--cream);
  box-shadow: 0 4px 0 var(--edge);
  color: var(--text);
  transform: rotate(-4deg);
}
.empty-title {
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.empty-action {
  margin-top: var(--s-3);
}
</style>
