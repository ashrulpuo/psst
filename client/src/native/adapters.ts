/**
 * The native capabilities the app uses, behind small interfaces. The real implementations talk to Capacitor
 * plugins (loaded lazily, only on a device). The simulator implementation backs the same interfaces with
 * in-page fakes so the native code paths can be tested in a browser.
 */

export interface TokenStore {
  get(): Promise<string | null>;
  set(token: string): Promise<void>;
  clear(): Promise<void>;
  /** A second slot for the push token this device registered, so sign-out/in can reason about it. */
  getPushToken(): Promise<string | null>;
  setPushToken(token: string | null): Promise<void>;
}

export interface NativeSignIn {
  /** Returns an ID token whose `nonce` claim matches (or is the SHA-256 of) the nonce passed in. */
  signIn(
    provider: "google" | "apple",
    nonce: string
  ): Promise<{ idToken: string; name?: string }>;
  signOut(): Promise<void>;
}

export type PushPermission = "granted" | "denied" | "prompt";

export interface NativePush {
  checkPermission(): Promise<PushPermission>;
  requestPermission(): Promise<PushPermission>;
  getToken(): Promise<string>;
  deleteToken(): Promise<void>;
  /** Called when Firebase rotates the token. */
  onTokenRefresh(handler: (token: string) => void): void;
  /** Called when the user taps a notification (app was in the background or closed). */
  onNotificationTap(handler: (data: Record<string, string>) => void): void;
}

export interface NativeShell {
  /** The first screen is on display: remove the splash. */
  appReady(): Promise<void>;
  setStatusBar(dark: boolean): Promise<void>;
  onDeepLink(handler: (url: string) => void): void;
  /** Android hardware back button. */
  onBackButton(handler: () => void): void;
  exitApp(): Promise<void>;
}

export interface NativeAdapters {
  tokens: TokenStore;
  signIn: NativeSignIn;
  push: NativePush;
  shell: NativeShell;
}

import { config } from "@/utils/config";

let cached: Promise<NativeAdapters> | null = null;

export function nativeAdapters(): Promise<NativeAdapters> {
  cached ??= (async () => {
    if (config.nativeSimulator)
      return (await import("./simulator")).createSimulator();
    // Guarded by a build-time constant: web and PWA builds drop the plugin code entirely.
    if (import.meta.env.QUASAR_CAPACITOR_MODE)
      return (await import("./capacitor/device")).createDeviceAdapters();
    throw new Error(
      "Native features are only available inside the mobile app."
    );
  })();
  return cached;
}
