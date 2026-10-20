<script setup lang="ts">
// Pattern: List / Index — the append-only record of what admins did and saw.
import { onMounted, ref, watch } from "vue";
import { RouterLink } from "vue-router";
import { History } from "@lucide/vue";
import { useCursorList } from "@/lib/useCursorList";
import type { AuditEntry } from "@/lib/types";
import { formatDateTime, humanizeAction } from "@/lib/format";
import Badge from "@/components/ui/Badge.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import UserCell from "@/components/UserCell.vue";

const list = useCursorList<AuditEntry>("/admin/audit");
const action = ref("");

const actions = [
  "user.viewed",
  "user.banned",
  "user.unbanned",
  "thread.viewed",
  "thread.messages_viewed",
  "report.viewed",
  "report.reviewed",
  "report.dismissed",
  "admin.2fa_enrolled",
  "admin.2fa_verified",
  "admin.2fa_failed",
];
const query = () => ({ action: action.value || undefined });
watch(action, () => void list.reload(query()));
onMounted(() => void list.reload(query()));

/** Risky or failed actions stand out; routine views stay quiet. */
const tone = (a: string) =>
  a.includes("banned") || a.includes("failed")
    ? "danger"
    : a.includes("viewed")
      ? "neutral"
      : "accent";

const subjectLink = (e: AuditEntry): string | null => {
  if (!e.subject_id) return null;
  if (e.subject_type === "User") return `/users/${e.subject_id}`;
  if (e.subject_type === "Thread") return `/threads/${e.subject_id}`;
  if (e.subject_type === "Report") return `/reports/${e.subject_id}`;
  return null;
};
const details = (e: AuditEntry) =>
  Object.entries(e.meta ?? {})
    .map(([k, v]) => `${k}: ${String(v)}`)
    .join(" · ");
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-5">
    <PageHeader
      title="Audit log"
      subtitle="Append-only. Every identity reveal and moderation action lands here and cannot be edited."
    />

    <div class="flex items-center gap-2">
      <label for="audit-action" class="text-sm font-medium">Action</label>
      <select
        id="audit-action"
        v-model="action"
        class="min-h-11 rounded-control border border-line-strong bg-surface px-3 text-base text-fg"
      >
        <option value="">All actions</option>
        <option v-for="a in actions" :key="a" :value="a">{{ humanizeAction(a) }}</option>
      </select>
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
          :icon="History"
          title="No entries"
          body="Nothing has been recorded for this filter."
        />
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[52rem] text-sm">
            <caption class="sr-only-keep">
              Audit log
            </caption>
            <thead class="bg-surface-2 text-left text-fg-2">
              <tr>
                <th scope="col" class="px-4 py-3 font-medium">When</th>
                <th scope="col" class="px-4 py-3 font-medium">Admin</th>
                <th scope="col" class="px-4 py-3 font-medium">Action</th>
                <th scope="col" class="px-4 py-3 font-medium">Subject</th>
                <th scope="col" class="px-4 py-3 font-medium">Details</th>
                <th scope="col" class="px-4 py-3 font-medium">IP</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="e in list.items.value" :key="e.id" class="border-t border-line align-top">
                <td class="px-4 py-3 whitespace-nowrap text-fg-2">
                  <time :datetime="e.created_at">{{ formatDateTime(e.created_at) }}</time>
                </td>
                <td class="px-4 py-3">
                  <UserCell v-if="e.actor" :user="e.actor" compact /><span v-else class="text-fg-3"
                    >deleted account</span
                  >
                </td>
                <td class="px-4 py-3">
                  <Badge :tone="tone(e.action)">{{ humanizeAction(e.action) }}</Badge>
                </td>
                <td class="px-4 py-3">
                  <RouterLink v-if="subjectLink(e)" :to="subjectLink(e)!">{{
                    e.subject_type
                  }}</RouterLink>
                  <span v-else class="text-fg-3">—</span>
                </td>
                <td class="max-w-64 px-4 py-3 text-xs break-words text-fg-2">
                  {{ details(e) || "—" }}
                </td>
                <td class="px-4 py-3 font-mono text-xs text-fg-3">{{ e.ip ?? "—" }}</td>
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
