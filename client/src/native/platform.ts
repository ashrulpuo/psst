import { config } from "@/utils/config";

/**
 * Where the app is running. Capacitor injects `window.Capacitor` before any page script runs, so this needs no
 * import (and costs the web bundle nothing). The simulator flag lets test builds exercise the native paths
 * in a normal browser.
 */
interface CapacitorGlobal {
  isNativePlatform?: () => boolean;
  getPlatform?: () => string;
}

const injected = (globalThis as { Capacitor?: CapacitorGlobal }).Capacitor;

export const isNativeApp: boolean =
  config.nativeSimulator || Boolean(injected?.isNativePlatform?.());

export type NativePlatform = "ios" | "android" | "web";

export const nativePlatform: NativePlatform = (() => {
  if (!isNativeApp) return "web";
  if (config.nativeSimulator) {
    return (
      (new URLSearchParams(globalThis.location?.search).get(
        "sim-platform"
      ) as NativePlatform) || "android"
    );
  }
  const name = injected?.getPlatform?.();
  return name === "ios" ? "ios" : "android";
})();
