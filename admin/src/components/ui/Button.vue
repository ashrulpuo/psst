<script setup lang="ts">
import { computed } from "vue";
import { cva } from "class-variance-authority";
import { LoaderCircle } from "@lucide/vue";
import { cn } from "@/lib/cn";

const variants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control text-sm font-medium transition-[background-color,transform] duration-150 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 cursor-pointer",
  {
    variants: {
      variant: {
        primary: "bg-accent text-on-accent hover:bg-accent-hover",
        outline: "border border-line-strong bg-surface text-fg hover:bg-surface-2",
        ghost: "text-fg-2 hover:bg-surface-2 hover:text-fg",
        danger: "bg-danger text-white hover:opacity-90 dark:text-[#16161c]",
      },
      size: {
        md: "min-h-11 px-4",
        sm: "min-h-9 px-3",
        icon: "size-11",
      },
    },
    defaultVariants: { variant: "primary", size: "md" },
  },
);

const props = withDefaults(
  defineProps<{
    variant?: "primary" | "outline" | "ghost" | "danger";
    size?: "md" | "sm" | "icon";
    loading?: boolean;
    type?: "button" | "submit";
    class?: string;
  }>(),
  { variant: "primary", size: "md", loading: false, type: "button", class: "" },
);
const classes = computed(() =>
  cn(variants({ variant: props.variant, size: props.size }), props.class),
);
</script>

<template>
  <button
    :type="type"
    :class="classes"
    :disabled="loading || undefined"
    :aria-busy="loading || undefined"
  >
    <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
    <slot />
  </button>
</template>
