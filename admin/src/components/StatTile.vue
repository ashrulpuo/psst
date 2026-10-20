<script setup lang="ts">
// Stat tile contract: label (sentence case) · value · optional hint. One tile per view may be the hero figure.
import { formatCompact } from "@/lib/format";

defineProps<{
  label: string;
  value: number;
  hint?: string;
  hero?: boolean;
  tone?: "default" | "danger";
}>();
</script>

<template>
  <div class="surface grid content-start gap-1 p-5" :class="hero ? 'sm:col-span-2' : ''">
    <p class="text-sm text-fg-2">{{ label }}</p>
    <!-- proportional figures for big standalone numbers; tabular is for columns -->
    <p
      class="font-semibold leading-none"
      :class="[
        hero ? 'text-5xl' : 'text-3xl',
        tone === 'danger' && value > 0 ? 'text-danger' : 'text-fg',
      ]"
    >
      {{ formatCompact(value) }}
    </p>
    <p v-if="hint" class="text-xs text-fg-3">{{ hint }}</p>
  </div>
</template>
