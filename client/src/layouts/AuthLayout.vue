<template>
  <q-layout view="hHh lpR fFf" class="phone">
    <q-page-container>
      <q-page class="auth-shell">
        <main class="auth-main">
          <router-link to="/" class="brand">Psst</router-link>
          <router-view />
        </main>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<style scoped lang="scss">
.phone {
  width: min(100%, 430px);
  min-height: 100dvh;
  margin-inline: auto;
  background: var(--canvas);
}
.auth-shell {
  display: grid;
  align-content: start;
  justify-items: center;
  min-height: calc(100dvh - var(--safe-top));
  padding: 20px 22px 40px;
}
@media (min-width: 480px) {
  .phone {
    box-shadow: 0 0 0 1px var(--border);
  }
}
.auth-main {
  width: 100%;
  max-width: 440px;
  display: grid;
  gap: var(--s-5);
}
.brand {
  justify-self: start;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  color: var(--text);
  font-family: var(--font-display);
  font-size: 1.45rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1;
  text-decoration: none;
}
.brand:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 4px;
  border-radius: 8px;
}
</style>
