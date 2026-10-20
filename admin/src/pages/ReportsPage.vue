<script setup lang="ts">
// Pattern: Split Pane — triage the report queue without leaving it. Narrow screens show one pane at a time.
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { ArrowLeft, Flag, ShieldAlert } from "@lucide/vue";
import { api, ApiError } from "@/lib/api";
import { useCursorList } from "@/lib/useCursorList";
import type { AdminReport, ReportStatus } from "@/lib/types";
import { formatDateTime, timeAgo } from "@/lib/format";
import { useOverviewStore } from "@/stores/overview";
import Badge from "@/components/ui/Badge.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import Dialog from "@/components/ui/Dialog.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import UserCell from "@/components/UserCell.vue";

const props = defineProps<{ id?: string }>();
const router = useRouter();
const overview = useOverviewStore();

const tabs: { value: ReportStatus; label: string }[] = [
  { value: "open", label: "Open" },
  { value: "reviewed", label: "Reviewed" },
  { value: "dismissed", label: "Dismissed" },
];
const status = ref<ReportStatus>("open");
const list = useCursorList<AdminReport>("/admin/reports");
const loadList = () => list.reload({ status: status.value });

watch(status, () => {
  void loadList();
  if (props.id) void router.replace("/reports");
});
onMounted(() => {
  void loadList();
  if (!overview.data) void overview.load();
});

// ---- the open report ----
const report = ref<AdminReport | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

async function loadReport() {
  if (!props.id) {
    report.value = null;
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    report.value = (await api.get<{ data: AdminReport }>(`/admin/reports/${props.id}`)).data;
  } catch (e) {
    report.value = null;
    error.value =
      e instanceof ApiError && e.status === 404
        ? "That report doesn't exist."
        : e instanceof Error
          ? e.message
          : "Something went wrong.";
  } finally {
    loading.value = false;
  }
}
watch(() => props.id, loadReport, { immediate: true });

// ---- actions ----
const busy = ref(false);
const actionError = ref("");
const banOpen = ref(false);

/** Hand the moderator the next report in the queue, or back to the list when it is empty. */
async function advance(handledId: string) {
  const items = list.items.value;
  const index = items.findIndex((r) => r.id === handledId);
  const next = items[index + 1] ?? items[index - 1];
  list.items.value = items.filter((r) => r.id !== handledId);
  overview.adjustOpenReports(-1);
  await router.replace(next && status.value === "open" ? `/reports/${next.id}` : "/reports");
}

async function resolve(outcome: "reviewed" | "dismissed", banAuthor = false) {
  if (!report.value) return;
  const id = report.value.id;
  busy.value = true;
  actionError.value = "";
  try {
    await api.post(`/admin/reports/${id}/resolve`, {
      status: outcome,
      ...(banAuthor ? { ban_author: true } : {}),
    });
    banOpen.value = false;
    await advance(id);
  } catch (e) {
    actionError.value = e instanceof Error ? e.message : "Something went wrong.";
  } finally {
    busy.value = false;
  }
}

