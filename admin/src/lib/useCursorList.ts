import { ref, type Ref } from "vue";
import { api } from "./api";
import type { CursorPage } from "./types";

/**
 * Cursor-paginated list with "load more". A newer reload always wins: a slow response for an old query
 * (the user kept typing) is discarded instead of overwriting fresher results.
 */
export function useCursorList<T>(path: string) {
  const items = ref([]) as Ref<T[]>;
  const cursor = ref<string | null>(null);
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref<string | null>(null);
  let generation = 0;

  async function fetchPage(query: Record<string, string | undefined>, more: boolean) {
    const mine = ++generation;
    loading.value = true;
    error.value = null;
    try {
      const page = await api.get<CursorPage<T>>(path, {
        ...query,
        cursor: more ? (cursor.value ?? undefined) : undefined,
      });
      if (mine !== generation) return;
      items.value = more ? [...items.value, ...page.data] : page.data;
      cursor.value = page.meta.next_cursor;
      loaded.value = true;
    } catch (e) {
      if (mine !== generation) return;
      error.value = e instanceof Error ? e.message : "Something went wrong. Please try again.";
    } finally {
      if (mine === generation) loading.value = false;
    }
  }

  return {
    items,
    cursor,
    loading,
    loaded,
    error,
    reload: (query: Record<string, string | undefined> = {}) => fetchPage(query, false),
    more: (query: Record<string, string | undefined> = {}) =>
      cursor.value ? fetchPage(query, true) : Promise.resolve(),
  };
}
