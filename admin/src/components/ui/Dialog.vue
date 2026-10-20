<script setup lang="ts">
import {
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from "reka-ui";

defineProps<{ title: string; description?: string }>();
const open = defineModel<boolean>("open", { required: true });
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-40 bg-black/50" />
      <!-- reka-ui traps focus, restores it on close, and closes on Escape. -->
      <DialogContent
        class="surface fixed top-1/2 left-1/2 z-50 grid w-[min(92vw,30rem)] max-h-[90dvh] -translate-x-1/2 -translate-y-1/2 gap-4 overflow-y-auto p-6"
      >
        <div class="grid gap-1">
          <DialogTitle class="text-lg font-semibold">{{ title }}</DialogTitle>
          <DialogDescription v-if="description" class="text-sm text-fg-2">{{
            description
          }}</DialogDescription>
        </div>
        <slot />
        <div v-if="$slots.actions" class="flex flex-wrap justify-end gap-2">
          <slot name="actions" />
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
