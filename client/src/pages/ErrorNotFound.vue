<script setup lang="ts">
import { Compass } from "@lucide/vue";
import EmptyState from "@/components/EmptyState.vue";
</script>

<template>
  <div class="wrap">
    <EmptyState
      :icon="Compass"
      :title="$t('errors.notFound')"
      :body="$t('errors.notFoundBody')"
    >
      <q-btn
        color="primary"
        unelevated
        no-caps
        :label="$t('profile.goHome')"
        to="/"
      />
    </EmptyState>
  </div>
</template>

<style scoped lang="scss">
.wrap {
  display: grid;
  min-height: calc(100dvh - var(--safe-top));
  place-items: center;
  padding: var(--s-5) var(--s-4);
}
</style>
