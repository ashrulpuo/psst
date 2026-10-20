<script setup lang="ts">
// One series of daily counts. Single series => no legend box (the title names it). The mark colour is the
// validated --chart token; text always wears text tokens. A table view exposes every value without hovering.
import { computed, onBeforeUnmount, onMounted, ref, useId, useTemplateRef } from "vue";
import { Table2, ChartColumn } from "@lucide/vue";
import type { DayCount } from "@/lib/types";
import { formatCompact, formatNumber } from "@/lib/format";
import Button from "../ui/Button.vue";

const props = defineProps<{ title: string; unit: string; data: DayCount[] }>();

const MARGIN = { top: 22, right: 8, bottom: 26, left: 40 };
const HEIGHT = 220;
const MAX_BAR = 24;
const GAP = 2; // surface gap between neighbours, one consistent width

const host = useTemplateRef<HTMLElement>("host");
const width = ref(640);
const view = ref<"chart" | "table">("chart");
const active = ref<number | null>(null);
const focusIndex = ref(props.data.length - 1);
const titleId = useId();

let observer: ResizeObserver | undefined;
onMounted(() => {
  if (!host.value) return;
  width.value = host.value.clientWidth || 640;
  observer = new ResizeObserver(([entry]) => {
    if (entry) width.value = Math.max(280, Math.floor(entry.contentRect.width));
  });
  observer.observe(host.value);
});
onBeforeUnmount(() => observer?.disconnect());

const plotW = computed(() => width.value - MARGIN.left - MARGIN.right);
const plotH = HEIGHT - MARGIN.top - MARGIN.bottom;
const slot = computed(() => plotW.value / Math.max(props.data.length, 1));
const barW = computed(() => Math.max(2, Math.min(MAX_BAR, slot.value - GAP - 2)));

/** Round the axis maximum up to 1, 2 or 5 times a power of ten so ticks read as clean numbers. */
const axisMax = computed(() => {
  const max = Math.max(...props.data.map((d) => d.count), 0);
  if (max <= 4) return 4;
  const pow = 10 ** Math.floor(Math.log10(max));
  const step = [1, 2, 5, 10].map((m) => m * pow).find((s) => s * 4 >= max) ?? max;
  return step * 4;
});
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => (axisMax.value / 4) * i));
const y = (value: number) => MARGIN.top + plotH - (value / axisMax.value) * plotH;

const bars = computed(() =>
  props.data.map((d, i) => {
    const h = (d.count / axisMax.value) * plotH;
    return {
      ...d,
      i,
      x: MARGIN.left + slot.value * i + (slot.value - barW.value) / 2,
      h,
      top: MARGIN.top + plotH - h,
    };
  }),
);

/** Label the extreme only: a number on every bar would be noise. */
const peak = computed(() => {
  const best = bars.value.reduce((a, b) => (b.count > a.count ? b : a), bars.value[0]!);
  return best && best.count > 0 ? best : null;
});

const dayFmt = new Intl.DateTimeFormat(undefined, {
  month: "short",
  day: "numeric",
  timeZone: "UTC",
});
const longFmt = new Intl.DateTimeFormat(undefined, {
  weekday: "short",
  month: "short",
  day: "numeric",
  timeZone: "UTC",
});
const parse = (d: string) => new Date(`${d}T00:00:00Z`);
/** Show about one x-axis label per 90px so they never collide. */
const every = computed(() =>
  Math.max(1, Math.ceil(props.data.length / Math.max(2, Math.floor(plotW.value / 90)))),
);
const showTick = (i: number) => i % every.value === 0;

const total = computed(() => props.data.reduce((s, d) => s + d.count, 0));
const summary = computed(
  () =>
    `${props.title}: ${formatNumber(total.value)} ${props.unit} in the last ${props.data.length} days.`,
);

const tip = computed(() => {
  const i = active.value;
  if (i === null) return null;
  const b = bars.value[i];
  if (!b) return null;
  return {
    text: `${formatNumber(b.count)} ${props.unit}`,
    date: longFmt.format(parse(b.date)),
    left: b.x + barW.value / 2,
    top: b.top,
  };
});

function onKey(event: KeyboardEvent) {
  const last = props.data.length - 1;
  const moves: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1, Home: -last, End: last };
  const delta = moves[event.key];
  if (delta === undefined) return;
  event.preventDefault();
  focusIndex.value =
    event.key === "Home"
      ? 0
      : event.key === "End"
        ? last
        : Math.min(last, Math.max(0, focusIndex.value + delta));
  active.value = focusIndex.value;
}
</script>

