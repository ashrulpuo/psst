<script setup lang="ts">
import { computed, ref, watch } from "vue";

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
    <form
      class="surface dialog"
      @submit.prevent="valid && emit('submit', reason.trim())"
    >
      <h3>{{ $t("thread.reportTitle") }}</h3>
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
      <div class="actions">
        <q-btn flat no-caps label="Cancel" v-close-popup />
        <q-btn
          type="submit"
          unelevated
          no-caps
          color="primary"
          :label="$t('thread.reportSubmit')"
          :loading="busy"
          :disable="!valid"
        />
      </div>
    </form>
  </q-dialog>
</template>

<style scoped lang="scss">
.dialog {
  display: grid;
  gap: var(--s-4);
  width: min(92vw, 420px);
  padding: var(--s-5);
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
  padding: var(--s-3) var(--s-4);
  resize: vertical;
  border: 1px solid var(--border-strong);
  border-radius: var(--r-control);
  background: var(--canvas);
  color: var(--text);
  font: inherit;
}
.input:focus-visible {
  outline: 2px solid var(--accent-text);
  outline-offset: 1px;
}
.error {
  color: var(--danger);
  font-size: var(--text-secondary);
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--s-2);
}
</style>
