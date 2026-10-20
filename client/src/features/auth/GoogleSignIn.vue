<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from "vue";
import { useQuasar } from "quasar";
import { loadScript } from "@/composables/useScript";
import { config } from "@/utils/config";
import { randomNonce } from "@/utils/format";
import { useAuthStore } from "@/stores/auth";

interface GoogleId {
  initialize(config: {
    client_id: string;
    nonce: string;
    callback: (response: { credential: string }) => void;
  }): void;
  renderButton(el: HTMLElement, options: Record<string, unknown>): void;
}

const emit = defineEmits<{ signedIn: []; failed: [] }>();
const clientId = config.googleClientId;
const $q = useQuasar();
const auth = useAuthStore();
const host = useTemplateRef<HTMLElement>("host");
const ready = ref(false);

onMounted(async () => {
  if (!clientId || !host.value) return;
  try {
    await loadScript("https://accounts.google.com/gsi/client");
    const google = (
      window as unknown as { google: { accounts: { id: GoogleId } } }
    ).google.accounts.id;
    // A fresh nonce per attempt ties the ID token to this page load.
    const nonce = randomNonce();
    google.initialize({
      client_id: clientId,
      nonce,
      callback: async ({ credential }) => {
        try {
          await auth.signIn("google", credential, nonce);
          emit("signedIn");
        } catch {
          emit("failed");
        }
      }
    });
    google.renderButton(host.value, {
      type: "standard",
      theme: $q.dark.isActive ? "filled_black" : "outline",
      size: "large",
      text: "continue_with",
      shape: "rectangular",
      width: Math.min(host.value.clientWidth, 400)
    });
    ready.value = true;
  } catch {
    emit("failed");
  }
});
</script>

<template>
  <div v-if="clientId" ref="host" class="google-host" :aria-busy="!ready" />
</template>

<style scoped lang="scss">
.google-host {
  display: flex;
  justify-content: center;
  min-height: 44px;
}
</style>
