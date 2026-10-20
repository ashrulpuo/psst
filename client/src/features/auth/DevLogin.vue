<script setup lang="ts">
import { ref } from "vue";
import { useAuthStore } from "@/stores/auth";

const emit = defineEmits<{ signedIn: []; failed: [] }>();
const auth = useAuthStore();
const busy = ref<string | null>(null);

// Matches database/seeders/DatabaseSeeder.php. Only rendered when PSST_DEV_LOGIN is set at build time.
const demoUsers = [
  { key: "alice", label: "Alice" },
  { key: "bob", label: "Bob" },
  { key: "carol", label: "Carol" }
];

async function go(key: string) {
  busy.value = key;
  try {
    await auth.devLogin(`${key}@example.test`);
    emit("signedIn");
  } catch {
    emit("failed");
  } finally {
    busy.value = null;
  }
}
</script>

<template>
  <section class="demo" aria-labelledby="demo-title">
    <h2 id="demo-title" class="text-meta">{{ $t("login.demo") }}</h2>
    <div class="demo-row">
      <q-btn
        v-for="u in demoUsers"
        :key="u.key"
        :label="u.label"
        :loading="busy === u.key"
        :disable="busy !== null"
        outline
        no-caps
        unelevated
        @click="go(u.key)"
      />
    </div>
  </section>
</template>

<style scoped lang="scss">
.demo {
  display: grid;
  gap: var(--s-2);
  padding-top: var(--s-4);
  border-top: 1px dashed var(--border-strong);
}
.demo-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--s-2);
}
.demo h2 {
  font-size: var(--text-meta);
  font-weight: 500;
  text-transform: none;
}
</style>
