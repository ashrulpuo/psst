import { onBeforeUnmount, onMounted } from "vue";

/**
 * Run `fn` every `ms` while the tab is visible, and once immediately when it becomes visible again.
 * Interim stand-in for WebSockets until Reverb is wired up.
 */
export function usePolling(fn: () => void | Promise<void>, ms: number) {
  let timer: ReturnType<typeof setInterval> | undefined;

  const start = () => {
    stop();
    timer = setInterval(() => void fn(), ms);
  };
  const stop = () => {
    if (timer) clearInterval(timer);
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
