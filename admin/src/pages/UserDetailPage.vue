<script setup lang="ts">
// Pattern: Detail / Record — inspect one account; the destructive action is named and confirmed.
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink } from "vue-router";
import { ArrowLeft, MessagesSquare, ShieldAlert, ShieldCheck } from "@lucide/vue";
import { api, ApiError } from "@/lib/api";
import type { AdminUserDetail } from "@/lib/types";
import { formatDateTime, formatNumber, timeAgo } from "@/lib/format";
import { useAuthStore } from "@/stores/auth";
import Avatar from "@/components/ui/Avatar.vue";
import Badge from "@/components/ui/Badge.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import Dialog from "@/components/ui/Dialog.vue";

const props = defineProps<{ id: string }>();
const auth = useAuthStore();

const user = ref<AdminUserDetail | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    user.value = (await api.get<{ data: AdminUserDetail }>(`/admin/users/${props.id}`)).data;
  } catch (e) {
    error.value =
      e instanceof ApiError && e.status === 404
        ? "That user doesn't exist."
        : e instanceof Error
          ? e.message
          : "Something went wrong.";
  } finally {
    loading.value = false;
  }
}
onMounted(load);
watch(() => props.id, load);

const isSelf = computed(() => auth.me?.id === user.value?.id);
const canBan = computed(() => user.value && user.value.role !== "admin" && !isSelf.value);

// ---- ban / unban ----
const banOpen = ref(false);
const reason = ref("");
const busy = ref(false);
const actionError = ref("");

function openBan() {
  reason.value = "";
  actionError.value = "";
  banOpen.value = true;
}

async function applyBan() {
  if (!user.value) return;
  busy.value = true;
  actionError.value = "";
  try {
    await api.put(`/admin/users/${user.value.id}/ban`, { reason: reason.value.trim() });
    banOpen.value = false;
    await load();
  } catch (e) {
    actionError.value =
      e instanceof ApiError ? (e.field("reason") ?? e.message) : "Something went wrong.";
  } finally {
    busy.value = false;
  }
}

async function lift() {
  if (!user.value) return;
  busy.value = true;
  try {
    await api.delete(`/admin/users/${user.value.id}/ban`);
    await load();
  } finally {
    busy.value = false;
  }
}

const stats = computed(() => {
  const s = user.value?.stats;
  return s
    ? [
        { label: "Messages written", value: s.messages_sent },
        { label: "Conversations received", value: s.received_threads },
        { label: "Conversations started", value: s.sent_threads },
        { label: "Reports filed", value: s.reports_filed },
        { label: "Reports against", value: s.reports_against, warn: s.reports_against > 0 },
        { label: "Push devices", value: s.push_devices },
      ]
    : [];
});
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-6">
    <RouterLink
      to="/users"
      class="inline-flex min-h-11 w-fit items-center gap-2 text-sm text-fg-2 no-underline hover:text-fg"
      ><ArrowLeft class="size-4" aria-hidden="true" />Users</RouterLink
    >

    <DataState :loading="loading" :error="error" :has-data="Boolean(user)" @retry="load">
      <div v-if="user" class="grid gap-6">
        <section class="surface flex flex-wrap items-center gap-5 p-6" aria-labelledby="user-h">
          <Avatar :name="user.name" :src="user.avatar_url" :size="64" />
          <div class="grid min-w-0 flex-1 gap-1">
            <h1 id="user-h">{{ user.name }}</h1>
            <p class="truncate text-sm text-fg-2">
              {{ user.username ? `@${user.username}` : "No username yet" }} · {{ user.email }}
            </p>
            <div class="flex flex-wrap gap-1 pt-1">
              <Badge v-if="user.role === 'admin'" tone="accent">Admin</Badge>
              <Badge v-if="user.banned_at" tone="danger">Banned</Badge>
              <Badge v-else tone="ok">Active</Badge>
              <Badge v-for="p in user.providers" :key="p">{{ p }}</Badge>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <RouterLink :to="{ name: 'threads', query: { user: user.id } }" class="no-underline">
              <Button variant="outline" tabindex="-1"
                ><MessagesSquare class="size-4" aria-hidden="true" />Conversations</Button
              >
            </RouterLink>
            <Button v-if="canBan && !user.banned_at" variant="danger" @click="openBan"
              ><ShieldAlert class="size-4" aria-hidden="true" />Ban user</Button
            >
            <Button v-if="canBan && user.banned_at" variant="outline" :loading="busy" @click="lift"
              ><ShieldCheck class="size-4" aria-hidden="true" />Lift ban</Button
            >
          </div>
        </section>

        <section
          v-if="user.banned_at"
          class="rounded-card border border-danger/40 bg-danger-soft p-5"
          role="status"
        >
          <h2 class="text-danger">Banned {{ timeAgo(user.banned_at) }}</h2>
          <p class="mt-1 text-sm text-fg-2">Reason: {{ user.ban_reason }}</p>
        </section>

        <section aria-labelledby="stats-h" class="grid gap-3">
          <h2 id="stats-h">Activity</h2>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="s in stats" :key="s.label" class="surface grid gap-1 p-5">
              <p class="text-sm text-fg-2">{{ s.label }}</p>
              <p class="text-3xl font-semibold leading-none" :class="s.warn ? 'text-danger' : ''">
                {{ formatNumber(s.value) }}
              </p>
            </div>
          </div>
        </section>

        <section class="surface p-6" aria-labelledby="facts-h">
          <h2 id="facts-h" class="mb-3">Details</h2>
          <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-fg-3">Joined</dt>
              <dd>{{ formatDateTime(user.created_at) }}</dd>
            </div>
            <div>
              <dt class="text-fg-3">Last sign-in</dt>
              <dd>{{ formatDateTime(user.last_login_at) }}</dd>
            </div>
            <div>
              <dt class="text-fg-3">Two-factor</dt>
              <dd>
                {{
                  user.role === "admin" ? (user.two_factor ? "On" : "Not set up") : "Not required"
                }}
              </dd>
            </div>
            <div>
              <dt class="text-fg-3">User id</dt>
              <dd class="font-mono text-xs break-all">{{ user.id }}</dd>
            </div>
          </dl>
          <p class="mt-4 text-xs text-fg-3">Opening this profile was recorded in the audit log.</p>
        </section>
      </div>
    </DataState>

    <Dialog
      v-model:open="banOpen"
      :title="`Ban ${user?.name ?? 'this user'}?`"
      description="This takes effect immediately and can be lifted later."
    >
      <ul class="list-disc space-y-1 pl-5 text-sm text-fg-2">
        <li>They are signed out everywhere and can't sign back in.</li>
        <li>Their push notifications stop.</li>
        <li>They can't send or reply to messages, and their link stops working.</li>
      </ul>
      <form id="ban-form" class="grid gap-1.5" novalidate @submit.prevent="applyBan">
        <label for="ban-reason" class="text-sm font-medium">Reason (kept in the audit log)</label>
        <textarea
          id="ban-reason"
          v-model="reason"
          rows="3"
          maxlength="255"
          class="rounded-control border border-line-strong bg-canvas p-3 text-base text-fg"
          :aria-invalid="Boolean(actionError)"
        />
        <p v-if="actionError" class="text-xs text-danger" role="alert">{{ actionError }}</p>
      </form>
      <template #actions>
        <Button variant="ghost" @click="banOpen = false">Cancel</Button>
        <Button
          variant="danger"
          type="submit"
          form="ban-form"
          :loading="busy"
          :disabled="reason.trim().length < 3"
          >Ban {{ user?.name }}</Button
        >
      </template>
    </Dialog>
  </div>
</template>
