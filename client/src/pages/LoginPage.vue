<script setup lang="ts">
// Pattern: Form (auth) — sign in to write back; the note carries the reason.
import { computed, ref } from "vue";
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
/** Google and Apple draw their own buttons; without client IDs there is nothing to show. */
const showProviders = isNativeApp || googleOn || appleOn;

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
    <article class="note arrive" aria-hidden="true">
      <span class="tape" />
      <p class="from">{{ $t("login.noteFrom") }}</p>
      <blockquote>“{{ $t("login.noteBody") }}”</blockquote>
      <span class="waiting">👻 {{ $t("login.noteAnon") }} · 1h</span>
      <span class="stamp">{{ $t("login.stamp") }}</span>
    </article>

    <header class="head arrive-late">
      <h1>{{ $t("login.heading") }}</h1>
      <p class="text-secondary-copy">{{ $t("login.subtitle") }}</p>
    </header>

    <div v-if="showProviders" class="providers arrive-later">
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
          class="text-secondary-copy not-configured"
          role="status"
        >
          {{ $t("login.notConfigured") }}
        </p>
      </template>
    </div>

    <p v-if="failed" class="error" role="alert">{{ $t("login.failed") }}</p>

    <DevLogin v-if="devOn" @signed-in="done" @failed="failed = true" />

    <p class="alt">
      {{ $t("login.noAccount") }}
      <router-link to="/get-link">{{ $t("login.linkCta") }}</router-link>
    </p>

    <ul class="trust">
      <li><b aria-hidden="true">🔑</b>{{ $t("login.trustPassword") }}</li>
      <li><b aria-hidden="true">🚫</b>{{ $t("login.trustSpam") }}</li>
      <li><b aria-hidden="true">🧹</b>{{ $t("login.trustDelete") }}</li>
    </ul>

    <p class="privacy">{{ $t("login.privacy") }}</p>
  </div>
</template>

<style scoped lang="scss">
.signin {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  width: 100%;
  /* Centre the pitch in the space under the brand instead of leaving a hole. */
  min-height: calc(100dvh - 116px);
  padding-bottom: 8px;
}

/* The note: the reason to sign in, told as a sticker. */
.note {
  position: relative;
  width: 100%;
  padding: 20px 18px 16px;
  border: 3px solid var(--edge);
  border-radius: 26px;
  background: var(--grad-sunset);
  box-shadow: 0 8px 0 var(--edge);
  color: #fff;
  transform: rotate(-1.6deg);
}
.tape {
  position: absolute;
  top: -12px;
  left: 50%;
  width: 92px;
  height: 24px;
  margin-left: -46px;
  background: rgba(255, 226, 190, 0.92);
  border: 1px solid rgba(180, 140, 100, 0.35);
  transform: rotate(-2deg);
}
.from {
  margin: 0;
  font-family: var(--font-display);
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  opacity: 0.9;
}
blockquote {
  margin: 10px 0 12px;
  font-family: var(--font-display);
  font-size: 1.45rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.15;
}
.waiting {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 10px;
  border: 1.5px solid rgba(255, 255, 255, 0.45);
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.25);
  font-family: var(--font-display);
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.stamp {
  position: absolute;
  right: 16px;
  bottom: 10px;
  padding: 8px 13px;
  border: 3px dashed #fff;
  border-radius: 10px;
  font-family: var(--font-display);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  opacity: 0.95;
  transform: rotate(6deg);
}

.head {
  display: grid;
  gap: 5px;
  text-align: center;
}
.head h1 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.05;
}
.head p {
  max-width: 32ch;
  margin-inline: auto;
  font-size: 0.8rem;
  line-height: 1.5;
}

.providers {
  display: grid;
  gap: 9px;
  width: 100%;
}
.providers :deep(.google-host) {
  min-height: 52px;
}
.not-configured {
  text-align: center;
  font-size: 0.8rem;
}
.error {
  margin: 0;
  color: var(--danger);
  font-size: var(--text-secondary);
  text-align: center;
}

.alt {
  margin: 0;
  color: var(--text-2);
  font-size: 0.8rem;
  text-align: center;
}
.alt a {
  color: var(--accent-text);
  font-weight: 800;
  text-underline-offset: 3px;
}

.trust {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 7px;
  width: 100%;
  margin: 0;
  padding: 0;
  list-style: none;
}
.trust li {
  display: grid;
  justify-items: center;
  gap: 3px;
  padding: 9px 4px;
  border: 2px dashed var(--border-strong);
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 60%, transparent);
  color: var(--text-2);
  font-family: var(--font-display);
  font-size: 0.54rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-align: center;
}
.trust b {
  font-size: 0.9rem;
  line-height: 1;
}

.privacy {
  margin: 0;
  color: var(--text-3);
  font-size: 0.68rem;
  line-height: 1.45;
  text-align: center;
}
</style>
