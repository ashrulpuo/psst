<script setup lang="ts">
import { MessageCircle } from "@lucide/vue";
</script>

<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-page class="auth-shell">
        <main class="auth-main">
          <router-link to="/" class="brand" aria-label="Psst home">
            <span class="brand-mark" aria-hidden="true"
              ><MessageCircle :size="20"
            /></span>
            <span>Psst</span>
          </router-link>
          <router-view />
        </main>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<style scoped lang="scss">
.auth-shell {
  display: grid;
  place-items: center;
  min-height: calc(100dvh - var(--safe-top));
  padding: var(--s-5) var(--s-4);
}
.auth-main {
  width: 100%;
  max-width: 440px;
  display: grid;
  gap: var(--s-5);
}
.brand {
  display: inline-flex;
  align-items: center;
  gap: var(--s-2);
  justify-self: center;
  font-weight: 600;
  font-size: var(--text-h3);
  color: var(--text);
  text-decoration: none;
  min-height: 44px;
}
.brand-mark {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--accent);
  color: var(--on-accent);
}
</style>
