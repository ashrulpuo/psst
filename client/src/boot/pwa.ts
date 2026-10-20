import { defineBoot } from "#q-app";

/**
 * Notification clicks arrive from the service worker as messages; the app's router does the navigating
 * so an open session is reused instead of reloading.
 */
export default defineBoot(({ router }) => {
  if (!("serviceWorker" in navigator)) return;

  navigator.serviceWorker.addEventListener(
    "message",
    (event: MessageEvent<{ type?: string; url?: string }>) => {
      const { type, url } = event.data ?? {};
      // Same-origin paths only; ignore anything else a message might carry.
      if (
        type === "navigate" &&
        typeof url === "string" &&
        url.startsWith("/") &&
        !url.startsWith("//")
      ) {
        void router.push(url);
      }
    }
  );
});
