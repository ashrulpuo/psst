<script setup lang="ts">
// Pattern: Form (auth) — one centred card, no app chrome.
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useMeta } from "quasar";
import { useAuthStore } from "@/stores/auth";
import { config } from "@/utils/config";
import { safeNext } from "@/features/auth/redirect";
import GoogleSignIn from "@/features/auth/GoogleSignIn.vue";
import AppleSignIn from "@/features/auth/AppleSignIn.vue";
import DevLogin from "@/features/auth/DevLogin.vue";
import NativeSignIn from "@/features/auth/NativeSignIn.vue";
import { isNativeApp } from "@/native/platform";

useMeta({ title: "Sign in · Psst" });

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const failed = ref(false);

const googleOn = Boolean(config.googleClientId);
const appleOn = Boolean(config.appleClientId);
const devOn = config.devLogin;

async function done() {
  failed.value = false;
  const next = safeNext(route.query.next);
  await router.replace(
    auth.user?.needs_username
      ? { path: "/welcome", query: next ? { next } : {} }
      : (next ?? "/inbox")
  );
}
</script>

<template>
  <div class="surface card">
    <header class="head">
      <h1>{{ $t("login.title") }}</h1>
      <p class="text-secondary-copy">{{ $t("login.subtitle") }}</p>
    </header>

    <div class="providers">
      <NativeSignIn
        v-if="isNativeApp"
        @signed-in="done"
        @failed="failed = true"
      />
      <template v-else>
        <GoogleSignIn @signed-in="done" @failed="failed = true" />
        <AppleSignIn @signed-in="done" @failed="failed = true" />
        <p
          v-if="!googleOn && !appleOn && !devOn"
          class="text-secondary-copy"
          role="status"
        >
          {{ $t("login.notConfigured") }}
        </p>
      </template>
    </div>

    <p v-if="failed" class="error" role="alert">{{ $t("login.failed") }}</p>

    <DevLogin v-if="devOn" @signed-in="done" @failed="failed = true" />

    <p class="text-meta">{{ $t("login.privacy") }}</p>
  </div>
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
.providers {
  display: grid;
  gap: var(--s-3);
}
.error {
  color: var(--danger);
  font-size: var(--text-secondary);
  text-align: center;
}
@media (max-width: 480px) {
  .card {
    padding: var(--s-5) var(--s-4);
  }
}
</style>
