<script setup lang="ts">
import { onMounted, useTemplateRef } from "vue";
import { useAuthStore } from "@/stores/auth";

interface GoogleId {
  initialize(config: {
    client_id: string;
    nonce: string;
    callback: (r: { credential: string }) => void;
  }): void;
  renderButton(el: HTMLElement, options: Record<string, unknown>): void;
}

const emit = defineEmits<{ failed: [] }>();
const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;
const auth = useAuthStore();
const host = useTemplateRef<HTMLElement>("host");

const randomNonce = () =>
  Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) =>
    b.toString(16).padStart(2, "0"),
  ).join("");

onMounted(async () => {
  if (!clientId || !host.value) return;
  try {
    await new Promise<void>((resolve, reject) => {
      const el = document.createElement("script");
      el.src = "https://accounts.google.com/gsi/client";
      el.async = true;
      el.onload = () => resolve();
      el.onerror = () => reject(new Error("script"));
      document.head.appendChild(el);
    });
    const google = (window as unknown as { google: { accounts: { id: GoogleId } } }).google.accounts
      .id;
    const nonce = randomNonce();
    google.initialize({
      client_id: clientId,
      nonce,
      callback: async ({ credential }) => {
        try {
          await auth.googleLogin(credential, nonce);
        } catch {
          emit("failed");
        }
      },
    });
    google.renderButton(host.value, {
      type: "standard",
      theme: document.documentElement.classList.contains("dark") ? "filled_black" : "outline",
      size: "large",
      text: "continue_with",
      width: Math.min(host.value.clientWidth, 360),
    });
  } catch {
    emit("failed");
  }
});
</script>

<template>
  <div v-if="clientId" ref="host" class="flex min-h-11 justify-center" />
</template>
