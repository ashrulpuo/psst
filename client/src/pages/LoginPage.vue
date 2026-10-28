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
import { MessageCircle } from "@lucide/vue";

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
      : (next ?? "/link")
  );
}
</script>

<template>
  <div class="signin">
    <article class="story arrive">
      <span class="ring" aria-hidden="true">
        <MessageCircle :size="28" />
      </span>
      <h1>{{ $t("link.prompt") }}</h1>
    </article>

    <header class="head">
      <p class="lede">{{ $t("login.title") }}</p>
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

    <p class="alt">
      {{ $t("login.wantLink") }}
      <router-link to="/get-link">{{ $t("login.wantLinkAction") }}</router-link>
    </p>

    <p class="text-meta">{{ $t("login.privacy") }}</p>
  </div>
</template>

<style scoped lang="scss">
.signin {
  display: grid;
  justify-items: center;
  gap: 22px;
  width: min(100%, 320px);
}
.story {
  display: grid;
  align-content: center;
  justify-items: center;
  gap: 14px;
  width: 100%;
  aspect-ratio: 1;
  padding: 24px;
  text-align: center;
  border: 2.5px solid var(--edge);
  border-radius: 28px;
  background: var(--grad-pink);
  box-shadow: 0 9px 0 var(--edge);
}
.ring {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border: 3px solid var(--edge);
  border-radius: 50%;
  background: var(--surface);
  color: var(--accent-strong);
  transform: rotate(-4deg);
}
.story h1 {
  max-width: 9ch;
  margin: 0;
  color: #fff;
  font-family: var(--font-display);
  font-size: 2.15rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.02;
}
.head {
  display: grid;
  gap: var(--s-2);
  text-align: center;
}
.alt {
  padding: 10px 14px;
  border: 2px dashed var(--border-strong);
  border-radius: var(--r-card);
  color: var(--text-2);
  font-size: 0.85rem;
  text-align: center;
}
.alt a {
  color: var(--accent-text);
  font-weight: 700;
  text-underline-offset: 3px;
}
.lede {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.providers {
  display: grid;
  gap: var(--s-3);
  width: 100%;
}
.error {
  color: var(--danger);
  font-size: var(--text-secondary);
  text-align: center;
}
.signin > p,
.signin :deep(.demo) {
  width: 100%;
}
.signin > p {
  text-align: center;
}
</style>
