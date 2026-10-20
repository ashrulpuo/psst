<script setup lang="ts">
// App shell: left rail on wide screens, a menu sheet on narrow ones. The shell itself is never width-capped.
import { computed, onMounted, ref } from "vue";
import { RouterLink, RouterView, useRoute, useRouter } from "vue-router";
import {
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from "reka-ui";
import {
  Flag,
  History,
  LayoutDashboard,
  LogOut,
  Menu,
  MessagesSquare,
  Monitor,
  Moon,
  ShieldCheck,
  Sun,
  Users,
  X,
} from "@lucide/vue";
import { useAuthStore } from "@/stores/auth";
import { useOverviewStore } from "@/stores/overview";
import { getTheme, setTheme, type ThemeChoice } from "@/lib/theme";
import Avatar from "@/components/ui/Avatar.vue";
import Button from "@/components/ui/Button.vue";

const auth = useAuthStore();
const overview = useOverviewStore();
const route = useRoute();
const router = useRouter();

const menuOpen = ref(false);
const theme = ref<ThemeChoice>(getTheme());

onMounted(() => void overview.load());

const items = computed(() => [
  { to: "/", name: "dashboard", label: "Dashboard", icon: LayoutDashboard, badge: 0 },
  { to: "/users", name: "users", label: "Users", icon: Users, badge: 0 },
  { to: "/threads", name: "threads", label: "Conversations", icon: MessagesSquare, badge: 0 },
  { to: "/reports", name: "reports", label: "Reports", icon: Flag, badge: overview.openReports },
  { to: "/audit", name: "audit", label: "Audit log", icon: History, badge: 0 },
]);

/** A detail page keeps its section highlighted (a user belongs to Users, a thread to Conversations). */
const sectionOf: Record<string, string> = { user: "users", thread: "threads" };
const activeName = computed(() => sectionOf[String(route.name)] ?? String(route.name));

const themes: { value: ThemeChoice; label: string; icon: typeof Sun }[] = [
  { value: "system", label: "Match device", icon: Monitor },
  { value: "light", label: "Light", icon: Sun },
  { value: "dark", label: "Dark", icon: Moon },
];
function cycleTheme() {
  const next = themes[(themes.findIndex((t) => t.value === theme.value) + 1) % themes.length]!;
  theme.value = next.value;
  setTheme(next.value);
}
const themeMeta = computed(() => themes.find((t) => t.value === theme.value)!);

async function signOut() {
  await auth.logout();
  await router.replace({ name: "login" });
}
</script>

<template>
  <div class="min-h-dvh md:pl-64">
    <!-- wide: fixed left rail -->
    <aside
      class="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r border-line bg-surface md:flex"
      aria-label="Sidebar"
    >
      <div class="flex items-center gap-2 px-5 py-5 text-lg font-semibold">
        <span
          class="grid size-8 place-items-center rounded-[10px] bg-accent text-on-accent"
          aria-hidden="true"
          ><ShieldCheck class="size-5"
        /></span>
        Psst Admin
      </div>
      <nav class="grid flex-1 content-start gap-1 px-3" aria-label="Main">
        <RouterLink
          v-for="item in items"
          :key="item.name"
          :to="item.to"
          class="flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-medium text-fg-2 no-underline transition-colors hover:bg-surface-2 hover:text-fg"
          :class="activeName === item.name ? 'bg-accent-soft !text-accent-text' : ''"
          :aria-current="activeName === item.name ? 'page' : undefined"
        >
          <component :is="item.icon" class="size-5" aria-hidden="true" />
          <span class="flex-1">{{ item.label }}</span>
          <span
            v-if="item.badge > 0"
            class="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-on-accent"
            >{{ item.badge }}<span class="sr-only-keep"> open</span></span
          >
        </RouterLink>
      </nav>
      <div class="grid gap-3 border-t border-line p-4">
        <div class="flex items-center gap-3">
          <Avatar :name="auth.me?.name ?? ''" :src="auth.me?.avatar_url" :size="36" />
          <div class="min-w-0">
            <p class="truncate text-sm font-medium">{{ auth.me?.name }}</p>
            <p class="truncate text-xs text-fg-3">{{ auth.me?.email }}</p>
          </div>
        </div>
        <div class="flex gap-2">
          <Button
            variant="outline"
            size="icon"
            :title="`Theme: ${themeMeta.label}`"
            :aria-label="`Theme: ${themeMeta.label}. Switch theme`"
            @click="cycleTheme"
          >
            <component :is="themeMeta.icon" class="size-5" aria-hidden="true" />
          </Button>
          <Button variant="outline" class="flex-1" @click="signOut"
            ><LogOut class="size-4" aria-hidden="true" />Sign out</Button
          >
        </div>
      </div>
    </aside>

    <!-- narrow: top bar + sheet menu -->
    <header
      class="sticky top-0 z-20 flex items-center gap-2 border-b border-line bg-surface px-3 py-2 md:hidden"
    >
      <Button variant="ghost" size="icon" aria-label="Open menu" @click="menuOpen = true"
        ><Menu class="size-5" aria-hidden="true"
      /></Button>
      <span class="font-semibold">Psst Admin</span>
    </header>

    <DialogRoot v-model:open="menuOpen">
      <DialogPortal>
        <DialogOverlay class="fixed inset-0 z-40 bg-black/50 md:hidden" />
        <DialogContent
          class="fixed inset-y-0 left-0 z-50 grid w-72 max-w-[85vw] grid-rows-[auto_1fr_auto] bg-surface md:hidden"
        >
          <div class="flex items-center justify-between px-4 py-3">
            <DialogTitle class="font-semibold">Menu</DialogTitle>
            <DialogDescription class="sr-only-keep">Navigate the admin area</DialogDescription>
            <Button variant="ghost" size="icon" aria-label="Close menu" @click="menuOpen = false"
              ><X class="size-5" aria-hidden="true"
            /></Button>
          </div>
          <nav class="grid content-start gap-1 px-3" aria-label="Main">
            <RouterLink
              v-for="item in items"
              :key="item.name"
              :to="item.to"
              class="flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-medium text-fg-2 no-underline hover:bg-surface-2"
              :class="activeName === item.name ? 'bg-accent-soft !text-accent-text' : ''"
              @click="menuOpen = false"
            >
              <component :is="item.icon" class="size-5" aria-hidden="true" />
              <span class="flex-1">{{ item.label }}</span>
              <span
                v-if="item.badge > 0"
                class="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-on-accent"
                >{{ item.badge }}</span
              >
            </RouterLink>
          </nav>
          <div class="grid gap-2 border-t border-line p-4">
            <p class="truncate text-sm">{{ auth.me?.email }}</p>
            <div class="flex gap-2">
              <Button variant="outline" size="sm" class="flex-1" @click="cycleTheme"
                ><component :is="themeMeta.icon" class="size-4" aria-hidden="true" />{{
                  themeMeta.label
                }}</Button
              >
              <Button variant="outline" size="sm" @click="signOut"
                ><LogOut class="size-4" aria-hidden="true" />Sign out</Button
              >
            </div>
          </div>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>

    <!-- main page content is width-capped and centred; the shell around it is not -->
    <main class="mx-auto w-full max-w-7xl px-4 py-6 md:px-8 md:py-8"><RouterView /></main>
  </div>
</template>
