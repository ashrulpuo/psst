<script setup lang="ts">
// Loading / error wrapper that keeps the page frame: no layout jump, errors name a next step.
import { TriangleAlert } from "@lucide/vue";
import Button from "./Button.vue";

defineProps<{ loading: boolean; error: string | null; hasData: boolean }>();
defineEmits<{ retry: [] }>();
</script>

<template>
  <div
    v-if="error && !hasData"
    class="grid justify-items-center gap-3 px-4 py-12 text-center"
    role="alert"
  >
    <TriangleAlert class="size-6 text-danger" aria-hidden="true" />
    <p class="text-sm text-fg-2">{{ error }}</p>
    <Button variant="outline" @click="$emit('retry')">Try again</Button>
  </div>
  <div
    v-else
    :class="loading && hasData ? 'opacity-60 transition-opacity' : ''"
    :aria-busy="loading"
  >
    <slot />
  </div>
</template>
