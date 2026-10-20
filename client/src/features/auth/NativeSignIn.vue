<script setup lang="ts">
// Native Google / Apple sign-in sheets. Apple is offered on iOS only (App Store rules require it next to Google).
import { ref } from "vue";
import { Apple } from "@lucide/vue";
import { nativePlatform } from "@/native/platform";
import { useAuthStore } from "@/stores/auth";
import type { Provider } from "@/api/types";

const emit = defineEmits<{ signedIn: []; failed: [] }>();
const auth = useAuthStore();
const busy = ref<Provider | null>(null);

async function go(provider: Provider) {
  busy.value = provider;
  try {
    await auth.signInNative(provider);
    emit("signedIn");
  } catch {
    // Cancelling the sheet is not worth an error banner, but any failure leaves the buttons usable.
    emit("failed");
  } finally {
    busy.value = null;
  }
}
</script>

<template>
  <div class="native-signin">
    <q-btn
      outline
      no-caps
      unelevated
      :loading="busy === 'google'"
      :disable="busy !== null"
      class="provider"
      @click="go('google')"
    >
      <svg
        class="g"
        viewBox="0 0 24 24"
        width="20"
        height="20"
        aria-hidden="true"
      >
        <path
          fill="#4285F4"
          d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.7v3h3.9c2.3-2.1 3.5-5.2 3.5-8.9z"
        />
        <path
          fill="#34A853"
          d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3c-1.1.7-2.5 1.2-4.1 1.2-3.1 0-5.8-2.1-6.7-5H1.3v3.1A12 12 0 0 0 12 24z"
        />
        <path
          fill="#FBBC05"
          d="M5.3 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.3a12 12 0 0 0 0 10.8l4-3.1z"
        />
        <path
          fill="#EA4335"
          d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.3 6.6l4 3.1c.9-2.9 3.6-4.9 6.7-4.9z"
        />
      </svg>
      {{ $t("login.google") }}
    </q-btn>
    <q-btn
      v-if="nativePlatform === 'ios'"
      unelevated
      no-caps
      :loading="busy === 'apple'"
      :disable="busy !== null"
      class="provider apple"
      @click="go('apple')"
    >
      <Apple :size="20" aria-hidden="true" class="icon" />
      {{ $t("login.apple") }}
    </q-btn>
  </div>
</template>

<style scoped lang="scss">
.native-signin {
  display: grid;
  gap: var(--s-3);
}
.provider {
  width: 100%;
  min-height: 48px;
  gap: var(--s-2);
}
.g {
  margin-right: var(--s-2);
}
.icon {
  margin-right: var(--s-2);
}
.apple {
  background: var(--text);
  color: var(--canvas);
}
</style>
