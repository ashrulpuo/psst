import type { NativeAdapters, PushPermission } from "./adapters";

/**
 * In-page stand-ins for the native plugins, used by test builds (QCLI_NATIVE_SIMULATOR=true) so the native code
 * paths (bearer auth, token storage, push registration, deep links, back button) run in a normal browser.
 * Tests drive and inspect it through `window.__psstNative`. It is never part of a device build.
 */
export interface SimulatorControls {
  /** Everything the app asked the "device" to do, in order. */
  calls: string[];
  permission: PushPermission;
  /** Outcome of the next native sign-in: an ID token to return, or an error message to reject with. */
  nextSignIn: { idToken: string; name?: string } | { error: string };
  pushToken: string;
  emitDeepLink(url: string): void;
  tapNotification(data: Record<string, string>): void;
  emitTokenRefresh(token: string): void;
  pressBack(): void;
}

declare global {
  interface Window {
    __psstNative?: SimulatorControls;
  }
}

// localStorage stands in for the Keychain/Keystore so a page reload behaves like an app restart.
const store = {
  get: (key: string) => localStorage.getItem(`sim.${key}`),
  set: (key: string, value: string) =>
    localStorage.setItem(`sim.${key}`, value),
  remove: (key: string) => localStorage.removeItem(`sim.${key}`)
};

export function createSimulator(): NativeAdapters {
  const deepLinkHandlers: ((url: string) => void)[] = [];
  const tapHandlers: ((data: Record<string, string>) => void)[] = [];
  const refreshHandlers: ((token: string) => void)[] = [];
  const backHandlers: (() => void)[] = [];

  const controls: SimulatorControls = {
    calls: [],
    permission: "prompt",
    nextSignIn: { idToken: "simulated.unverified.token" },
    pushToken: "sim-fcm-token-0123456789:abcdef_ghijkl",
    emitDeepLink: url => deepLinkHandlers.forEach(h => h(url)),
    tapNotification: data => tapHandlers.forEach(h => h(data)),
    emitTokenRefresh: token => {
      controls.pushToken = token;
      refreshHandlers.forEach(h => h(token));
    },
    pressBack: () => backHandlers.forEach(h => h())
  };
  window.__psstNative = controls;
  const log = (call: string) => controls.calls.push(call);

  return {
    tokens: {
      get: async () => store.get("api-token"),
      set: async token => store.set("api-token", token),
      clear: async () => store.remove("api-token"),
      getPushToken: async () => store.get("push-token"),
      setPushToken: async token =>
        token ? store.set("push-token", token) : store.remove("push-token")
    },
    signIn: {
      async signIn(provider) {
        log(`signIn:${provider}`);
        if ("error" in controls.nextSignIn)
          throw new Error(controls.nextSignIn.error);
        return controls.nextSignIn;
      },
      signOut: async () => void log("signOut")
    },
    push: {
      checkPermission: async () => controls.permission,
      requestPermission: async () => {
        log("requestPermission");
        // A real prompt resolves to granted or denied; tests preselect which by setting `permission` first.
        if (controls.permission === "prompt") controls.permission = "granted";
        return controls.permission;
      },
      getToken: async () => controls.pushToken,
      deleteToken: async () => void log("deleteToken"),
      onTokenRefresh: handler => void refreshHandlers.push(handler),
      onNotificationTap: handler => void tapHandlers.push(handler)
    },
    shell: {
      appReady: async () => void log("splash:hide"),
      setStatusBar: async dark =>
        void log(`statusBar:${dark ? "light-text" : "dark-text"}`),
      onDeepLink: handler => void deepLinkHandlers.push(handler),
      onBackButton: handler => void backHandlers.push(handler),
      exitApp: async () => void log("exitApp")
    }
  };
}
