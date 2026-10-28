<script setup lang="ts">
import AppButton from "@/components/AppButton.vue";
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
  <aside v-if="visible" class="prompt" :aria-label="$t('push.promptTitle')">
    <span class="icon" aria-hidden="true"><Bell :size="20" /></span>
    <div class="text">
      <strong>{{ $t("push.promptTitle") }}</strong>
      <span class="text-secondary-copy">{{ $t("push.promptBody") }}</span>
    </div>
    <div class="actions">
      <button type="button" class="dismiss" @click="dismiss">
        {{ $t("push.promptDismiss") }}
      </button>
      <AppButton size="sm" :disabled="push.busy.value" @click="push.enable()">
        {{ $t("push.promptEnable") }}
      </AppButton>
    </div>
  </aside>
</template>

<style scoped lang="scss">
.prompt {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  padding: 12px 14px;
  border: 2px solid var(--edge);
  border-radius: var(--r-card);
  background: var(--cream);
  box-shadow: 0 4px 0 var(--edge);
}
.dismiss {
  border: 0;
  background: transparent;
  color: var(--text-2);
  font: 700 0.8rem / 1 var(--font-sans);
  cursor: pointer;
}
.icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 2px solid var(--edge);
  border-radius: 12px;
  background: var(--lime);
  color: var(--ink);
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
