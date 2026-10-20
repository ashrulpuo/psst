<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from "vue";
import { SendHorizontal } from "@lucide/vue";

const MAX = 1000;

const props = withDefaults(
  defineProps<{
    label: string;
    placeholder?: string;
    submitLabel: string;
    busy?: boolean;
    disabled?: boolean;
    modelValue?: string;
  }>(),
  { placeholder: "", busy: false, disabled: false, modelValue: "" }
);
const emit = defineEmits<{
  submit: [body: string];
  "update:modelValue": [value: string];
}>();

const text = ref(props.modelValue);
const field = useTemplateRef<HTMLTextAreaElement>("field");

watch(
  () => props.modelValue,
  v => (text.value = v)
);
watch(text, v => emit("update:modelValue", v));

const trimmed = computed(() => text.value.trim());
const canSend = computed(
  () =>
    trimmed.value.length > 0 &&
    trimmed.value.length <= MAX &&
    !props.disabled &&
    !props.busy
);
const remaining = computed(() => MAX - text.value.length);

function grow() {
  const el = field.value;
  if (!el) return;
  el.style.height = "auto";
  el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
}
watch(text, () => void nextTick(grow));

function submit() {
  if (!canSend.value) return;
  emit("submit", trimmed.value);
}

// Enter sends on devices with a keyboard; on touch screens Enter makes a new line and the button sends.
const hasKeyboard =
  typeof window !== "undefined" &&
  window.matchMedia("(hover: hover) and (pointer: fine)").matches;

function onKeydown(e: KeyboardEvent) {
  if (e.key !== "Enter" || e.isComposing) return;
  if (e.metaKey || e.ctrlKey || (hasKeyboard && !e.shiftKey)) {
    e.preventDefault();
    submit();
  }
}

function clear() {
  text.value = "";
  void nextTick(grow);
}
defineExpose({ clear, focus: () => field.value?.focus() });
</script>

<template>
  <form class="composer" @submit.prevent="submit">
    <label class="sr-only" :for="`composer-${label}`">{{ label }}</label>
    <textarea
      :id="`composer-${label}`"
      ref="field"
      v-model="text"
      class="field"
      rows="1"
      :placeholder="placeholder"
      :disabled="disabled"
      :maxlength="MAX + 50"
      enterkeyhint="send"
      @keydown="onKeydown"
      @input="grow"
    />
    <span
      v-if="remaining < 100"
      class="text-meta count"
      :class="{ over: remaining < 0 }"
      aria-live="polite"
      >{{ remaining }}</span
    >
    <q-btn
      type="submit"
      round
      unelevated
      color="primary"
      class="send hit"
      :disable="!canSend"
      :loading="busy"
      :aria-label="submitLabel"
    >
      <SendHorizontal :size="20" />
    </q-btn>
  </form>
</template>

<style scoped lang="scss">
.composer {
  position: relative;
  display: flex;
  align-items: flex-end;
  gap: var(--s-2);
}
.field {
  flex: 1;
  min-height: 48px;
  max-height: 160px;
  padding: 11px var(--s-4);
  resize: none;
  border: 1px solid var(--border-strong);
  border-radius: 24px;
  background: var(--surface);
  color: var(--text);
  font: inherit;
  line-height: 1.5;
  transition: border-color var(--dur) var(--ease);
}
.field::placeholder {
  color: var(--text-3);
}
.field:focus-visible {
  outline: 2px solid var(--accent-text);
  outline-offset: 1px;
  border-color: var(--accent-text);
}
.field:disabled {
  opacity: 0.6;
}
.count {
  position: absolute;
  right: 60px;
  bottom: -18px;
}
.count.over {
  color: var(--danger);
}
.send {
  flex: none;
  width: 48px;
  height: 48px;
}
</style>
