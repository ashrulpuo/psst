<script setup lang="ts">
// Pattern: Detail / Record — one conversation, both identities, the full message log.
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink } from "vue-router";
import { ArrowLeft, Eye } from "@lucide/vue";
import { api, ApiError } from "@/lib/api";
import type { AdminMessage, AdminThread, CursorPage } from "@/lib/types";
import { formatDateTime, formatNumber } from "@/lib/format";
import Badge from "@/components/ui/Badge.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import UserCell from "@/components/UserCell.vue";

const props = defineProps<{ id: string }>();

const thread = ref<AdminThread | null>(null);
const messages = ref<AdminMessage[]>([]); // oldest first for reading
const cursor = ref<string | null>(null);
const loading = ref(false);
const loadingMore = ref(false);
const error = ref<string | null>(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    thread.value = (await api.get<{ data: AdminThread }>(`/admin/threads/${props.id}`)).data;
    const page = await api.get<CursorPage<AdminMessage>>(`/admin/threads/${props.id}/messages`);
    messages.value = [...page.data].reverse();
    cursor.value = page.meta.next_cursor;
  } catch (e) {
    error.value =
      e instanceof ApiError && e.status === 404
        ? "That conversation doesn't exist."
        : e instanceof Error
          ? e.message
          : "Something went wrong.";
  } finally {
    loading.value = false;
  }
}

async function older() {
  if (!cursor.value) return;
  loadingMore.value = true;
  try {
    const page = await api.get<CursorPage<AdminMessage>>(`/admin/threads/${props.id}/messages`, {
      cursor: cursor.value,
    });
    messages.value = [...[...page.data].reverse(), ...messages.value];
    cursor.value = page.meta.next_cursor;
  } finally {
    loadingMore.value = false;
  }
}

onMounted(load);
watch(() => props.id, load);

const who = (m: AdminMessage) =>
  m.author === "receiver" ? thread.value?.receiver : thread.value?.sender;
const roleLabel = (m: AdminMessage) => (m.author === "receiver" ? "Receiver" : "Sender");
const messageCount = computed(() => thread.value?.message_count ?? messages.value.length);
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-6">
    <RouterLink
      to="/threads"
      class="inline-flex min-h-11 w-fit items-center gap-2 text-sm text-fg-2 no-underline hover:text-fg"
      ><ArrowLeft class="size-4" aria-hidden="true" />Conversations</RouterLink
    >

    <DataState :loading="loading" :error="error" :has-data="Boolean(thread)" @retry="load">
      <div v-if="thread" class="grid gap-6">
        <header class="grid gap-1">
          <h1>{{ thread.label }}</h1>
          <p class="text-sm text-fg-2">
            {{ formatNumber(messageCount) }} messages · started
            {{ formatDateTime(thread.created_at) }}
            <Badge v-if="thread.blocked_at" tone="warn" class="ml-2">Blocked by the receiver</Badge>
          </p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
          <section class="surface grid gap-2 p-5" aria-labelledby="rcv-h">
            <h2 id="rcv-h" class="text-xs font-medium text-fg-3">Receiver · owns the link</h2>
            <UserCell :user="thread.receiver" />
          </section>
          <section class="surface grid gap-2 border-accent/40 p-5" aria-labelledby="snd-h">
            <h2 id="snd-h" class="text-xs font-medium text-fg-3">
              Sender · anonymous to the receiver
            </h2>
            <UserCell :user="thread.sender" />
          </section>
        </div>

        <p class="flex items-center gap-2 text-xs text-fg-3">
          <Eye class="size-4" aria-hidden="true" />Viewing this conversation and its messages was
          recorded in the audit log.
        </p>

        <section class="surface grid gap-4 p-5" aria-labelledby="log-h">
          <h2 id="log-h">Messages</h2>
          <div v-if="cursor" class="flex justify-center">
            <Button variant="ghost" size="sm" :loading="loadingMore" @click="older"
              >Load earlier messages</Button
            >
          </div>
          <ol class="grid gap-4">
            <li
              v-for="m in messages"
              :key="m.id"
              class="grid gap-1 rounded-control border border-line p-4"
              :class="m.author === 'sender' ? 'bg-canvas' : ''"
            >
              <p class="flex flex-wrap items-center gap-2 text-xs text-fg-3">
                <Badge :tone="m.author === 'sender' ? 'accent' : 'neutral'">{{
                  roleLabel(m)
                }}</Badge>
                <span class="font-medium text-fg-2">{{ who(m)?.name }}</span>
                <time :datetime="m.created_at">{{ formatDateTime(m.created_at) }}</time>
                <span v-if="m.read_at">· read</span>
              </p>
              <!-- message text is user input: rendered as text, never as HTML -->
              <p class="text-base whitespace-pre-wrap break-words">{{ m.body }}</p>
            </li>
          </ol>
        </section>
      </div>
    </DataState>
  </div>
</template>
