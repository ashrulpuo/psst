<script setup lang="ts">
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import GateLayout from "@/layouts/GateLayout.vue";
import Button from "@/components/ui/Button.vue";

const auth = useAuthStore();
const router = useRouter();

async function signOut() {
  await auth.logout();
  await router.replace({ name: "login" });
}
</script>

<template>
  <GateLayout>
    <header class="grid gap-1 text-center">
      <h1>This area is for staff</h1>
      <p class="text-sm text-fg-2">
        {{ auth.me?.email }} isn't an admin. If that's a mistake, ask an existing admin to grant
        access.
      </p>
    </header>
    <Button variant="outline" @click="signOut">Sign out</Button>
  </GateLayout>
</template>