const reportedName = computed(() => report.value?.reported_user.name ?? "this user");
const canBan = computed(
  () =>
    report.value &&
    report.value.reported_user.role !== "admin" &&
    !report.value.reported_user.banned_at,
);
const statusTone = (s: ReportStatus) =>
  s === "open" ? "warn" : s === "reviewed" ? "ok" : "neutral";
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-5">
    <PageHeader
      title="Reports"
      subtitle="Oldest first. Work through the queue and every action is recorded."
    />

    <div
      class="flex w-fit rounded-control border border-line bg-surface p-1"
      role="tablist"
      aria-label="Report status"
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
        {{ t.label
        }}<span v-if="t.value === 'open' && overview.openReports" class="ml-1.5 text-xs"
          >({{ overview.openReports }})</span
        >
      </button>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-[22rem_minmax(0,1fr)]">
      <!-- queue -->
      <section
        class="surface overflow-hidden"
        :class="id ? 'hidden lg:block' : ''"
        aria-label="Reports"
      >
        <DataState
          :loading="list.loading.value"
          :error="list.error.value"
          :has-data="list.loaded.value"
          @retry="loadList"
        >
          <EmptyState
            v-if="list.loaded.value && !list.items.value.length"
            :icon="Flag"
            :title="status === 'open' ? 'Queue is clear' : 'Nothing here'"
            :body="status === 'open' ? 'No reports are waiting.' : 'No reports with this status.'"
          />
          <ul v-else class="divide-y divide-line">
            <li v-for="r in list.items.value" :key="r.id">
              <RouterLink
                :to="`/reports/${r.id}`"
                class="grid gap-1 px-4 py-3 text-fg no-underline transition-colors hover:bg-surface-2"
                :class="r.id === id ? 'bg-accent-soft' : ''"
                :aria-current="r.id === id ? 'page' : undefined"
              >
                <span class="flex items-center justify-between gap-2">
                  <span class="truncate text-sm font-medium">{{ r.reason }}</span>
                  <time class="shrink-0 text-xs text-fg-3" :datetime="r.created_at">{{
                    timeAgo(r.created_at)
                  }}</time>
                </span>
                <span class="truncate text-xs text-fg-2">Against {{ r.reported_user.name }}</span>
                <span class="truncate text-xs text-fg-3">“{{ r.message.body }}”</span>
              </RouterLink>
            </li>
          </ul>
        </DataState>
        <div v-if="list.cursor.value" class="flex justify-center border-t border-line p-3">
          <Button variant="ghost" :loading="list.loading.value" @click="list.more({ status })"
            >Load more</Button
          >
        </div>
      </section>

      <!-- detail -->
      <section :class="id ? '' : 'hidden lg:block'" aria-label="Report detail">
        <RouterLink
          v-if="id"
          to="/reports"
          class="mb-3 inline-flex min-h-11 items-center gap-2 text-sm text-fg-2 no-underline hover:text-fg lg:hidden"
          ><ArrowLeft class="size-4" aria-hidden="true" />Back to queue</RouterLink
        >

        <div v-if="!id" class="surface">
          <EmptyState
            :icon="Flag"
            title="Select a report"
            body="Choose a report on the left to see it in context."
          />
        </div>

        <DataState
          v-else
          :loading="loading"
          :error="error"
          :has-data="Boolean(report)"
          @retry="loadReport"
        >
          <div v-if="report" class="grid gap-5">
            <header class="surface grid gap-3 p-5">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg">Report</h2>
                <Badge :tone="statusTone(report.status)">{{ report.status }}</Badge>
                <time class="text-xs text-fg-3" :datetime="report.created_at">{{
                  formatDateTime(report.created_at)
                }}</time>
              </div>
              <p class="text-base">“{{ report.reason }}”</p>
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                  <p class="text-xs font-medium text-fg-3">Reported by</p>
                  <UserCell :user="report.reporter" />
                </div>
                <div class="grid gap-1">
                  <p class="text-xs font-medium text-fg-3">Message written by</p>
                  <UserCell :user="report.reported_user" />
                </div>
              </div>
              <p v-if="report.reviewer" class="text-xs text-fg-3">
                Handled by {{ report.reviewer.name }} · {{ formatDateTime(report.reviewed_at) }}
              </p>
            </header>

            <section class="surface grid gap-3 p-5" aria-labelledby="ctx-h">
              <div class="flex items-center justify-between gap-2">
                <h3 id="ctx-h">In context</h3>
                <RouterLink :to="`/threads/${report.thread_id}`" class="text-sm"
                  >Full conversation</RouterLink
                >
              </div>
              <ol class="grid gap-2">
                <li
                  v-for="m in report.context"
                  :key="m.id"
                  class="grid gap-0.5 rounded-control border p-3"
                  :class="
                    m.id === report.message.id ? 'border-danger/60 bg-danger-soft' : 'border-line'
                  "
                >
                  <p class="text-xs text-fg-3">
                    <span class="font-medium text-fg-2">{{
                      m.author === "sender" ? "Sender" : "Receiver"
                    }}</span>
                    · {{ formatDateTime(m.created_at)
                    }}<span v-if="m.id === report.message.id" class="font-medium text-danger">
                      · reported</span
                    >
                  </p>
                  <p class="text-base whitespace-pre-wrap break-words">{{ m.body }}</p>
                </li>
              </ol>
            </section>

            <div v-if="report.status === 'open'" class="surface grid gap-3 p-5">
              <h3>Decision</h3>
              <p v-if="actionError" class="text-sm text-danger" role="alert">{{ actionError }}</p>
              <div class="flex flex-wrap gap-2">
                <Button variant="outline" :loading="busy" @click="resolve('dismissed')"
                  >Dismiss</Button
                >
                <Button :loading="busy" @click="resolve('reviewed')">Mark reviewed</Button>
                <Button v-if="canBan" variant="danger" :disabled="busy" @click="banOpen = true"
                  ><ShieldAlert class="size-4" aria-hidden="true" />Review and ban
                  {{ reportedName }}</Button
                >
              </div>
            </div>
          </div>
        </DataState>
      </section>
    </div>

    <Dialog
      v-model:open="banOpen"
      :title="`Ban ${reportedName}?`"
      description="Marks the report reviewed and suspends the person who wrote the message."
    >
      <ul class="list-disc space-y-1 pl-5 text-sm text-fg-2">
        <li>They are signed out everywhere and can't sign back in.</li>
        <li>Their push notifications stop and their link stops working.</li>
        <li>The reason is recorded as this report.</li>
      </ul>
      <template #actions>
        <Button variant="ghost" @click="banOpen = false">Cancel</Button>
        <Button variant="danger" :loading="busy" @click="resolve('reviewed', true)"
          >Ban {{ reportedName }}</Button
        >
      </template>
    </Dialog>
  </div>
</template>
