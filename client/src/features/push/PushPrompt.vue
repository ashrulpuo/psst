<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Bell } from "@lucide/vue";
import { usePush } from "./usePush";

const DISMISSED_KEY = "psst.push.prompt.dismissed";

const push = usePush();
const dismissed = ref(false);

onMounted(async () => {
  try {
    dismissed.value = localStorage.getItem(DISMISSED_KEY) === "1";
  } catch {
    /* storage unavailable: just show it */
  }
  await push.refresh();
});

// Only ask when asking can succeed: supported, configured, not already decided.
const visible = computed(() => push.state.value === "off" && !dismissed.value);

function dismiss() {
  dismissed.value = true;
  try {
    localStorage.setItem(DISMISSED_KEY, "1");
  } catch {
    /* non-fatal */
  }
}
</script>

<template>
  <aside
    v-if="visible"
    class="surface prompt"
    :aria-label="$t('push.promptTitle')"
  >
    <span class="icon" aria-hidden="true"><Bell :size="20" /></span>
    <div class="text">
      <strong>{{ $t("push.promptTitle") }}</strong>
      <span class="text-secondary-copy">{{ $t("push.promptBody") }}</span>
    </div>
    <div class="actions">
      <q-btn
        flat
        no-caps
        dense
        :label="$t('push.promptDismiss')"
        @click="dismiss"
      />
      <q-btn
        color="primary"
        unelevated
        no-caps
        dense
        :label="$t('push.promptEnable')"
        :loading="push.busy.value"
        @click="push.enable()"
      />
    </div>
  </aside>
</template>

<style scoped lang="scss">
.prompt {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: var(--s-3);
  margin-bottom: var(--s-3);
  padding: var(--s-3) var(--s-4);
}
.icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--accent-soft);
  color: var(--accent-text);
}
.text {
  display: grid;
  gap: 2px;
}
.actions {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
  gap: var(--s-2);
}
</style>
