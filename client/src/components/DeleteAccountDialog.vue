<script setup lang="ts">
import { computed, ref, watch } from "vue";

const props = defineProps<{
  modelValue: boolean;
  /** What the user must type: their username, or email before they have one. */
  expected: string;
  busy: boolean;
  /** Server-side message to show under the field. */
  error: string;
}>();
const emit = defineEmits<{
  "update:modelValue": [value: boolean];
  confirm: [typed: string];
}>();

const typed = ref("");
const matches = computed(
  () => typed.value.trim().toLowerCase() === props.expected.toLowerCase()
);
watch(
  () => props.modelValue,
  open => {
    if (open) typed.value = "";
  }
);

function submit() {
  if (matches.value && !props.busy) emit("confirm", typed.value.trim());
}
</script>

<template>
  <q-dialog
    :model-value="modelValue"
    :persistent="busy"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- Quasar only re-enables pointer events on a direct <div> child, so the form lives inside one. -->
    <div
      class="surface dialog"
      role="alertdialog"
      aria-labelledby="del-title"
      aria-describedby="del-body"
    >
      <form class="form" @submit.prevent="submit">
        <h3 id="del-title">{{ $t("settings.deleteTitle") }}</h3>
        <p id="del-body" class="text-secondary-copy">
          {{ $t("settings.deleteBody") }}
        </p>
        <label class="field">
          <span>{{ $t("settings.deleteLabel", { name: expected }) }}</span>
          <input
            v-model="typed"
            type="text"
            autocomplete="off"
            autocapitalize="none"
            spellcheck="false"
            :aria-invalid="error ? 'true' : 'false'"
          />
        </label>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <div class="actions">
          <q-btn flat no-caps label="Cancel" :disable="busy" v-close-popup />
          <q-btn
            type="submit"
            unelevated
            no-caps
            color="negative"
            :label="$t('settings.deleteConfirm')"
            :loading="busy"
            :disable="!matches"
          />
        </div>
      </form>
    </div>
  </q-dialog>
</template>

<style scoped lang="scss">
.dialog {
  width: min(92vw, 420px);
  padding: var(--s-5);
}
.form {
  display: grid;
  gap: var(--s-3);
}
.field {
  display: grid;
  gap: var(--s-2);
  font-size: var(--text-secondary);
  color: var(--text-2);
}
.field input {
  min-height: 44px;
  padding: 0 var(--s-3);
  border: 1px solid var(--border);
  border-radius: var(--r-control);
  background: var(--canvas);
  color: var(--text);
  font: inherit;
}
.field input:focus-visible {
  outline: 2px solid var(--accent);
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
  margin-top: var(--s-2);
}
</style>
