<script setup lang="ts">
import { watch } from "vue";
import { RouterView, useRouter } from "vue-router";
import { gateRoute } from "@/router";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
const router = useRouter();

// The route guard only runs on navigation, so react whenever the session moves between gates
// (signed in, 2FA verified, signed out): send the person to wherever that state belongs.
watch(
  () => auth.gate,
  async (gate, previous) => {
    if (!auth.ready || gate === previous) return;
    await router.replace(gate === "ready" ? { name: "dashboard" } : { name: gateRoute[gate] });
  },
);
</script>

<template>
  <RouterView />
</template>
