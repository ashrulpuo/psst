<script setup lang="ts">
// Phone shell at every width. A wide window shows the same column, not a website.
import { computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { Settings } from "@lucide/vue";
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
      <router-link to="/link" class="mark">Psst</router-link>
      <div class="tabs">
        <router-link to="/inbox" class="tab" active-class="is-active">
          {{ $t("nav.inbox") }}
          <span
            v-if="messaging.inboxUnread > 0"
            class="dot"
            aria-hidden="true"
          />
          <span v-if="messaging.inboxUnread > 0" class="sr-only"
            >{{ messaging.inboxUnread }} unread</span
          >
        </router-link>
        <router-link to="/sent" class="tab" active-class="is-active">{{
          $t("nav.sent")
        }}</router-link>
      </div>
      <router-link
        to="/settings"
        class="gear"
        active-class="is-active"
        :aria-label="$t('nav.settings')"
      >
        <Settings :size="22" aria-hidden="true" />
      </router-link>
    </nav>

    <q-page-container class="content">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<style scoped lang="scss">
.shell {
  --nav-height: 56px;
  width: min(100%, 430px);
  min-height: 100dvh;
  margin-inline: auto;
  background: var(--canvas);
}
.shell.in-thread {
  --nav-height: 0px;
}

.nav {
  position: fixed;
  z-index: 10;
  top: 0;
  left: 50%;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  width: min(100%, 430px);
  height: calc(var(--nav-height) + var(--safe-top));
  padding: var(--safe-top) 8px 0;
  background: var(--canvas);
  transform: translateX(-50%);
}
.mark {
  justify-self: start;
  padding-left: 8px;
  color: var(--text);
  font-family: var(--font-display);
  font-size: 1.45rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1;
  text-decoration: none;
}
.tabs {
  display: flex;
  align-items: center;
  gap: 22px;
}
.tab {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  color: var(--text-3);
  font-size: 1.15rem;
  font-weight: 600;
  text-decoration: none;
}
.tab.is-active {
  color: var(--text);
  font-weight: 800;
}
.tab:focus-visible,
.mark:focus-visible,
.gear:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
  border-radius: 8px;
}
.dot {
  position: absolute;
  top: 10px;
  right: -9px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ff3b5c;
}
.gear {
  justify-self: end;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  color: var(--text);
  text-decoration: none;
}
.content {
  padding-top: calc(var(--nav-height) + var(--safe-top));
}

// The window is the desk. The column is the phone.
@media (min-width: 480px) {
  .shell {
    min-height: 100dvh;
    box-shadow: 0 0 0 1px var(--border);
  }
}
</style>
