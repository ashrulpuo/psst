<script setup lang="ts">
import { ShieldCheck } from "@lucide/vue";
</script>

<template>
  <main class="grid min-h-dvh place-items-center px-4 py-8">
    <div class="grid w-full max-w-md gap-6">
      <div class="flex items-center justify-center gap-2 text-lg font-semibold">
        <span
          class="grid size-8 place-items-center rounded-[10px] bg-accent text-on-accent"
          aria-hidden="true"
          ><ShieldCheck class="size-5"
        /></span>
        Psst Admin
      </div>
      <div class="surface grid gap-5 p-6 sm:p-8"><slot /></div>
    </div>
  </main>
</template>
