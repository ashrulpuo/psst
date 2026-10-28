<script setup lang="ts">
import AppButton from "@/components/AppButton.vue";

defineProps<{ modelValue: boolean; label: string; busy: boolean }>();
defineEmits<{ "update:modelValue": [value: boolean]; confirm: [] }>();
</script>

<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="dialog-card" role="alertdialog" aria-labelledby="block-title">
      <h3 id="block-title">Block {{ label }}?</h3>
      <p class="text-secondary-copy">
        They won't be able to send you any more messages, and they'll see that
        they can't reply. You can unblock them any time.
      </p>
      <div class="dialog-actions">
        <AppButton variant="ghost" size="sm" v-close-popup>Cancel</AppButton>
        <AppButton
          variant="danger"
          size="sm"
          :disabled="busy"
          @click="$emit('confirm')"
        >
          Block sender
        </AppButton>
      </div>
    </div>
  </q-dialog>
</template>
