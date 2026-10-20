/*
 * Psst service worker (workboxMode: "InjectManifest").
 * Jobs: precache the app shell, show push notifications, and route notification clicks.
 */

import { clientsClaim } from "workbox-core";
import { NavigationRoute, registerRoute } from "workbox-routing";
import {
  cleanupOutdatedCaches,
  createHandlerBoundToURL,
  precacheAndRoute
} from "workbox-precaching";

declare const self: ServiceWorkerGlobalScope & typeof globalThis;

void self.skipWaiting();
clientsClaim();

// Use with precache injection
precacheAndRoute(self.__WB_MANIFEST);

cleanupOutdatedCaches();

if (import.meta.env.QUASAR_PROD) {
  // Single-page app: every navigation falls back to index.html, except the API and the worker itself.
  registerRoute(
    new NavigationRoute(
      createHandlerBoundToURL(import.meta.env.QUASAR_PWA_FALLBACK_HTML),
      {
        denylist: [
          new RegExp(import.meta.env.QUASAR_PWA_SERVICE_WORKER_REGEX),
          /workbox-(.)*\.js$/,
          /^\/api\//,
          /^\/sanctum\//,
          /^\/app\//
        ]
      }
    )
  );
}

// ---- push ----

/** Shape built by server/app/Push/PushPayload.php. */
interface PushPayload {
  title: string;
  body: string;
  tag: string;
  url: string;
}

function parsePayload(event: PushEvent): PushPayload | null {
  try {
    const data = event.data?.json() as Partial<PushPayload> | undefined;
    if (
      data &&
      typeof data.title === "string" &&
      typeof data.body === "string"
    ) {
      return {
        title: data.title,
        body: data.body,
        tag: typeof data.tag === "string" ? data.tag : "psst",
        url: typeof data.url === "string" ? data.url : "/inbox"
      };
    }
  } catch {
    /* not JSON: fall through to the generic notification */
  }
  return null;
}

/** Same-origin paths only, so a crafted payload can never open another site. */
function safeUrl(raw: string): string {
  try {
    const url = new URL(raw, self.location.origin);
    return url.origin === self.location.origin
      ? url.pathname + url.search
      : "/inbox";
  } catch {
    return "/inbox";
  }
}

async function showNotification(event: PushEvent): Promise<void> {
  const windows = await self.clients.matchAll({
    type: "window",
    includeUncontrolled: true
  });
  // The reader is looking at the app: realtime already shows the message, so stay quiet.
  if (windows.some(w => w.visibilityState === "visible" && w.focused)) return;

  const payload = parsePayload(event) ?? {
    title: "Psst",
    body: "You have a new message",
    tag: "psst",
    url: "/inbox"
  };

  await self.registration.showNotification(payload.title, {
    body: payload.body,
    // One notification per conversation: a newer message replaces the older one.
    tag: payload.tag,
    renotify: true,
    icon: "/icons/icon-192x192.png",
    badge: "/icons/badge-96x96.png",
    data: { url: safeUrl(payload.url) }
  } as NotificationOptions);
}

self.addEventListener("push", event => {
  event.waitUntil(showNotification(event));
});

self.addEventListener("notificationclick", event => {
  event.notification.close();
  const target = safeUrl(
    (event.notification.data as { url?: string } | null)?.url ?? "/inbox"
  );

  event.waitUntil(
    (async () => {
      const windows = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true
      });
      const existing = windows.find(
        w => new URL(w.url).origin === self.location.origin
      );
      if (existing) {
        await existing.focus();
        // The app's router handles the navigation (see src/boot/pwa.ts).
        existing.postMessage({ type: "navigate", url: target });
        return;
      }
      await self.clients.openWindow(target);
    })()
  );
});
