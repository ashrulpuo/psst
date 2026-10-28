<script setup lang="ts">
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import { useMeta } from "quasar";
import { useAuthStore } from "@/stores/auth";
import ClaimLinkForm from "@/components/ClaimLinkForm.vue";

useMeta({ title: "Your link · Psst" });

const auth = useAuthStore();
const router = useRouter();

onMounted(() => {
  if (auth.isAuthenticated && auth.user?.username) void router.replace("/link");
});
</script>

<template>
  <section class="surface card">
    <header class="head">
      <h1>{{ $t("welcome.title") }}</h1>
      <p class="text-secondary-copy">{{ $t("welcome.subtitle") }}</p>
    </header>
    <ClaimLinkForm />
  </section>
</template>

<style scoped lang="scss">
.card {
  display: grid;
  gap: var(--s-5);
  padding: var(--s-6) var(--s-5);
}
.head {
  display: grid;
  gap: var(--s-2);
  text-align: center;
}
</style>
