<script setup lang="ts">
// Pattern: Queue — keyword-flagged messages, oldest open first. Message text is admin-only data, shown as plain text.
import { onMounted, ref, watch } from "vue";
import { RouterLink } from "vue-router";
import { OctagonAlert } from "@lucide/vue";
import { api, ApiError } from "@/lib/api";
import { useCursorList } from "@/lib/useCursorList";
import type { AdminFlag, FlagStatus } from "@/lib/types";
import { formatDateTime, timeAgo } from "@/lib/format";
import Badge from "@/components/ui/Badge.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const tabs: { value: FlagStatus; label: string }[] = [
  { value: "open", label: "Open" },
  { value: "reviewed", label: "Reviewed" },
];
const status = ref<FlagStatus>("open");
const list = useCursorList<AdminFlag>("/admin/flags");
const loadList = () => list.reload({ status: status.value });

watch(status, () => void loadList());
onMounted(() => void loadList());

const busyId = ref<string | null>(null);
const actionError = ref("");

async function review(flag: AdminFlag) {
  busyId.value = flag.id;
  actionError.value = "";
  try {
    await api.post(`/admin/flags/${flag.id}/review`);
  } catch (e) {
    // 409: already reviewed (another tab or session), so it is done either way.
    if (!(e instanceof ApiError && e.status === 409)) {
      actionError.value = e instanceof Error ? e.message : "Something went wrong.";
      busyId.value = null;
      return;
    }
  }
  list.items.value = list.items.value.filter((f) => f.id !== flag.id);
  busyId.value = null;
}
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-5">
    <PageHeader
      title="Flagged messages"
      subtitle="Messages that matched a keyword. They were delivered normally; this is your review queue."
    />

    <div
      class="flex w-fit rounded-control border border-line bg-surface p-1"
      role="tablist"
      aria-label="Flag status"
    >
      <button
        v-for="t in tabs"
        :key="t.value"
        type="button"
        role="tab"
        :aria-selected="status === t.value"
        class="min-h-9 cursor-pointer rounded-lg px-4 text-sm font-medium transition-colors"
        :class="
          status === t.value ? 'bg-accent-soft text-accent-text' : 'text-fg-2 hover:bg-surface-2'
        "
        @click="status = t.value"
      >
        {{ t.label }}
      </button>
    </div>

    <p v-if="actionError" class="text-sm text-danger" role="alert">{{ actionError }}</p>

    <section class="surface overflow-hidden" aria-label="Flagged messages">
      <DataState
        :loading="list.loading.value"
        :error="list.error.value"
        :has-data="list.loaded.value"
        @retry="loadList"
      >
        <EmptyState
          v-if="list.loaded.value && !list.items.value.length"
          :icon="OctagonAlert"
          :title="status === 'open' ? 'Queue is clear' : 'Nothing here'"
          :body="status === 'open' ? 'No flagged messages are waiting.' : 'No reviewed flags yet.'"
        />
        <ul v-else class="divide-y divide-line">
          <li v-for="f in list.items.value" :key="f.id" class="grid gap-2 p-4">
            <div class="flex flex-wrap items-center gap-2">
              <Badge v-for="term in f.terms" :key="term" tone="warn">{{ term }}</Badge>
              <Badge>{{ f.sender_kind === "guest" ? "Guest" : "User" }}</Badge>
              <time
                class="text-xs text-fg-3"
                :datetime="f.created_at"
                :title="formatDateTime(f.created_at)"
                >{{ timeAgo(f.created_at) }}</time
              >
              <span v-if="f.reviewed_at" class="text-xs text-fg-3"
                >Reviewed {{ formatDateTime(f.reviewed_at) }}</span
              >
            </div>
            <p class="text-base whitespace-pre-wrap break-words">{{ f.body }}</p>
            <div class="flex flex-wrap items-center gap-3">
              <RouterLink :to="`/threads/${f.thread_id}`" class="text-sm"
                >Open conversation</RouterLink
              >
              <Button
                v-if="!f.reviewed_at"
                variant="outline"
                :loading="busyId === f.id"
                :disabled="busyId !== null && busyId !== f.id"
                @click="review(f)"
                >Mark reviewed</Button
              >
            </div>
          </li>
        </ul>
      </DataState>
      <div v-if="list.cursor.value" class="flex justify-center border-t border-line p-3">
        <Button variant="ghost" :loading="list.loading.value" @click="list.more({ status })"
          >Load more</Button
        >
      </div>
    </section>
  </div>
</template>
