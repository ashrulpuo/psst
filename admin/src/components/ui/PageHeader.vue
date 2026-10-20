<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
  <header class="flex flex-wrap items-end justify-between gap-3">
    <div class="grid gap-1">
      <h1>{{ title }}</h1>
      <p v-if="subtitle" class="text-sm text-fg-2">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.default" class="flex flex-wrap items-center gap-2"><slot /></div>
  </header>
</template>
