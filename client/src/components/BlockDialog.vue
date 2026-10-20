<script setup lang="ts">
defineProps<{ modelValue: boolean; label: string; busy: boolean }>();
defineEmits<{ "update:modelValue": [value: boolean]; confirm: [] }>();
</script>

<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div
      class="surface dialog"
      role="alertdialog"
      aria-labelledby="block-title"
    >
      <h3 id="block-title">Block {{ label }}?</h3>
      <p class="text-secondary-copy">
        They won't be able to send you any more messages, and they'll see that
        they can't reply. You can unblock them any time.
      </p>
      <div class="actions">
        <q-btn flat no-caps label="Cancel" v-close-popup />
        <q-btn
          unelevated
          no-caps
          color="negative"
          label="Block sender"
          :loading="busy"
          @click="$emit('confirm')"
        />
      </div>
    </div>
  </q-dialog>
</template>

<style scoped lang="scss">
.dialog {
  display: grid;
  gap: var(--s-3);
  width: min(92vw, 420px);
  padding: var(--s-5);
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--s-2);
  margin-top: var(--s-2);
}
</style>
