<script setup lang="ts">
// The lockup: sticker mark + live wordmark. The word stays text so it always matches the app's type.
withDefaults(defineProps<{ size?: number; word?: boolean }>(), {
  size: 32,
  word: true
});
</script>

<template>
  <span class="brand">
    <img
      class="mark"
      src="icons/mark.svg"
      alt=""
      :style="{ width: `${size}px`, height: `${size}px` }"
    />
    <span v-if="word" class="word">psst<sup aria-hidden="true">🤫</sup></span>
  </span>
</template>

<style scoped lang="scss">
.brand {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.mark {
  display: block;
  flex: none;
  filter: drop-shadow(0 2px 0 rgba(27, 16, 36, 0.18));
}
.word {
  position: relative;
  font-family: var(--font-display);
  font-size: 1.45rem;
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1;
  color: var(--text);
}
.word sup {
  position: absolute;
  top: -8px;
  right: -15px;
  font-size: 0.72rem;
  transform: rotate(12deg);
}
</style>
