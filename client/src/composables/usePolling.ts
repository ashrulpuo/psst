import { onBeforeUnmount, onMounted } from "vue";

/**
 * Run `fn` repeatedly while the tab is visible, and once immediately when it becomes visible again.
 * `ms` may be a function so the cadence can change at runtime (slow down while realtime is connected).
 * Acts as the fallback and safety net next to WebSockets.
 */
export function usePolling(
  fn: () => void | Promise<void>,
  ms: number | (() => number)
) {
  let timer: ReturnType<typeof setTimeout> | undefined;
  let running = false;

  const delay = () => (typeof ms === "function" ? ms() : ms);

  const schedule = () => {
    timer = setTimeout(async () => {
      try {
        await fn();
      } finally {
        if (running) schedule();
      }
    }, delay());
  };
  const start = () => {
    stop();
    running = true;
    schedule();
  };
  const stop = () => {
    running = false;
    if (timer) clearTimeout(timer);
    timer = undefined;
  };
  const onVisibility = () => {
    if (document.hidden) {
      stop();
    } else {
      void fn();
      start();
    }
  };

  onMounted(() => {
    if (!document.hidden) start();
    document.addEventListener("visibilitychange", onVisibility);
  });
  onBeforeUnmount(() => {
    stop();
    document.removeEventListener("visibilitychange", onVisibility);
  });
}
