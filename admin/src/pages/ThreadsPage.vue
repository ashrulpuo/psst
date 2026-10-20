<script setup lang="ts">
// Pattern: List / Index — conversations with BOTH real identities visible to admins.
import { computed, onMounted, watch } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { ChevronRight, MessagesSquare, X } from "@lucide/vue";
import { useCursorList } from "@/lib/useCursorList";
import type { AdminThread } from "@/lib/types";
import { timeAgo } from "@/lib/format";
import Badge from "@/components/ui/Badge.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import UserCell from "@/components/UserCell.vue";

const route = useRoute();
const router = useRouter();
const list = useCursorList<AdminThread>("/admin/threads");

const userId = computed(() =>
  typeof route.query.user === "string" ? route.query.user : undefined,
);
const blockedOnly = computed(() => route.query.blocked === "1");
const query = () => ({ user_id: userId.value, blocked: blockedOnly.value ? "1" : undefined });

watch(
  () => route.query,
  () => void list.reload(query()),
);
onMounted(() => void list.reload(query()));

/** The name for the filter chip comes from the rows we already have, so no extra (audited) profile lookup. */
const filterName = computed(() => {
  const t = list.items.value[0];
  if (!t || !userId.value) return null;
  return (t.receiver.id === userId.value ? t.receiver : t.sender)?.name ?? "Guest";
});

function setQuery(patch: Record<string, string | undefined>) {
  const next = { ...route.query, ...patch };
  for (const key of Object.keys(next)) if (next[key] === undefined) delete next[key];
  void router.replace({ query: next });
}
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-5">
    <PageHeader
      title="Conversations"
      subtitle="Who wrote to whom. Opening a conversation is recorded in the audit log."
    >
      <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm">
        <input
          type="checkbox"
          class="size-5 accent-[var(--accent)]"
          :checked="blockedOnly"
          @change="setQuery({ blocked: blockedOnly ? undefined : '1' })"
        />
        Blocked only
      </label>
    </PageHeader>

    <div v-if="userId" class="flex items-center gap-2">
      <Badge tone="accent" class="!px-3 !py-1.5 text-sm"
        >Filtered: {{ filterName ?? "one user" }}</Badge
      >
      <Button variant="ghost" size="sm" @click="setQuery({ user: undefined })"
        ><X class="size-4" aria-hidden="true" />Clear filter</Button
      >
    </div>

    <div class="surface overflow-hidden">
      <DataState
        :loading="list.loading.value"
        :error="list.error.value"
        :has-data="list.loaded.value"
        @retry="list.reload(query())"
      >
        <EmptyState
          v-if="list.loaded.value && !list.items.value.length"
          :icon="MessagesSquare"
          title="No conversations"
          body="Nothing matches this filter yet."
        />
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[52rem] text-sm">
            <caption class="sr-only-keep">
              Conversations
            </caption>
            <thead class="bg-surface-2 text-left text-fg-2">
              <tr>
                <th scope="col" class="px-4 py-3 font-medium">Conversation</th>
                <th scope="col" class="px-4 py-3 font-medium">Receiver</th>
                <th scope="col" class="px-4 py-3 font-medium">Sender (real identity)</th>
                <th scope="col" class="px-4 py-3 font-medium">Last activity</th>
                <th scope="col" class="w-10 px-2 py-3"><span class="sr-only-keep">Open</span></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="t in list.items.value"
                :key="t.id"
                class="cursor-pointer border-t border-line transition-colors hover:bg-surface-2"
                @click="router.push(`/threads/${t.id}`)"
              >
                <td class="px-4 py-3">
                  <RouterLink
                    :to="`/threads/${t.id}`"
                    class="font-medium text-fg no-underline hover:underline"
                    @click.stop
                    >{{ t.label }}</RouterLink
                  >
                  <Badge v-if="t.blocked_at" tone="warn" class="ml-2">Blocked</Badge>
                </td>
                <td class="px-4 py-3"><UserCell :user="t.receiver" compact /></td>
                <td class="px-4 py-3"><UserCell :user="t.sender" compact /></td>
                <td class="px-4 py-3 text-fg-2">{{ timeAgo(t.last_message_at) }}</td>
                <td class="px-2 py-3 text-fg-3">
                  <ChevronRight class="size-4" aria-hidden="true" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </DataState>
      <div v-if="list.cursor.value" class="flex justify-center border-t border-line p-3">
        <Button variant="ghost" :loading="list.loading.value" @click="list.more(query())"
          >Load more</Button
        >
      </div>
    </div>
  </div>
</template>
