<script setup lang="ts">
// Front door: what it looks like when someone arrives who has never seen Psst.
import { useMeta } from "quasar";
import { useI18n } from "vue-i18n";
import AppButton from "@/components/AppButton.vue";
import BrandMark from "@/components/BrandMark.vue";
import { STORY_GRADIENTS } from "@/utils/cardColor";

useMeta({ title: "Psst — say it anonymously" });
const { t, te } = useI18n();

/** Demo notes. Illustrative only: the wall is aria-hidden, so nobody has to read it. */
const SAMPLES = [
  { key: "home.sample1", from: "Anonymous #7", time: "2m", tone: 0 },
  { key: "home.sample2", from: "Anonymous #2", time: "18m", tone: 1 },
  { key: "home.sample3", from: "Anonymous #11", time: "1h", tone: 2 }
] as const;

const body = (key: string) => (te(key) ? t(key) : "");
</script>

<template>
  <div class="home">
    <header class="top">
      <BrandMark :size="34" />
      <span class="pill">🔒 {{ $t("home.badge") }}</span>
    </header>

    <div class="body">
      <div class="hero arrive">
        <h1>
          {{ $t("home.line1") }}<br />
          <em>{{ $t("home.line2") }}</em>
        </h1>
        <p>{{ $t("home.sub") }}</p>
      </div>

      <div class="wall" aria-hidden="true">
        <div
          v-for="(sample, index) in SAMPLES"
          :key="sample.key"
          class="note"
          :class="`n${index + 1}`"
          :style="{
            background: STORY_GRADIENTS[sample.tone],
            animationDelay: `${index * 0.55}s`
          }"
        >
          <p>{{ body(sample.key) }}</p>
          <div class="from">
            <span><i class="dot" />{{ sample.from }}</span>
            <span>{{ sample.time }}</span>
          </div>
        </div>
      </div>

      <div class="cta arrive-late">
        <AppButton to="/get-link" block>{{ $t("home.primary") }}</AppButton>
        <AppButton to="/login" variant="ghost" size="sm" block>
          {{ $t("home.secondary") }}
        </AppButton>
      </div>

      <p class="privacy arrive-later">
        🔒 <span>{{ $t("home.privacy") }}</span>
      </p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.home {
  width: min(100%, 430px);
  min-height: 100dvh;
  margin-inline: auto;
  display: flex;
  flex-direction: column;
  padding: 18px 22px 14px;
  background: var(--canvas);
}
.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 0 0;
}
.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 11px;
  border: 2px solid var(--edge);
  border-radius: 999px;
  background: var(--surface);
  box-shadow: 0 3px 0 var(--edge);
  font: 800 0.7rem / 1 var(--font-display);
}
.body {
  flex: 1;
  display: flex;
  flex-direction: column;
  /* Start the pitch under the brand with a little air, and keep the action low. */
  justify-content: flex-start;
  gap: 14px;
  padding-top: 72px;
}
.hero {
  text-align: center;
}
.hero h1 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 2.05rem;
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.02;
}
.hero h1 em {
  font-style: normal;
  color: var(--accent);
  text-shadow: 2px 2px 0 var(--edge);
}
.hero p {
  max-width: 29ch;
  margin: 10px auto 0;
  color: var(--text-2);
  font-size: 0.86rem;
  line-height: 1.5;
}

/* The wall: three notes that drift, like the inbox they come from. */
.wall {
  position: relative;
  height: 344px;
  margin-inline: -8px;
}
.note {
  position: absolute;
  padding: 13px 14px 11px;
  border: 2.5px solid var(--edge);
  border-radius: var(--r-card);
  box-shadow: 0 6px 0 var(--edge);
  color: #fff;
  animation: psst-note-bob 5.6s ease-in-out infinite;
}
.note p {
  margin: 0 0 9px;
  font-size: 0.88rem;
  font-weight: 700;
  line-height: 1.35;
}
.note .from {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-family: var(--font-display);
  font-size: 0.62rem;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.88);
}
.note .dot {
  display: inline-block;
  width: 7px;
  height: 7px;
  margin-right: 5px;
  border: 1.5px solid #fff;
  border-radius: 50%;
  background: var(--lime);
}
.n1 {
  top: 0;
  left: 6px;
  width: 218px;
  --r: -4deg;
}
.n2 {
  top: 96px;
  right: -2px;
  width: 192px;
  --r: 3deg;
}
.n3 {
  top: 204px;
  left: 22px;
  width: 204px;
  --r: 1.5deg;
}

.cta {
  display: grid;
  gap: 9px;
}
.privacy {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin: 6px 0 0;
  color: var(--text-3);
  font-size: 0.72rem;
}

@keyframes psst-note-bob {
  0%,
  100% {
    transform: rotate(var(--r, -3deg)) translateY(0);
  }
  50% {
    transform: rotate(var(--r, -3deg)) translateY(-6px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .note {
    animation: none;
    transform: rotate(var(--r, -3deg));
  }
}
</style>
