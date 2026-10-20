<script setup lang="ts">
import { computed } from "vue";
import { initials } from "@/lib/format";

const props = withDefaults(
  defineProps<{ name: string; src?: string | null | undefined; size?: number }>(),
  { src: null, size: 36 },
);
const label = computed(() => initials(props.name));
</script>

<template>
  <span
    class="inline-grid shrink-0 place-items-center overflow-hidden rounded-full border border-line bg-accent-soft font-semibold text-accent-text"
    :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.36)}px` }"
  >
    <img
      v-if="src"
      :src="src"
      alt=""
      loading="lazy"
      referrerpolicy="no-referrer"
      class="size-full object-cover"
    />
    <span v-else aria-hidden="true">{{ label }}</span>
  </span>
</template>
