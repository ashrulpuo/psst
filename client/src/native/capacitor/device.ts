import { App } from "@capacitor/app";
import { Capacitor } from "@capacitor/core";
import { Keyboard } from "@capacitor/keyboard";
import { SplashScreen } from "@capacitor/splash-screen";
import { StatusBar, Style } from "@capacitor/status-bar";
import { FirebaseMessaging } from "@capacitor-firebase/messaging";
import { SecureStorage } from "@aparajita/capacitor-secure-storage";
import { SocialLogin } from "@capgo/capacitor-social-login";
import { config } from "@/utils/config";
import type { NativeAdapters, PushPermission } from "../adapters";

const TOKEN_KEY = "psst.api-token";
const PUSH_TOKEN_KEY = "psst.push-token";
const CHANNEL_ID = "messages";

const isIos = Capacitor.getPlatform() === "ios";

/** Real device implementations: Keychain/Keystore, native Google/Apple sign-in, FCM, splash, status bar. */
export async function createDeviceAdapters(): Promise<NativeAdapters> {
  // Items in the iOS Keychain survive an app reinstall by default; the API token should not.
  await SecureStorage.setSynchronize(false).catch(() => undefined);
  await Keyboard.setAccessoryBarVisible({ isVisible: true }).catch(
    () => undefined
  ); // no-op on Android

  let socialReady: Promise<void> | null = null;
  const ensureSocial = () =>
    (socialReady ??= SocialLogin.initialize({
      google: {
        webClientId: config.googleClientId,
        ...(isIos
          ? {
              iOSClientId: config.googleIosClientId,
              iOSServerClientId: config.googleClientId
            }
          : {})
      },
      // Sign in with Apple is native on iOS only; on Android it would need a web redirect, so it is not offered there.
      ...(isIos ? { apple: {} } : {})
    }));

  const permission = (state: string): PushPermission =>
    state === "granted" ? "granted" : state === "denied" ? "denied" : "prompt";

  return {
    tokens: {
      get: async () =>
        ((await SecureStorage.get(TOKEN_KEY).catch(() => null)) as
          | string
          | null) ?? null,
      set: async token => await SecureStorage.set(TOKEN_KEY, token),
      clear: async () =>
        void (await SecureStorage.remove(TOKEN_KEY).catch(() => false)),
      getPushToken: async () =>
        ((await SecureStorage.get(PUSH_TOKEN_KEY).catch(() => null)) as
          | string
          | null) ?? null,
      setPushToken: async token => {
        if (token) await SecureStorage.set(PUSH_TOKEN_KEY, token);
        else await SecureStorage.remove(PUSH_TOKEN_KEY).catch(() => false);
      }
    },

    signIn: {
      async signIn(provider, nonce) {
        await ensureSocial();
        const login = await SocialLogin.login(
          provider === "google"
            ? {
                provider: "google",
                options: { scopes: ["email", "profile"], nonce }
              }
            : {
                provider: "apple",
                options: { scopes: ["email", "name"], nonce }
              }
        );
        const result = login.result as {
          idToken?: string | null;
          profile?: { givenName?: string | null; familyName?: string | null };
        };
        if (!result.idToken)
          throw new Error("The provider did not return an ID token.");
        // Apple only shares the name on the very first authorization.
        const name =
          [result.profile?.givenName, result.profile?.familyName]
            .filter(Boolean)
            .join(" ") || undefined;
        return { idToken: result.idToken, ...(name ? { name } : {}) };
      },
      signOut: async () => {
        await ensureSocial().catch(() => undefined);
        await SocialLogin.logout({ provider: "google" }).catch(() => undefined);
      }
    },

    push: {
      checkPermission: async () =>
        permission((await FirebaseMessaging.checkPermissions()).receive),
      requestPermission: async () => {
        const result = permission(
          (await FirebaseMessaging.requestPermissions()).receive
        );
        if (result === "granted" && !isIos) {
          // Android shows notifications through a channel; FCM messages name this one (see FirebaseFcmSender).
          await FirebaseMessaging.createChannel({
            id: CHANNEL_ID,
            name: "Messages",
            importance: 4,
            visibility: 0,
            vibration: true
          }).catch(() => undefined);
        }
        return result;
      },
      getToken: async () => (await FirebaseMessaging.getToken()).token,
      deleteToken: () => FirebaseMessaging.deleteToken(),
      onTokenRefresh: handler =>
        void FirebaseMessaging.addListener("tokenReceived", ({ token }) =>
          handler(token)
        ),
      onNotificationTap: handler =>
        void FirebaseMessaging.addListener(
          "notificationActionPerformed",
          ({ notification }) => {
            const data = (notification.data ?? {}) as Record<string, unknown>;
            handler(
              Object.fromEntries(
                Object.entries(data).map(([k, v]) => [k, String(v)])
              )
            );
          }
        )
    },

    shell: {
      appReady: () => SplashScreen.hide({ fadeOutDuration: 150 }),
      setStatusBar: async dark => {
        // "Dark" style = light text, for dark backgrounds.
        await StatusBar.setStyle({
          style: dark ? Style.Dark : Style.Light
        }).catch(() => undefined);
        if (!isIos)
          await StatusBar.setBackgroundColor({
            color: dark ? "#0e0e12" : "#f6f6f9"
          }).catch(() => undefined);
      },
      onDeepLink: handler =>
        void App.addListener("appUrlOpen", ({ url }) => handler(url)),
      onBackButton: handler =>
        void App.addListener("backButton", () => handler()),
      exitApp: () => App.exitApp()
    }
  };
}
