import { ref } from "vue";

/**
 * One-axis swipe. Vertical intent wins immediately so scrolling never fights the card.
 * The gesture is an accelerator: the same action always has a focusable button.
 */
export function useSwipe(opts: { threshold?: number; max?: number } = {}) {
  const threshold = opts.threshold ?? 72;
  const max = opts.max ?? 110;

  const offset = ref(0);
  const open = ref(false);
  let startX: number | null = null;
  let startY = 0;
  let vertical = false;

  function close() {
    open.value = false;
    offset.value = 0;
  }

  function pointerdown(event: PointerEvent) {
    if (open.value) {
      close();
      return;
    }
    startX = event.clientX;
    startY = event.clientY;
    vertical = false;
    (event.currentTarget as HTMLElement | null)?.setPointerCapture?.(
      event.pointerId
    );
  }

  function pointermove(event: PointerEvent) {
    if (startX === null) return;
    const dx = event.clientX - startX;
    const dy = event.clientY - startY;
    if (!vertical && Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 8) {
      vertical = true;
      offset.value = 0;
      return;
    }
    if (vertical) return;
    offset.value = Math.max(-max, Math.min(0, dx));
  }

  function settle() {
    if (startX === null) return;
    startX = null;
    open.value = offset.value < -threshold;
    offset.value = open.value ? -max : 0;
  }

  return {
    offset,
    open,
    close,
    handlers: {
      pointerdown,
      pointermove,
      pointerup: settle,
      pointercancel: settle
    }
  };
}
