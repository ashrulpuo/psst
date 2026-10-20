<script setup lang="ts">
import type { Component } from "vue";

defineProps<{ icon: Component; title: string; body?: string }>();
</script>

<template>
  <div
    class="mx-auto grid max-w-sm justify-items-center gap-2 px-4 py-12 text-center"
    role="status"
  >
    <span
      class="grid size-12 place-items-center rounded-2xl bg-accent-soft text-accent-text"
      aria-hidden="true"
    >
      <component :is="icon" class="size-6" />
    </span>
    <h3 class="font-semibold">{{ title }}</h3>
    <p v-if="body" class="text-sm text-fg-2">{{ body }}</p>
    <div v-if="$slots.default" class="mt-2"><slot /></div>
  </div>
</template>
