<script setup lang="ts">
// App shell: left rail on wide screens, bottom tab bar on narrow. Never width-capped itself.
import { onMounted } from "vue";
import { Inbox, MessageCircle, Send, Settings } from "@lucide/vue";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";
import { useRealtimeStore } from "@/stores/realtime";
import { usePolling } from "@/composables/usePolling";
import AppAvatar from "@/components/AppAvatar.vue";

const auth = useAuthStore();
const messaging = useMessagingStore();
const realtime = useRealtimeStore();

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

const items = [
  {
    to: "/inbox",
    key: "nav.inbox",
    icon: Inbox,
    badge: () => messaging.inboxUnread
  },
  {
    to: "/sent",
    key: "nav.sent",
    icon: Send,
    badge: () => messaging.sentUnread
  },
  { to: "/settings", key: "nav.settings", icon: Settings, badge: () => 0 }
];
</script>

<template>
  <q-layout view="hHh lpR fFf" class="shell">
    <nav class="nav" :aria-label="$t('nav.main')">
      <router-link to="/inbox" class="nav-brand hit" aria-label="Psst">
        <span class="brand-mark" aria-hidden="true"
          ><MessageCircle :size="20"
        /></span>
      </router-link>

      <ul class="nav-list">
        <li v-for="item in items" :key="item.to">
          <router-link :to="item.to" class="nav-link" active-class="is-active">
            <span class="nav-icon">
              <component :is="item.icon" :size="22" aria-hidden="true" />
              <span v-if="item.badge() > 0" class="badge" aria-hidden="true">{{
                item.badge() > 9 ? "9+" : item.badge()
              }}</span>
            </span>
            <span class="nav-label">{{ $t(item.key) }}</span>
            <span v-if="item.badge() > 0" class="sr-only"
              >{{ item.badge() }} unread</span
            >
          </router-link>
        </li>
      </ul>

      <router-link
        to="/settings"
        class="nav-me hit"
        :aria-label="auth.user?.name"
      >
        <AppAvatar
          :name="auth.user?.name ?? ''"
          :src="auth.user?.avatar_url"
          :size="36"
        />
      </router-link>
    </nav>

    <q-page-container class="content">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<style scoped lang="scss">
.shell {
  min-height: 100dvh;
}

// ---- narrow: bottom tab bar ----
.nav {
  position: fixed;
  inset: auto 0 0 0;
  z-index: 10;
  display: flex;
  align-items: stretch;
  height: calc(var(--nav-height) + env(safe-area-inset-bottom));
  padding-bottom: env(safe-area-inset-bottom);
  background: var(--surface);
  border-top: 1px solid var(--border);
}
.nav-brand,
.nav-me {
  display: none;
  text-decoration: none;
}
.nav-list {
  display: flex;
  flex: 1;
  margin: 0;
  padding: 0;
  list-style: none;
}
.nav-list li {
  flex: 1;
}
.nav-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  height: 100%;
  min-height: 44px;
  color: var(--text-3);
  text-decoration: none;
  font-size: var(--text-meta);
  font-weight: 500;
  transition: color var(--dur) var(--ease);
}
.nav-link:hover {
  color: var(--text);
}
.nav-link.is-active {
  color: var(--accent-text);
}
.nav-icon {
  position: relative;
  display: grid;
}
.badge {
  position: absolute;
  top: -6px;
  right: -10px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: var(--accent);
  color: var(--on-accent);
  font-size: var(--text-meta);
  font-weight: 600;
  line-height: 18px;
  text-align: center;
}
.content {
  padding-bottom: calc(var(--nav-height) + env(safe-area-inset-bottom));
}

// ---- medium and up: left rail ----
@media (min-width: 768px) {
  .nav {
    inset: 0 auto 0 0;
    flex-direction: column;
    align-items: center;
    width: var(--rail-width);
    height: 100dvh;
    padding: var(--s-4) 0;
    border-top: 0;
    border-right: 1px solid var(--border);
  }
  .nav-brand,
  .nav-me {
    display: inline-flex;
  }
  .nav-brand {
    margin-bottom: var(--s-4);
  }
  .nav-list {
    flex: 1;
    flex-direction: column;
    gap: var(--s-1);
    width: 100%;
    padding: 0 var(--s-2);
  }
  .nav-list li {
    flex: none;
  }
  .nav-link {
    height: auto;
    padding: var(--s-2) 0;
    border-radius: var(--r-control);
  }
  .nav-link:hover {
    background: var(--surface-hover);
  }
  .nav-link.is-active {
    background: var(--accent-soft);
  }
  .content {
    padding: 0 0 0 var(--rail-width);
  }
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  background: var(--accent);
  color: var(--on-accent);
}
</style>
