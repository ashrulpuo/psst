<script setup lang="ts">
// A person with their real identity. Links to the admin profile.
import { RouterLink } from "vue-router";
import type { UserBrief } from "@/lib/types";
import Avatar from "./ui/Avatar.vue";

defineProps<{ user: UserBrief | null; compact?: boolean }>();
</script>

<template>
  <div v-if="!user" class="flex min-w-0 items-center gap-3">
    <Avatar name="Guest" :size="compact ? 28 : 36" />
    <span class="grid min-w-0">
      <span class="truncate text-sm font-medium">Guest</span>
      <span class="truncate text-xs text-fg-3">Never signed in · no account</span>
    </span>
  </div>
  <RouterLink
    v-else
    :to="`/users/${user.id}`"
    class="group flex min-w-0 items-center gap-3 text-fg no-underline"
    @click.stop
  >
    <Avatar :name="user.name" :src="user.avatar_url" :size="compact ? 28 : 36" />
    <span class="grid min-w-0">
      <span class="truncate text-sm font-medium group-hover:underline"
        >{{ user.name
        }}<span v-if="user.banned_at" class="ml-1 text-xs font-normal text-danger"
          >(banned)</span
        ></span
      >
      <span class="truncate text-xs text-fg-3"
        >{{ user.username ? `@${user.username}` : "no username yet" }} · {{ user.email }}</span
      >
    </span>
  </RouterLink>
</template>
