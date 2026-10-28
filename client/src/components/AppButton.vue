<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    variant?: "primary" | "ghost" | "lime";
    size?: "md" | "sm";
    block?: boolean;
    to?: string;
    type?: "button" | "submit";
    disabled?: boolean;
  }>(),
  {
    variant: "primary",
    size: "md",
    block: false,
    type: "button",
    disabled: false
  }
);

const tag = computed(() => (props.to ? "router-link" : "button"));
</script>

<template>
  <component
    :is="tag"
    class="app-btn press"
    :class="[`is-${variant}`, `is-${size}`, { 'is-block': block }]"
    :to="to"
    :type="to ? undefined : type"
    :disabled="to ? undefined : disabled"
  >
    <slot />
  </component>
</template>

<style scoped lang="scss">
.app-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 52px;
  padding: 0 20px;
  border-radius: var(--r-sticker);
  font-family: var(--font-display);
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.01em;
  text-decoration: none;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}
.app-btn.is-md {
  min-height: 56px;
  font-size: 1.12rem;
}
.app-btn.is-sm {
  min-height: 46px;
  font-size: 0.95rem;
  border-width: 2px;
  border-radius: var(--r-control);
}
.app-btn.is-primary {
  background: var(--accent-strong);
  color: var(--on-accent);
}
.app-btn.is-primary:hover {
  background: var(--accent-hover);
}
.app-btn.is-ghost {
  background: var(--surface);
  color: var(--text);
}
.app-btn.is-ghost:hover {
  background: var(--surface-hover);
}
.app-btn.is-lime {
  background: var(--lime);
  color: var(--ink);
}
.app-btn.is-block {
  width: 100%;
}
.app-btn[disabled] {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}
.app-btn:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 3px;
}
</style>
