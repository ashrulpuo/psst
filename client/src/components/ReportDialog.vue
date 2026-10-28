<script setup lang="ts">
import { computed, ref, watch } from "vue";
import AppButton from "@/components/AppButton.vue";

const props = defineProps<{
  modelValue: boolean;
  busy: boolean;
  error: string;
}>();
const emit = defineEmits<{
  "update:modelValue": [value: boolean];
  submit: [reason: string];
}>();

const reason = ref("");
const valid = computed(() => reason.value.trim().length >= 3);

watch(
  () => props.modelValue,
  open => open && (reason.value = "")
);
</script>

<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- Quasar only re-enables pointer events on a direct <div> child. -->
    <div class="dialog-card" role="dialog" aria-labelledby="report-title">
      <form @submit.prevent="valid && emit('submit', reason.trim())">
        <h3 id="report-title">{{ $t("thread.reportTitle") }}</h3>
        <div class="field">
          <label for="report-reason">{{ $t("thread.reportReason") }}</label>
          <textarea
            id="report-reason"
            v-model="reason"
            class="input"
            rows="3"
            maxlength="500"
          />
          <p v-if="error" class="error" role="alert">{{ error }}</p>
        </div>
        <div class="dialog-actions">
          <AppButton variant="ghost" size="sm" v-close-popup>Cancel</AppButton>
          <AppButton type="submit" size="sm" :disabled="!valid || busy">
            {{ $t("thread.reportSubmit") }}
          </AppButton>
        </div>
      </form>
    </div>
  </q-dialog>
</template>

<style scoped lang="scss">
form {
  display: grid;
  gap: var(--s-4);
}
.field {
  display: grid;
  gap: var(--s-2);
}
label {
  font-size: var(--text-secondary);
  font-weight: 500;
}
.input {
  padding: 10px 12px;
  resize: vertical;
  border: 2px solid var(--edge);
  border-radius: var(--r-control);
  background: var(--cream);
  color: var(--text);
  font: inherit;
}
.input:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 2px;
}
.error {
  color: var(--danger);
  font-size: var(--text-secondary);
}
</style>
