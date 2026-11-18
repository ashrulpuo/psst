<script setup lang="ts">
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { ApiError } from "@/api/http";
import { reportOwnLinkAfterGuest } from "@/utils/growth";
import { useAuthStore } from "@/stores/auth";
import AppButton from "@/components/AppButton.vue";

const router = useRouter();
const auth = useAuthStore();

const username = ref("");
const touched = ref(false);
const serverError = ref("");
const busy = ref(false);

const FORMAT = /^[a-z0-9_.]{3,24}$/;
const normalized = computed(() => username.value.trim().toLowerCase());
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
    await auth.claimLink(normalized.value);
    reportOwnLinkAfterGuest();
    await router.replace("/link");
  } catch (e) {
    if (auth.isAuthenticated && auth.user?.username) {
      await router.replace("/link");
      return;
    }
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
  <form class="claim" novalidate @submit.prevent="submit">
    <div class="field">
      <label for="link-username">{{ $t("welcome.label") }}</label>
      <input
        id="link-username"
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
        aria-describedby="link-username-help"
        @blur="touched = true"
        @input="serverError = ''"
      />
      <p
        id="link-username-help"
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

    <AppButton type="submit" block :disabled="busy">
      {{ $t("welcome.submit") }}
    </AppButton>
  </form>
</template>

<style scoped lang="scss">
.claim {
  display: grid;
  gap: var(--s-4);
  width: 100%;
}
.field {
  display: grid;
  gap: var(--s-2);
  text-align: start;
}
label {
  font-size: var(--text-secondary);
  font-weight: 500;
}
.input {
  min-height: 48px;
  padding: 0 14px;
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
.input.invalid {
  border-color: var(--danger);
}
.is-error {
  color: var(--danger);
}
.preview {
  display: grid;
  gap: 2px;
  padding: 10px 12px;
  border: 2px dashed var(--border-strong);
  border-radius: var(--r-control);
  background: var(--cream);
  word-break: break-all;
  text-align: start;
}
.preview strong {
  font-weight: 600;
  color: var(--text);
}
</style>
