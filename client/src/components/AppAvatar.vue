<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    name: string;
    src?: string | null | undefined;
    size?: number;
  }>(),
  {
    src: null,
    size: 40
  }
);

// "Anonymous #3" → "3"; "Alice Rahman" → "AR".
const initials = computed(() => {
  const anon = props.name.match(/#(\d+)/);
  if (anon) return anon[1];
  return props.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0]?.toUpperCase())
    .join("");
});
</script>

<template>
  <span
    class="avatar"
    :style="{
      width: `${size}px`,
      height: `${size}px`,
      fontSize: `${Math.round(size * 0.36)}px`
    }"
  >
    <img
      v-if="src"
      :src="src"
      alt=""
      loading="lazy"
      referrerpolicy="no-referrer"
    />
    <span v-else aria-hidden="true">{{ initials }}</span>
  </span>
</template>

<style scoped lang="scss">
.avatar {
  flex: none;
  display: inline-grid;
  place-items: center;
  border-radius: 999px;
  overflow: hidden;
  background: var(--accent-soft);
  color: var(--accent-text);
  font-weight: 600;
  border: 1px solid var(--border);
}
img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
