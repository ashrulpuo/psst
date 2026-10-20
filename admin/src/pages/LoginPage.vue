<script setup lang="ts">
// Pattern: Form (auth) — one centred card, no app chrome.
import { ref } from "vue";
import { useAuthStore } from "@/stores/auth";
import GateLayout from "@/layouts/GateLayout.vue";
import GoogleSignIn from "@/components/GoogleSignIn.vue";
import Button from "@/components/ui/Button.vue";

const auth = useAuthStore();
const failed = ref(false);
const busy = ref(false);

const googleOn = Boolean(import.meta.env.VITE_GOOGLE_CLIENT_ID);
const devOn = import.meta.env.VITE_DEV_LOGIN === "true";

async function demo(email: string) {
  busy.value = true;
  failed.value = false;
  try {
    await auth.devLogin(email);
  } catch {
    failed.value = true;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <GateLayout>
    <header class="grid gap-1 text-center">
      <h1>Admin sign-in</h1>
      <p class="text-sm text-fg-2">Staff only. You'll be asked for a second factor next.</p>
    </header>

    <GoogleSignIn @failed="failed = true" />
    <p v-if="!googleOn && !devOn" class="text-center text-sm text-fg-2" role="status">
      Sign-in isn't set up on this server yet.
    </p>
    <p v-if="failed" class="text-center text-sm text-danger" role="alert">
      We couldn't sign you in. Please try again.
    </p>

    <section
      v-if="devOn"
      class="grid gap-2 border-t border-dashed border-line-strong pt-4"
      aria-labelledby="demo-h"
    >
      <h2 id="demo-h" class="text-xs font-medium text-fg-3">Local demo accounts</h2>
      <div class="flex flex-wrap gap-2">
        <Button variant="outline" :loading="busy" @click="demo('admin@example.test')"
          >Site Admin</Button
        >
        <Button variant="outline" :loading="busy" @click="demo('alice@example.test')"
          >Alice (not an admin)</Button
        >
      </div>
    </section>
  </GateLayout>
</template>
