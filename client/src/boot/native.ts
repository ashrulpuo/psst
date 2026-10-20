import { defineBoot } from "#q-app";
import { Dark } from "quasar";
import { watch } from "vue";
import { nativeAdapters } from "@/native/adapters";
import { safePath } from "@/native/links";
import { isNativeApp } from "@/native/platform";
import { onTokenRotated } from "@/features/push/nativePush";
import { config } from "@/utils/config";
import { useAuthStore } from "@/stores/auth";

/**
 * A universal/app link (https://<public host>/alice) arrives as a full URL. Only our own host is honoured,
 * and only its path is used: the app's router handles it like any in-app navigation.
 */
export function pathFromDeepLink(url: string): string | null {
  try {
    const target = new URL(url);
    const ours = new URL(config.publicUrl);
    if (target.host !== ours.host) return null;
    return safePath(target.pathname + target.search);
  } catch {
    return null;
  }
}

export default defineBoot(async ({ router }) => {
  if (!isNativeApp) return;

  const auth = useAuthStore();
  const { shell, push } = await nativeAdapters();
  const document_ = document.documentElement;
  document_.classList.add("native-app");

  // Dark text on a light app, light text on a dark one: follow the app theme, not the OS.
  const syncStatusBar = () => void shell.setStatusBar(Dark.isActive);
  watch(() => Dark.isActive, syncStatusBar, { immediate: true });

  // Links and notification taps go through the router; a signed-out user is bounced to sign-in by the guard.
  shell.onDeepLink(url => {
    const path = pathFromDeepLink(url);
    if (path) void router.push(path);
  });
  push.onNotificationTap(data => {
    const path = data.url ? safePath(data.url) : null;
    if (path) void router.push(path);
  });
  push.onTokenRefresh(token => void onTokenRotated(token));

  // Android hardware back: step back through the app, and leave it only from the root.
  shell.onBackButton(() => {
    const route = router.currentRoute.value;
    const atRoot = ["/inbox", "/sent", "/settings", "/login", "/"].includes(
      route.path
    );
    if (atRoot) void shell.exitApp();
    else router.back();
  });

  // First screen rendered: drop the splash (it was set to not auto-hide, so there is no white flash).
  // Not awaited: the router only starts navigating after the boot files finish.
  void auth
    .init()
    .then(() => router.isReady())
    .then(() => shell.appReady());
});
