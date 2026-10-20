<script setup lang="ts">
import { useId } from "vue";

defineProps<{
  label: string;
  hint?: string;
  error?: string;
  type?: string;
  placeholder?: string;
  autocomplete?: string;
  inputmode?: "text" | "numeric" | "search";
  maxlength?: number;
  hideLabel?: boolean;
}>();
const model = defineModel<string>({ default: "" });
const id = useId();
</script>

<template>
  <div class="grid gap-1.5">
    <label :for="id" :class="hideLabel ? 'sr-only-keep' : 'text-sm font-medium'">{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      :type="type ?? 'text'"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :inputmode="inputmode"
      :maxlength="maxlength"
      :aria-invalid="Boolean(error)"
      :aria-describedby="error || hint ? `${id}-help` : undefined"
      class="min-h-11 w-full rounded-control border border-line-strong bg-canvas px-3 text-base text-fg placeholder:text-fg-3 aria-[invalid=true]:border-danger"
    />
    <p
      v-if="error || hint"
      :id="`${id}-help`"
      class="text-xs"
      :class="error ? 'text-danger' : 'text-fg-3'"
      :role="error ? 'alert' : undefined"
    >
      {{ error || hint }}
    </p>
  </div>
</template>
