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
  <section class="form-card">
    <header class="head">
      <h1>{{ $t("welcome.title") }}</h1>
      <p class="text-secondary-copy">{{ $t("welcome.subtitle") }}</p>
    </header>
    <ClaimLinkForm />
  </section>
</template>

<style scoped lang="scss">
.head {
  display: grid;
  gap: 6px;
  text-align: center;
}
</style>
