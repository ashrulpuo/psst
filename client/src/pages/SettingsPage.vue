<script setup lang="ts">
// Pattern: Settings — grouped sections in a single column.
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useMeta, useQuasar } from "quasar";
import { Copy, LogOut, Share2 } from "@lucide/vue";
import { applyTheme, THEME_KEY, type ThemeChoice } from "@/boot/theme";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";
import { copyText } from "@/utils/clipboard";
import AppAvatar from "@/components/AppAvatar.vue";

useMeta({ title: "Settings · Psst" });

const $q = useQuasar();
const router = useRouter();
const auth = useAuthStore();
const messaging = useMessagingStore();

const canShare = typeof navigator !== "undefined" && "share" in navigator;

async function copy() {
  const ok = await copyText(auth.profileLink);
  $q.notify({
    type: ok ? "positive" : "warning",
    message: ok ? "Link copied" : auth.profileLink,
    position: "top"
  });
}
async function share() {
  try {
    await navigator.share({
      title: "Psst",
      text: "Send me an anonymous message",
      url: auth.profileLink
    });
  } catch {
    /* dismissed */
  }
}

const theme = ref<ThemeChoice>(
  (() => {
    try {
      const saved = localStorage.getItem(THEME_KEY);
      return saved === "light" || saved === "dark" ? saved : "auto";
    } catch {
      return "auto";
    }
  })()
);
function setTheme(choice: ThemeChoice) {
  theme.value = choice;
  applyTheme(choice);
  try {
    localStorage.setItem(THEME_KEY, choice);
  } catch {
    /* non-fatal */
  }
}

const themes: { value: ThemeChoice; label: string }[] = [
  { value: "auto", label: "Match device" },
  { value: "light", label: "Light" },
  { value: "dark", label: "Dark" }
];

const signingOut = ref(false);
async function signOut() {
  signingOut.value = true;
  try {
    await auth.logout();
  } finally {
    messaging.reset();
    signingOut.value = false;
    await router.replace("/login");
  }
}
</script>

<template>
  <div class="page">
    <div class="col">
      <h1>{{ $t("settings.title") }}</h1>

      <section class="surface block" aria-labelledby="link-h">
        <h2 id="link-h">{{ $t("settings.linkTitle") }}</h2>
        <p class="text-secondary-copy">{{ $t("settings.linkBody") }}</p>
        <output class="link">{{ auth.profileLink }}</output>
        <div class="row">
          <q-btn color="primary" unelevated no-caps @click="copy"
            ><Copy :size="18" class="ico" />{{ $t("settings.copy") }}</q-btn
          >
          <q-btn v-if="canShare" outline unelevated no-caps @click="share"
            ><Share2 :size="18" class="ico" />{{ $t("settings.share") }}</q-btn
          >
        </div>
      </section>

      <section class="surface block" aria-labelledby="look-h">
        <h2 id="look-h">{{ $t("settings.appearance") }}</h2>
        <div
          class="seg"
          role="radiogroup"
          :aria-label="$t('settings.appearance')"
        >
          <button
            v-for="t in themes"
            :key="t.value"
            type="button"
            role="radio"
            class="seg-btn"
            :class="{ on: theme === t.value }"
            :aria-checked="theme === t.value"
            @click="setTheme(t.value)"
          >
            {{ t.label }}
          </button>
        </div>
      </section>

      <section class="surface block" aria-labelledby="acct-h">
        <h2 id="acct-h">{{ $t("settings.account") }}</h2>
        <div class="who">
          <AppAvatar
            :name="auth.user?.name ?? ''"
            :src="auth.user?.avatar_url"
            :size="48"
          />
          <div class="who-text">
            <strong>{{ auth.user?.name }}</strong>
            <span class="text-secondary-copy">{{
              $t("settings.signedInAs", { email: auth.user?.email })
            }}</span>
          </div>
        </div>
        <q-btn outline unelevated no-caps :loading="signingOut" @click="signOut"
          ><LogOut :size="18" class="ico" />{{ $t("settings.signOut") }}</q-btn
        >
      </section>
    </div>
  </div>
</template>

<style scoped lang="scss">
.col {
  display: grid;
  gap: var(--s-4);
  max-width: 640px;
  margin-inline: auto;
}
.block {
  display: grid;
  gap: var(--s-3);
  padding: var(--s-5);
}
.block h2 {
  font-size: var(--text-body);
}
.link {
  display: block;
  padding: var(--s-3) var(--s-4);
  border-radius: var(--r-control);
  background: var(--canvas);
  border: 1px solid var(--border);
  font-size: var(--text-secondary);
  overflow-wrap: anywhere;
}
.row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--s-2);
}
.ico {
  margin-right: var(--s-2);
}
.seg {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  padding: 4px;
  gap: 4px;
  border-radius: var(--r-control);
  background: var(--canvas);
  border: 1px solid var(--border);
}
.seg-btn {
  min-height: 44px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: var(--text-2);
  font: inherit;
  font-size: var(--text-secondary);
  font-weight: 500;
  cursor: pointer;
  transition: background-color var(--dur) var(--ease);
}
.seg-btn:hover {
  background: var(--surface-hover);
}
.seg-btn.on {
  background: var(--surface);
  color: var(--text);
  box-shadow: var(--shadow-soft);
  border: 1px solid var(--border);
}
.who {
  display: flex;
  align-items: center;
  gap: var(--s-3);
}
.who-text {
  display: grid;
  min-width: 0;
}
.who-text span {
  overflow-wrap: anywhere;
}
</style>