<template>
  <section class="surface grid gap-3 p-5" :aria-labelledby="titleId">
    <header class="flex items-start justify-between gap-3">
      <div class="grid gap-0.5">
        <h2 :id="titleId">{{ title }}</h2>
        <p class="text-sm text-fg-2">
          {{ formatNumber(total) }} {{ unit }} · last {{ data.length }} days (UTC)
        </p>
      </div>
      <Button
        variant="ghost"
        size="sm"
        :aria-pressed="view === 'table'"
        @click="view = view === 'chart' ? 'table' : 'chart'"
      >
        <component
          :is="view === 'chart' ? Table2 : ChartColumn"
          class="size-4"
          aria-hidden="true"
        />
        {{ view === "chart" ? "Show as table" : "Show as chart" }}
      </Button>
    </header>

    <div ref="host" class="relative" @pointerleave="active = null">
      <svg
        v-show="view === 'chart'"
        :viewBox="`0 0 ${width} ${HEIGHT}`"
        :width="width"
        :height="HEIGHT"
        class="block max-w-full overflow-visible"
        role="group"
        :aria-label="summary"
      >
        <!-- recessive hairline grid; the axis carries the values we don't label -->
        <g>
          <line
            v-for="t in ticks"
            :key="t"
            :x1="MARGIN.left"
            :x2="width - MARGIN.right"
            :y1="y(t)"
            :y2="y(t)"
            stroke="var(--line)"
            stroke-width="1"
          />
          <text
            v-for="t in ticks"
            :key="`l${t}`"
            :x="MARGIN.left - 8"
            :y="y(t)"
            text-anchor="end"
            dominant-baseline="middle"
            class="fill-fg-3 tabular"
            font-size="12"
          >
            {{ formatCompact(t) }}
          </text>
        </g>

        <g>
          <text
            v-for="b in bars.filter((_, i) => showTick(i))"
            :key="`x${b.i}`"
            :x="b.x + barW / 2"
            :y="HEIGHT - 6"
            text-anchor="middle"
            class="fill-fg-3"
            font-size="12"
          >
            {{ dayFmt.format(parse(b.date)) }}
          </text>
        </g>

        <!-- bars: 4px rounded data end, square at the baseline -->
        <g>
          <path
            v-for="b in bars"
            :key="b.date"
            :d="
              b.h <= 0.5
                ? ''
                : `M${b.x},${MARGIN.top + plotH} V${b.top + Math.min(4, b.h)} Q${b.x},${b.top} ${b.x + Math.min(4, barW / 2)},${b.top} H${b.x + barW - Math.min(4, barW / 2)} Q${b.x + barW},${b.top} ${b.x + barW},${b.top + Math.min(4, b.h)} V${MARGIN.top + plotH} Z`
            "
            fill="var(--chart)"
            :opacity="active === null || active === b.i ? 1 : 0.55"
          />
        </g>

        <!-- the extreme is labelled directly -->
        <text
          v-if="peak"
          :x="peak.x + barW / 2"
          :y="peak.top - 6"
          text-anchor="middle"
          class="fill-fg tabular"
          font-size="12"
          font-weight="600"
        >
          {{ formatNumber(peak.count) }}
        </text>

        <!-- hit targets: the whole slot, taller than the mark; one roving tab stop, arrows move between days -->
        <g @keydown="onKey">
          <rect
            v-for="b in bars"
            :key="`h${b.i}`"
            :x="MARGIN.left + slot * b.i"
            :y="MARGIN.top"
            :width="slot"
            :height="plotH"
            fill="transparent"
            :tabindex="focusIndex === b.i ? 0 : -1"
            role="img"
            :aria-label="`${longFmt.format(parse(b.date))}: ${formatNumber(b.count)} ${unit}`"
            @pointerenter="active = b.i"
            @pointermove="active = b.i"
            @focus="((active = b.i), (focusIndex = b.i))"
            @blur="active = null"
          />
        </g>
      </svg>

      <div
        v-if="view === 'chart' && tip"
        class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg border border-line bg-surface px-3 py-2 shadow-md"
        :style="{
          left: `${Math.min(Math.max(tip.left, 64), width - 64)}px`,
          top: `${tip.top - 8}px`,
        }"
        role="status"
      >
        <p class="text-sm font-semibold tabular whitespace-nowrap">{{ tip.text }}</p>
        <p class="text-xs text-fg-3 whitespace-nowrap">{{ tip.date }}</p>
      </div>

      <div
        v-if="view === 'table'"
        class="max-h-72 overflow-auto rounded-control border border-line"
      >
        <table class="w-full text-sm">
          <caption class="sr-only-keep">
            {{
              summary
            }}
          </caption>
          <thead class="sticky top-0 bg-surface-2 text-left text-fg-2">
            <tr>
              <th class="px-3 py-2 font-medium" scope="col">Day (UTC)</th>
              <th class="px-3 py-2 text-right font-medium" scope="col">{{ unit }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in [...data].reverse()" :key="d.date" class="border-t border-line">
              <td class="px-3 py-2">{{ longFmt.format(parse(d.date)) }}</td>
              <td class="px-3 py-2 text-right tabular">{{ formatNumber(d.count) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
