<script setup lang="ts">
// Optional bot challenge for anonymous sends (Cloudflare Turnstile). Renders nothing unless a site key is configured.
import { onBeforeUnmount, onMounted, ref } from "vue";
import { config } from "@/utils/config";

const emit = defineEmits<{ token: [token: string | null] }>();

declare global {
  interface Window {
    turnstile?: {
      render(el: HTMLElement, options: Record<string, unknown>): string;
      reset(id?: string): void;
      remove(id?: string): void;
    };
  }
}

const host = ref<HTMLElement | null>(null);
let widgetId: string | undefined;

function loadScript(): Promise<void> {
  if (window.turnstile) return Promise.resolve();
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src =
      "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("turnstile failed to load"));
    document.head.appendChild(script);
  });
}

onMounted(async () => {
  if (!config.turnstileSiteKey || !host.value) return;
  try {
    await loadScript();
    widgetId = window.turnstile?.render(host.value, {
      sitekey: config.turnstileSiteKey,
      callback: (token: string) => emit("token", token),
      "expired-callback": () => emit("token", null),
      "error-callback": () => emit("token", null)
    });
  } catch {
    emit("token", null);
  }
});

onBeforeUnmount(() => {
  if (widgetId) window.turnstile?.remove(widgetId);
});

defineExpose({
  reset: () => {
    emit("token", null);
    if (widgetId) window.turnstile?.reset(widgetId);
  }
});
</script>

<template>
  <div v-if="config.turnstileSiteKey" ref="host" class="turnstile" />
</template>

<style scoped>
.turnstile {
  display: flex;
  justify-content: center;
  min-height: 65px;
}
</style>
