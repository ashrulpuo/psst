import { defineStore } from "pinia";
import { computed, ref } from "vue";
import { api } from "@/lib/api";
import type { Overview } from "@/lib/types";

export const useOverviewStore = defineStore("overview", () => {
  const data = ref<Overview | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const openReports = computed(() => data.value?.totals.open_reports ?? 0);

  async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      data.value = (await api.get<{ data: Overview }>("/admin/overview")).data;
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Something went wrong.";
    } finally {
      loading.value = false;
    }
  }

  /** Keep the nav badge honest between server refreshes. */
  function adjustOpenReports(delta: number) {
    if (data.value)
      data.value.totals.open_reports = Math.max(0, data.value.totals.open_reports + delta);
  }

  return { data, loading, error, openReports, load, adjustOpenReports };
});
