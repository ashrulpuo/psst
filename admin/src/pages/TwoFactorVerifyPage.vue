<script setup lang="ts">
// Pattern: Form (auth)
import { ref } from "vue";
import { useRouter } from "vue-router";
import { ApiError } from "@/lib/api";
import { useAuthStore } from "@/stores/auth";
import GateLayout from "@/layouts/GateLayout.vue";
import Button from "@/components/ui/Button.vue";
import TextField from "@/components/ui/TextField.vue";

const auth = useAuthStore();
const router = useRouter();

const code = ref("");
const error = ref("");
const busy = ref(false);

async function submit() {
  if (!code.value.trim()) return;
  busy.value = true;
  error.value = "";
  try {
    await auth.verify(code.value.trim());
  } catch (e) {
    error.value =
      e instanceof ApiError && e.status === 429
        ? "Too many attempts. Wait a minute and try again."
        : e instanceof ApiError
          ? e.message
          : "Something went wrong. Please try again.";
  } finally {
    busy.value = false;
  }
}

async function signOut() {
  await auth.logout();
  await router.replace({ name: "login" });
}
</script>

<template>
  <GateLayout>
    <header class="grid gap-1 text-center">
      <h1>Enter your code</h1>
      <p class="text-sm text-fg-2">Open your authenticator app, or use a recovery code.</p>
    </header>
    <form class="grid gap-3" novalidate @submit.prevent="submit">
      <TextField
        v-model="code"
        label="Authentication or recovery code"
        autocomplete="one-time-code"
        :maxlength="12"
        placeholder="123456"
        :error="error"
      />
      <Button type="submit" :loading="busy" :disabled="!code.trim()">Verify</Button>
    </form>
    <button type="button" class="min-h-11 text-sm text-fg-2 underline" @click="signOut">
      Sign out
    </button>
  </GateLayout>
</template>
