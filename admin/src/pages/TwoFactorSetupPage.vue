<script setup lang="ts">
// Pattern: Form (auth) — enrol an authenticator app, then keep the recovery codes.
import { onMounted, ref } from "vue";
import QRCode from "qrcode";
import { Copy, KeyRound } from "@lucide/vue";
import { api, ApiError } from "@/lib/api";
import { useAuthStore } from "@/stores/auth";
import GateLayout from "@/layouts/GateLayout.vue";
import Button from "@/components/ui/Button.vue";
import TextField from "@/components/ui/TextField.vue";

const auth = useAuthStore();

const secret = ref("");
const qr = ref("");
const code = ref("");
const error = ref("");
const busy = ref(false);
const recoveryCodes = ref<string[]>([]);
const saved = ref(false);
const copied = ref(false);

onMounted(async () => {
  try {
    const { data } = await api.post<{ data: { secret: string; uri: string } }>("/admin/2fa/enroll");
    secret.value = data.secret;
    // Drawn locally: the secret never leaves this browser for a QR-code web service.
    qr.value = await QRCode.toDataURL(data.uri, { margin: 1, width: 196 });
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Something went wrong.";
  }
});

async function confirm() {
  busy.value = true;
  error.value = "";
  try {
    const { data } = await api.post<{ data: { recovery_codes: string[] } }>("/admin/2fa/confirm", {
      code: code.value.replace(/\s/g, ""),
    });
    recoveryCodes.value = data.recovery_codes;
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : "Something went wrong. Please try again.";
  } finally {
    busy.value = false;
  }
}

async function copyCodes() {
  try {
    await navigator.clipboard.writeText(recoveryCodes.value.join("\n"));
    copied.value = true;
  } catch {
    /* the codes are on screen: the user can copy them by hand */
  }
}

async function done() {
  await auth.refresh(); // the router guard then lets us into the app
}
</script>

<template>
  <GateLayout>
    <template v-if="!recoveryCodes.length">
      <header class="grid gap-1 text-center">
        <h1>Set up two-factor</h1>
        <p class="text-sm text-fg-2">
          Admin access needs a code from an authenticator app (1Password, Authy, Google
          Authenticator…).
        </p>
      </header>

      <ol class="grid gap-4 text-sm">
        <li class="grid gap-2">
          <span class="font-medium">1. Scan this code with your app</span>
          <img
            v-if="qr"
            :src="qr"
            width="196"
            height="196"
            alt="QR code for your authenticator app"
            class="mx-auto rounded-control border border-line bg-white p-2"
          />
          <div
            v-else
            class="mx-auto size-[212px] animate-pulse rounded-control bg-surface-2"
            aria-hidden="true"
          />
          <p v-if="secret" class="text-center text-xs text-fg-3">
            Can't scan? Enter this key instead:<br /><code
              class="break-all font-mono text-sm text-fg"
              >{{ secret }}</code
            >
          </p>
        </li>
        <li class="grid gap-2">
          <span class="font-medium">2. Enter the 6-digit code it shows</span>
          <form class="grid gap-3" novalidate @submit.prevent="confirm">
            <TextField
              v-model="code"
              label="Authentication code"
              hide-label
              inputmode="numeric"
              autocomplete="one-time-code"
              :maxlength="7"
              placeholder="123 456"
              :error="error"
            />
            <Button type="submit" :loading="busy" :disabled="code.replace(/\s/g, '').length !== 6"
              >Turn on two-factor</Button
            >
          </form>
        </li>
      </ol>
    </template>

    <template v-else>
      <header class="grid gap-1 text-center">
        <span
          class="mx-auto grid size-11 place-items-center rounded-2xl bg-accent-soft text-accent-text"
          aria-hidden="true"
          ><KeyRound class="size-6"
        /></span>
        <h1>Save your recovery codes</h1>
        <p class="text-sm text-fg-2">
          Each works once if you lose your authenticator. We can't show them again.
        </p>
      </header>
      <ul
        class="grid grid-cols-2 gap-2 rounded-control border border-line bg-canvas p-4 font-mono text-sm"
      >
        <li v-for="c in recoveryCodes" :key="c">{{ c }}</li>
      </ul>
      <Button variant="outline" @click="copyCodes"
        ><Copy class="size-4" aria-hidden="true" />{{ copied ? "Copied" : "Copy codes" }}</Button
      >
      <label class="flex items-start gap-3 text-sm">
        <input v-model="saved" type="checkbox" class="mt-1 size-5 accent-[var(--accent)]" />
        I've stored these somewhere safe.
      </label>
      <Button :disabled="!saved" @click="done">Continue to admin</Button>
    </template>
  </GateLayout>
</template>
