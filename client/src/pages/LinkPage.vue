<script setup lang="ts">
// Home: one share card. The inbox is where messages are read.
import { computed } from "vue";
import { useMeta, useQuasar } from "quasar";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import qrcode from "qrcode-generator";
import { trackEvent } from "@/api/endpoints";
import { useAuthStore } from "@/stores/auth";
import { copyText } from "@/utils/clipboard";
import AppButton from "@/components/AppButton.vue";

useMeta({ title: "Your link · Psst" });

const $q = useQuasar();
const router = useRouter();
const { t } = useI18n();
const auth = useAuthStore();

const url = computed(() => auth.profileLink);
const handle = computed(() => auth.user?.username ?? "");

/** Decorative: the URL is always printed as text, so nothing depends on the code being readable. */
const qr = computed(() => {
  if (!url.value) return "";
  const code = qrcode(0, "M");
  code.addData(url.value);
  code.make();
  return code.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
});

async function copy() {
  const ok = await copyText(url.value);
  if (ok) void trackEvent("link_shared");
  $q.notify({
    type: ok ? "positive" : "warning",
    message: ok ? t("settings.copied") : url.value,
    position: "bottom"
  });
}

async function share() {
  if (typeof navigator.share !== "function") {
    await copy();
    return;
  }
  try {
    await navigator.share({
      title: "Psst",
      text: t("link.prompt"),
      url: url.value
    });
    void trackEvent("link_shared");
  } catch {
    /* dismissed */
  }
}
</script>

<template>
  <main class="link-page">
    <header class="link-head">
      <h1>{{ $t("link.title") }}</h1>
      <span class="live">{{ $t("link.live") }}</span>
    </header>

    <article class="sticker arrive">
      <span class="tag-line">psst.app/</span>
      <h2 class="handle">{{ handle }}</h2>
      <p class="prompt">{{ $t("link.prompt") }}</p>
      <p class="url">{{ url }}</p>
      <div class="qr" v-html="qr" aria-hidden="true" />
    </article>

    <div class="actions arrive-late">
      <AppButton block @click="copy">{{ $t("link.copy") }}</AppButton>
      <AppButton variant="ghost" size="sm" class="share" @click="share">
        {{ $t("link.share") }}
      </AppButton>
      <AppButton
        v-if="auth.user?.can_reply === false"
        variant="ghost"
        block
        @click="router.push({ path: '/login', query: { next: '/link' } })"
      >
        {{ $t("link.signInToChat") }}
      </AppButton>
    </div>

    <ol class="steps arrive-later">
      <li>{{ $t("link.step1") }}</li>
      <li>{{ $t("link.step2") }}</li>
    </ol>

    <div class="story-row">
      <div class="story-thumb">{{ $t("link.prompt") }}</div>
      <div class="story-copy">
        <b>{{ $t("link.storyTitle") }}</b>
        <span>{{ $t("link.storyBody") }}</span>
      </div>
    </div>
  </main>
</template>

<style scoped lang="scss">
.link-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  /* Clear the bottom tab bar: the shell does not pad for it. */
  padding: 14px 22px calc(84px + env(safe-area-inset-bottom));
}
.link-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.link-head h1 {
  font-family: var(--font-display);
  font-size: 1.7rem;
  letter-spacing: -0.04em;
}
.live {
  padding: 5px 10px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--lime);
  color: var(--ink);
  font: 800 0.66rem / 1 var(--font-display);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.sticker {
  position: relative;
  overflow: hidden;
  padding: 22px 116px 26px 20px;
  border: 2.5px solid var(--edge);
  border-radius: 28px;
  background: var(--grad-link);
  box-shadow: 0 9px 0 var(--edge);
  color: #fff;
  transform: rotate(-1.2deg);
}
.sticker::before {
  content: "";
  position: absolute;
  width: 220px;
  height: 220px;
  right: -70px;
  top: -90px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.14);
}
.tag-line {
  font: 800 0.62rem / 1 var(--font-display);
  letter-spacing: 0.18em;
  text-transform: uppercase;
  opacity: 0.85;
}
.handle {
  margin: 6px 0 0;
  font-family: var(--font-display);
  font-size: 2.35rem;
  letter-spacing: -0.045em;
  line-height: 1;
}
.prompt {
  margin: 4px 0 0;
  font-family: var(--font-display);
  font-size: 0.9rem;
  font-weight: 700;
  opacity: 0.92;
}
.url {
  display: inline-block;
  max-width: 100%;
  margin-top: 10px;
  padding: 6px 11px;
  border: 1.5px solid rgba(255, 255, 255, 0.5);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.18);
  font: 700 0.72rem / 1 var(--font-sans);
  overflow-wrap: anywhere;
}
.qr {
  position: absolute;
  right: 16px;
  bottom: 18px;
  width: 86px;
  height: 86px;
  padding: 6px;
  border: 2.5px solid var(--edge);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 4px 0 var(--edge);
}
.qr :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
}
.actions {
  display: grid;
  gap: 10px;
}
.share {
  justify-self: center;
  padding-inline: 22px;
}
.steps {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
  counter-reset: step;
}
.steps li {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 10px 12px;
  border: 2px solid var(--border);
  border-radius: var(--r-control);
  background: var(--surface);
  color: var(--text-2);
  font-size: 0.8rem;
  font-weight: 700;
  counter-increment: step;
}
.story-row {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-top: 2px;
  padding: 10px 12px;
  border: 2px solid var(--border);
  border-radius: var(--r-card);
  background: var(--surface);
}
.story-thumb {
  flex: none;
  width: 46px;
  height: 78px;
  padding: 7px 5px;
  border: 2.5px solid var(--edge);
  border-radius: 11px;
  background: var(--grad-link);
  box-shadow: 0 3px 0 var(--edge);
  color: #fff;
  font: 800 0.42rem / 1.15 var(--font-display);
  text-align: center;
  transform: rotate(-4deg);
}
.story-copy {
  display: grid;
  gap: 2px;
}
.story-copy b {
  font-family: var(--font-display);
  font-size: 0.86rem;
}
.story-copy span {
  color: var(--text-3);
  font-size: 0.7rem;
  font-weight: 700;
}
.steps li::before {
  content: counter(step);
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  flex: none;
  border-radius: 7px;
  background: var(--ink);
  color: var(--canvas);
  font: 800 0.68rem / 1 var(--font-display);
}
</style>
