<script setup lang="ts">
// Pattern: List / Index — scan and manage many accounts.
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { ChevronRight, Search, Users } from "@lucide/vue";
import { useCursorList } from "@/lib/useCursorList";
import type { AdminUser } from "@/lib/types";
import { timeAgo, formatDate } from "@/lib/format";
import Badge from "@/components/ui/Badge.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import UserCell from "@/components/UserCell.vue";

const router = useRouter();
const list = useCursorList<AdminUser>("/admin/users");

const search = ref("");
const status = ref<"all" | "admin" | "banned">("all");
const statuses = [
  { value: "all", label: "All" },
  { value: "admin", label: "Admins" },
  { value: "banned", label: "Banned" },
] as const;

const query = () => ({
  search: search.value.trim() || undefined,
  status: status.value === "all" ? undefined : status.value,
});
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
  clearTimeout(timer);
  timer = setTimeout(() => void list.reload(query()), 300);
});
watch(status, () => void list.reload(query()));
onMounted(() => void list.reload(query()));
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-5">
    <PageHeader
      title="Users"
      subtitle="Everyone who has signed in to Psst, with their real identity."
    />

    <div class="flex flex-wrap items-center gap-3">
      <div class="relative min-w-60 flex-1 sm:max-w-sm">
        <label for="user-search" class="sr-only-keep">Search users</label>
        <Search
          class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-fg-3"
          aria-hidden="true"
        />
        <input
          id="user-search"
          v-model="search"
          type="search"
          placeholder="Search name, username or email"
          class="min-h-11 w-full rounded-control border border-line-strong bg-surface pr-3 pl-9 text-base text-fg placeholder:text-fg-3"
        />
      </div>
      <div
        class="flex rounded-control border border-line bg-surface p-1"
        role="radiogroup"
        aria-label="Filter by status"
      >
        <button
          v-for="s in statuses"
          :key="s.value"
          type="button"
          role="radio"
          :aria-checked="status === s.value"
          class="min-h-9 cursor-pointer rounded-lg px-3 text-sm font-medium transition-colors"
          :class="
            status === s.value ? 'bg-accent-soft text-accent-text' : 'text-fg-2 hover:bg-surface-2'
          "
          @click="status = s.value"
        >
          {{ s.label }}
        </button>
      </div>
    </div>

    <!-- the table always sits in a visible L1 frame, even when empty or loading -->
    <div class="surface overflow-hidden">
      <DataState
        :loading="list.loading.value"
        :error="list.error.value"
        :has-data="list.loaded.value"
        @retry="list.reload(query())"
      >
        <EmptyState
          v-if="list.loaded.value && !list.items.value.length"
          :icon="Users"
          title="No users match"
          body="Try a different search or clear the filter."
        />
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[44rem] text-sm">
            <caption class="sr-only-keep">
              Users
            </caption>
            <thead class="bg-surface-2 text-left text-fg-2">
              <tr>
                <th scope="col" class="px-4 py-3 font-medium">User</th>
                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                <th scope="col" class="px-4 py-3 font-medium">Last seen</th>
                <th scope="col" class="px-4 py-3 font-medium">Joined</th>
                <th scope="col" class="w-10 px-2 py-3"><span class="sr-only-keep">Open</span></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="u in list.items.value"
                :key="u.id"
                class="cursor-pointer border-t border-line transition-colors hover:bg-surface-2"
                @click="router.push(`/users/${u.id}`)"
              >
                <td class="px-4 py-3"><UserCell :user="u" /></td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-1">
                    <Badge v-if="u.role === 'admin'" tone="accent">Admin</Badge>
                    <Badge v-if="u.banned_at" tone="danger">Banned</Badge>
                    <Badge v-else-if="u.role !== 'admin'" tone="ok">Active</Badge>
                  </div>
                </td>
                <td class="px-4 py-3 text-fg-2">{{ timeAgo(u.last_login_at) }}</td>
                <td class="px-4 py-3 text-fg-2">{{ formatDate(u.created_at) }}</td>
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
