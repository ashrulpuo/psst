import { defineBoot } from "#q-app";
import { setUnauthorizedHandler } from "@/api/http";
import { useAuthStore } from "@/stores/auth";
import { useMessagingStore } from "@/stores/messaging";
import { useRealtimeStore } from "@/stores/realtime";
import { watch } from "vue";
import { usePush } from "@/features/push/usePush";

export default defineBoot(({ router, store }) => {
  const auth = useAuthStore(store);
  const messaging = useMessagingStore(store);
  const realtime = useRealtimeStore(store);

  // One socket per signed-in user; closed on sign-out so nothing leaks to the next account.
  watch(
    () => auth.user?.id,
    id => {
      if (id) {
        void realtime.start(id);
        // A device that already has a push subscription now belongs to whoever just signed in.
        void usePush().syncThisDevice();
      } else {
        realtime.stop();
      }
    },
    { immediate: true }
  );

  // A 401 on an authenticated request means the session ended: forget it and go sign in.
  setUnauthorizedHandler(() => {
    if (!auth.isAuthenticated) return;
    auth.clear();
    messaging.reset();
    const next = router.currentRoute.value.fullPath;
    void router.replace({ path: "/login", query: { next } });
  });

  router.beforeEach(async to => {
    await auth.init();

    // The homepage is for people without a link; everyone else opens straight into the app.
    if (to.name === "home" && auth.isAuthenticated) {
      return "/link";
    }
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { path: "/login", query: { next: to.fullPath } };
    }
    // A link kept without an account is "signed in" locally but cannot reply yet:
    // let it through to /login so it can upgrade by signing in.
    if (
      to.meta.guestOnly &&
      auth.isAuthenticated &&
      auth.user?.can_reply !== false
    ) {
      return auth.user?.needs_username ? "/welcome" : "/link";
    }
    // Everyone signed in must claim a handle before using the app.
    if (
      auth.user?.needs_username &&
      to.path !== "/welcome" &&
      to.meta.requiresAuth
    ) {
      return "/welcome";
    }
    return true;
  });
});
