<script setup lang="ts">
import { onMounted, ref } from "vue";
import { Apple } from "@lucide/vue";
import { loadScript } from "@/composables/useScript";
import { config } from "@/utils/config";
import { randomNonce } from "@/utils/format";
import { useAuthStore } from "@/stores/auth";

interface AppleAuth {
  init(config: Record<string, unknown>): void;
  signIn(): Promise<{
    authorization: { id_token: string };
    user?: { name?: { firstName?: string; lastName?: string } };
  }>;
}

const emit = defineEmits<{ signedIn: []; failed: [] }>();
const clientId = config.appleClientId;
const redirectURI = config.appleRedirectUri || window.location.origin;
const auth = useAuthStore();
const ready = ref(false);
const busy = ref(false);
let nonce = "";

const apple = () =>
  (window as unknown as { AppleID: { auth: AppleAuth } }).AppleID.auth;

onMounted(async () => {
  if (!clientId) return;
  try {
    await loadScript(
      "https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js"
    );
    nonce = randomNonce();
    apple().init({
      clientId,
      scope: "name email",
      redirectURI,
      usePopup: true,
      nonce
    });
    ready.value = true;
  } catch {
    emit("failed");
  }
});

async function signIn() {
  busy.value = true;
  try {
    const result = await apple().signIn();
    // Apple only shares the name on the very first authorization.
    const first = result.user?.name;
    const name =
      [first?.firstName, first?.lastName].filter(Boolean).join(" ") ||
      undefined;
    await auth.signIn("apple", result.authorization.id_token, nonce, name);
    emit("signedIn");
  } catch (e) {
    // Closing the popup is not an error worth shouting about.
    if ((e as { error?: string })?.error !== "popup_closed_by_user")
      emit("failed");
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <button
    v-if="clientId"
    class="apple-btn"
    type="button"
    :disabled="!ready || busy"
    @click="signIn"
  >
    <Apple :size="20" aria-hidden="true" />
    <span>{{ $t("login.apple") }}</span>
  </button>
</template>

<style scoped lang="scss">
.apple-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--s-2);
  width: 100%;
  max-width: 400px;
  min-height: 44px;
  margin-inline: auto;
  border: 1px solid var(--text);
  border-radius: 4px;
  background: var(--text);
  color: var(--canvas);
  font: inherit;
  font-size: var(--text-secondary);
  font-weight: 500;
  cursor: pointer;
  transition: opacity var(--dur) var(--ease);
}
.apple-btn:disabled {
  opacity: 0.6;
  cursor: progress;
}
</style>
