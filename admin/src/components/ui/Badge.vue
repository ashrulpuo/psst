<script setup lang="ts">
import { computed } from "vue";
import { cn } from "@/lib/cn";

const props = withDefaults(
  defineProps<{ tone?: "neutral" | "accent" | "ok" | "warn" | "danger"; class?: string }>(),
  {
    tone: "neutral",
    class: "",
  },
);

const tones = {
  neutral: "bg-surface-2 text-fg-2",
  accent: "bg-accent-soft text-accent-text",
  ok: "bg-ok-soft text-ok",
  warn: "bg-warn-soft text-warn",
  danger: "bg-danger-soft text-danger",
} as const;
const classes = computed(() =>
  cn(
    "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap",
    tones[props.tone],
    props.class,
  ),
);
</script>

<template>
  <span :class="classes"><slot /></span>
</template>
