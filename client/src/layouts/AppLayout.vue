<script setup lang="ts">
// Phone shell at every width. A wide window shows the same column, not a website.
import { computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useMessagingStore } from "@/stores/messaging";
import { useRealtimeStore } from "@/stores/realtime";
import { usePolling } from "@/composables/usePolling";

const messaging = useMessagingStore();
const realtime = useRealtimeStore();
const route = useRoute();
const inThread = computed(() => Boolean(route.params.threadId));

onMounted(() => {
  void messaging.loadInbox();
  void messaging.loadSent();
});
// WebSockets push changes; polling is only a safety net while connected and the main path otherwise.
usePolling(
  () => {
    void messaging.loadInbox();
    void messaging.loadSent();
  },
  () => (realtime.connected ? 60000 : 15000)
);
</script>

<template>
  <q-layout view="hHh lpR fFf" class="shell" :class="{ 'in-thread': inThread }">
    <nav v-show="!inThread" class="nav" :aria-label="$t('nav.main')">
      <router-link to="/link" class="tab" active-class="is-active">
        <span class="ico" aria-hidden="true">🔗</span>
        {{ $t("nav.link") }}
      </router-link>
      <router-link to="/inbox" class="tab" active-class="is-active">
        <span class="ico" aria-hidden="true">💌</span>
        {{ $t("nav.inbox") }}
        <span v-if="messaging.inboxUnread > 0" class="dot" aria-hidden="true" />
        <span v-if="messaging.inboxUnread > 0" class="sr-only">
          {{ messaging.inboxUnread }} unread
        </span>
      </router-link>
      <router-link to="/sent" class="tab" active-class="is-active">
        <span class="ico" aria-hidden="true">📮</span>
        {{ $t("nav.sent") }}
      </router-link>
      <router-link
        to="/settings"
        class="tab"
        active-class="is-active"
        :aria-label="$t('nav.settings')"
      >
        <span class="ico" aria-hidden="true">⚙️</span>
        {{ $t("nav.you") }}
      </router-link>
    </nav>

    <q-page-container class="content">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<style scoped lang="scss">
.shell {
  width: min(100%, 430px);
  min-height: 100dvh;
  margin-inline: auto;
  background: var(--canvas);
}
/* A thread owns the whole screen: no tab bar, no reserved height. */
.shell.in-thread {
  --nav-height: 0px;
}

.nav {
  position: fixed;
  z-index: 10;
  left: 50%;
  bottom: calc(12px + env(safe-area-inset-bottom));
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: min(calc(100% - 24px), 406px);
  height: 62px;
  padding: 0 6px;
  border: 2.5px solid var(--edge);
  border-radius: 22px;
  background: var(--surface);
  box-shadow: 0 5px 0 var(--edge);
  transform: translateX(-50%);
}
.tab {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 2px;
  flex: 1;
  min-height: 48px;
  padding: 5px 2px;
  border-radius: 16px;
  color: var(--text-3);
  font-family: var(--font-display);
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-decoration: none;
}
.tab .ico {
  font-family:
    "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
  font-size: 1.25rem;
  line-height: 1;
  opacity: 0.72;
}
.tab.is-active {
  background: var(--accent-strong);
  color: #fff;
}
.tab.is-active .ico {
  opacity: 1;
}
.tab:focus-visible {
  outline: 3px solid var(--ink);
  outline-offset: 2px;
}
.dot {
  position: absolute;
  top: 6px;
  right: calc(50% - 20px);
  width: 8px;
  height: 8px;
  border: 2px solid var(--surface);
  border-radius: 50%;
  background: var(--accent);
}
.content {
  /* The tab bar is an overlay: full-height pages size themselves and
     scrolling pages pad their own end (Link, Settings). */
  padding-top: var(--safe-top);
}

// The window is the desk. The column is the phone.
@media (min-width: 480px) {
  .shell {
    min-height: 100dvh;
    box-shadow: 0 0 0 1px var(--border);
  }
}
</style>
