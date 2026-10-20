<script setup lang="ts">
// Pattern: Form (auth) — claim a username once.
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useMeta } from "quasar";
import { ApiError } from "@/api/http";
import { useAuthStore } from "@/stores/auth";
import { safeNext } from "@/features/auth/redirect";

useMeta({ title: "Choose a username · Psst" });

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const username = ref("");
const touched = ref(false);
const serverError = ref("");
const busy = ref(false);

const FORMAT = /^[a-z0-9_.]{3,24}$/;
const normalized = computed(() => username.value.trim().toLowerCase());
// Validate on blur first (not while typing); once shown, clear the moment the input is valid.
const formatError = computed(() =>
  touched.value && !FORMAT.test(normalized.value)
    ? "Use 3–24 lowercase letters, numbers, dots or underscores."
    : ""
);
const error = computed(() => formatError.value || serverError.value);
const preview = computed(
  () => `${window.location.host}/${normalized.value || "username"}`
);

async function submit() {
  touched.value = true;
  if (!FORMAT.test(normalized.value) || busy.value) return;
  busy.value = true;
  serverError.value = "";
  try {
    await auth.setUsername(normalized.value);
    await router.replace(safeNext(route.query.next) ?? "/inbox");
  } catch (e) {
    serverError.value =
      e instanceof ApiError
        ? (e.field("username") ?? e.message)
        : "Something went wrong. Please try again.";
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <form class="surface card" novalidate @submit.prevent="submit">
    <header class="head">
      <h1>{{ $t("welcome.title") }}</h1>
      <p class="text-secondary-copy">{{ $t("welcome.subtitle") }}</p>
    </header>

    <div class="field">
      <label for="username">{{ $t("welcome.label") }}</label>
      <input
        id="username"
        v-model="username"
        class="input"
        :class="{ invalid: error }"
        type="text"
        inputmode="text"
        autocomplete="username"
        autocapitalize="none"
        autocorrect="off"
        spellcheck="false"
        maxlength="24"
        :aria-invalid="Boolean(error)"
        aria-describedby="username-help"
        @blur="touched = true"
        @input="serverError = ''"
      />
      <p
        id="username-help"
        class="text-meta"
        :class="{ 'is-error': error }"
        :role="error ? 'alert' : undefined"
      >
        {{ error || $t("welcome.hint") }}
      </p>
    </div>

    <p class="preview text-secondary-copy">
      <span class="text-meta">{{ $t("welcome.preview") }}</span>
      <strong>{{ preview }}</strong>
    </p>

    <q-btn
      type="submit"
      color="primary"
      unelevated
      no-caps
      :loading="busy"
      :label="$t('welcome.submit')"
    />
  </form>
</template>

<style scoped lang="scss">
.card {
  display: grid;
  gap: var(--s-5);
  padding: var(--s-6) var(--s-5);
}
.head {
  display: grid;
  gap: var(--s-2);
  text-align: center;
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
  min-height: 48px;
  padding: 0 var(--s-4);
  border: 1px solid var(--border-strong);
  border-radius: var(--r-control);
  background: var(--canvas);
  color: var(--text);
  font: inherit;
  transition: border-color var(--dur) var(--ease);
}
.input:focus-visible {
  outline: 2px solid var(--accent-text);
  outline-offset: 1px;
  border-color: var(--accent-text);
}
.input.invalid {
  border-color: var(--danger);
}
.is-error {
  color: var(--danger);
}
.preview {
  display: grid;
  gap: 2px;
  padding: var(--s-3) var(--s-4);
  border-radius: var(--r-control);
  background: var(--accent-soft);
  word-break: break-all;
}
.preview strong {
  font-weight: 600;
  color: var(--text);
}
</style>
