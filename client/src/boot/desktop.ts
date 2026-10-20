import { defineBoot } from "#q-app";
import { watch } from "vue";
import { desktop as bridge } from "@/native/desktop";
import { safePath } from "@/native/links";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";

/** macOS/Windows/Linux (Electron): dock/taskbar badge, native notifications and notification-click routing. */
export default defineBoot(({ router }) => {
  if (!bridge) return;
  const desktop = bridge;
  const auth = useAuthStore();
  const messaging = useMessagingStore();
  document.documentElement.classList.add("desktop-app");

  desktop.onNavigate(path => {
    const safe = safePath(path);
    if (safe) void router.push(safe);
  });

  watch(
    () => (auth.user ? messaging.inboxUnread + messaging.sentUnread : 0),
    count => desktop.setBadge(count),
    { immediate: true }
  );

  // Web Push needs a browser push service, which Electron doesn't have: the live socket drives notifications instead.
  messaging.$onAction(({ name, args, after }) => {
    if (name !== "applyMessagePosted") return;
    const payload = args[0];
    after(() => {
      if (payload.message.mine || document.hasFocus()) return;
      const previews = auth.user?.push_previews === true;
      desktop.notify({
        title: payload.box === "inbox" ? "New anonymous message" : "New reply",
        body: previews ? payload.message.body : "Open Psst to read it.",
        path: `/${payload.box}/${payload.thread.id}`
      });
    });
  });
});
