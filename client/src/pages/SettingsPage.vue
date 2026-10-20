<script setup lang="ts">
// Pattern: Settings — grouped sections in a single column.
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useMeta, useQuasar } from "quasar";
import { Bell, BellOff, Copy, LogOut, Share2, Trash2 } from "@lucide/vue";
import { applyTheme, THEME_KEY, type ThemeChoice } from "@/boot/theme";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";
import { copyText } from "@/utils/clipboard";
import AppAvatar from "@/components/AppAvatar.vue";
import DeleteAccountDialog from "@/components/DeleteAccountDialog.vue";
import { ApiError } from "@/api/http";
import { usePush } from "@/features/push/usePush";

useMeta({ title: "Settings · Psst" });

const $q = useQuasar();
const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();
const messaging = useMessagingStore();

const push = usePush();
onMounted(() => void push.refresh());

const previewsBusy = ref(false);
async function togglePreviews() {
  previewsBusy.value = true;
  try {
    await auth.setPushPreviews(!auth.user?.push_previews);
  } catch {
    $q.notify({
      type: "negative",
      message: "Couldn't save that. Please try again.",
      position: "top"
    });
  } finally {
    previewsBusy.value = false;
  }
}

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
    // Stop pushes for this account on this device before the session goes away.
    await push.detachThisDevice();
    await auth.logout();
  } finally {
    messaging.reset();
    signingOut.value = false;
    await router.replace("/login");
  }
}

const deleteOpen = ref(false);
const deleting = ref(false);
const deleteError = ref("");
const deleteExpected = () => auth.user?.username ?? auth.user?.email ?? "";

async function deleteAccount(typed: string) {
  deleting.value = true;
  deleteError.value = "";
  try {
    await auth.deleteAccount(typed);
    deleteOpen.value = false;
    await push.detachThisDevice();
    messaging.reset();
    await router.replace("/login");
  } catch (e) {
    deleteError.value =
      e instanceof ApiError && e.status === 403
        ? t("settings.deleteAdmin")
        : e instanceof ApiError && e.field("confirmation")
          ? t("settings.deleteMismatch")
          : t("settings.deleteFailed");
  } finally {
    deleting.value = false;
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

      <section class="surface block" aria-labelledby="push-h">
        <h2 id="push-h">{{ $t("settings.notifications") }}</h2>
        <p class="text-secondary-copy">{{
          $t("settings.notificationsBody")
        }}</p>

        <p v-if="push.state.value === 'unsupported'" class="note" role="status">
          {{
            push.isIos
              ? $t("settings.pushUnsupportedIos")
              : $t("settings.pushUnsupported")
          }}
        </p>
        <p
          v-else-if="push.state.value === 'unconfigured'"
          class="note"
          role="status"
        >
          {{ $t("settings.pushUnconfigured") }}
        </p>
        <p
          v-else-if="push.state.value === 'blocked'"
          class="note"
          role="status"
          >{{ $t("settings.pushBlocked") }}</p
        >

        <template
          v-else-if="push.state.value === 'on' || push.state.value === 'off'"
        >
          <p class="status" role="status">
            <component
              :is="push.state.value === 'on' ? Bell : BellOff"
              :size="18"
              aria-hidden="true"
            />
            {{
              push.state.value === "on"
                ? $t("settings.pushOn")
                : $t("settings.pushOff")
            }}
          </p>
          <p v-if="push.failed.value" class="error" role="alert">{{
            $t("settings.pushFailed")
          }}</p>
          <div class="row">
            <q-btn
              v-if="push.state.value === 'off'"
              color="primary"
              unelevated
              no-caps
              :loading="push.busy.value"
              :label="$t('settings.pushEnable')"
              @click="push.enable()"
            />
            <q-btn
              v-else
              outline
              unelevated
              no-caps
              :loading="push.busy.value"
              :label="$t('settings.pushDisable')"
              @click="push.disable()"
            />
          </div>

          <div class="switch-row">
            <div class="switch-text">
              <span id="previews-label" class="switch-title">{{
                $t("settings.previews")
              }}</span>
              <span class="text-secondary-copy">{{
                $t("settings.previewsBody")
              }}</span>
            </div>
            <button
              type="button"
              role="switch"
              class="switch"
              :class="{ on: auth.user?.push_previews }"
              :aria-checked="Boolean(auth.user?.push_previews)"
              aria-labelledby="previews-label"
              :disabled="previewsBusy"
              @click="togglePreviews"
            >
              <span class="knob" />
            </button>
          </div>
        </template>
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

      <section class="surface block danger" aria-labelledby="danger-h">
        <h2 id="danger-h">{{ $t("settings.danger") }}</h2>
        <p class="text-secondary-copy">{{ $t("settings.dangerBody") }}</p>
        <q-btn
          outline
          unelevated
          no-caps
          color="negative"
          @click="deleteOpen = true"
          ><Trash2 :size="18" class="ico" />{{
            $t("settings.deleteOpen")
          }}</q-btn
        >
      </section>

      <DeleteAccountDialog
        v-model="deleteOpen"
        :expected="deleteExpected()"
        :busy="deleting"
        :error="deleteError"
        @confirm="deleteAccount"
      />
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
  gap: var(--s-4);
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
.note {
  padding: var(--s-3) var(--s-4);
  border-radius: var(--r-control);
  background: var(--canvas);
  border: 1px solid var(--border);
  font-size: var(--text-secondary);
  color: var(--text-2);
}
.status {
  display: flex;
  align-items: center;
  gap: var(--s-2);
  font-size: var(--text-secondary);
}
.error {
  color: var(--danger);
  font-size: var(--text-secondary);
}
.switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--s-4);
  padding-top: var(--s-3);
  border-top: 1px solid var(--border);
}
.switch-text {
  display: grid;
  gap: 2px;
}
.switch-title {
  font-weight: 500;
}
.switch {
  position: relative;
  flex: none;
  width: 52px;
  min-height: 44px;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
}
.switch::before {
  content: "";
  position: absolute;
  inset: 10px 0;
  border-radius: 999px;
  background: var(--border-strong);
  transition: background-color var(--dur) var(--ease);
}
.switch.on::before {
  background: var(--accent);
}
.knob {
  position: absolute;
  top: 12px;
  left: 2px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
  transition: transform var(--dur) var(--ease);
}
.switch.on .knob {
  transform: translateX(28px);
}
.switch:disabled {
  opacity: 0.6;
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
