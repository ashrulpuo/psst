import type { Ref } from "vue";
import { pushApi } from "@/api/endpoints";
import { nativeAdapters } from "@/native/adapters";
import { nativePlatform } from "@/native/platform";
import type { PushState } from "./usePush";

/** The reactive state usePush owns; the native flow drives the same state machine as the web one. */
export interface PushContext {
  state: Ref<PushState>;
  busy: Ref<boolean>;
  failed: Ref<boolean>;
}

const platform = () => (nativePlatform === "ios" ? "ios" : "android");

export async function refreshNative({ state }: PushContext): Promise<void> {
  const { push, tokens } = await nativeAdapters();
  let fcm: boolean;
  try {
    fcm = (await pushApi.config()).fcm;
  } catch {
    state.value = "unsupported";
    return;
  }
  if (!fcm) {
    state.value = "unconfigured";
    return;
  }
  const permission = await push
    .checkPermission()
    .catch(() => "prompt" as const);
  if (permission === "denied") {
    state.value = "blocked";
    return;
  }
  state.value =
    permission === "granted" && (await tokens.getPushToken()) ? "on" : "off";
}

/** From a tap: the OS shows its permission sheet only in response to a user gesture. */
export async function enableNative(ctx: PushContext): Promise<void> {
  const { push, tokens } = await nativeAdapters();
  ctx.busy.value = true;
  ctx.failed.value = false;
  try {
    const permission = await push.requestPermission();
    if (permission !== "granted") {
      ctx.state.value = permission === "denied" ? "blocked" : "off";
      return;
    }
    const token = await push.getToken();
    await pushApi.registerDevice(token, platform());
    await tokens.setPushToken(token);
    ctx.state.value = "on";
  } catch {
    // Most often Firebase is not configured in this build (no google-services.json / GoogleService-Info.plist).
    ctx.failed.value = true;
    await refreshNative(ctx);
  } finally {
    ctx.busy.value = false;
  }
}

export async function disableNative(ctx: PushContext): Promise<void> {
  const { push, tokens } = await nativeAdapters();
  ctx.busy.value = true;
  ctx.failed.value = false;
  try {
    const token = await tokens.getPushToken();
    if (token) await pushApi.unregisterDevice(token);
    await push.deleteToken().catch(() => undefined);
    await tokens.setPushToken(null);
    ctx.state.value = "off";
  } catch {
    ctx.failed.value = true;
  } finally {
    ctx.busy.value = false;
  }
}

/**
 * After sign-in on a device that already has push set up: re-register for the account that just signed in
 * (the server moves a device to whoever registered it last) and keep the stored token current.
 */
export async function syncNative(): Promise<void> {
  try {
    const { push, tokens } = await nativeAdapters();
    if (
      (await push.checkPermission()) !== "granted" ||
      !(await tokens.getPushToken())
    )
      return;
    const token = await push.getToken();
    await pushApi.registerDevice(token, platform());
    await tokens.setPushToken(token);
  } catch {
    /* best effort: Settings shows the real state */
  }
}

/** Before sign-out: stop pushes for this account on this device. The token stays for the next sign-in. */
export async function detachNative(): Promise<void> {
  try {
    const { tokens } = await nativeAdapters();
    const token = await tokens.getPushToken();
    if (token) await pushApi.unregisterDevice(token);
  } catch {
    /* sign-out must never be blocked by this */
  }
}

/** Firebase rotated the token while the app was running: register the new one for the current account. */
export async function onTokenRotated(token: string): Promise<void> {
  try {
    const { tokens } = await nativeAdapters();
    if (!(await tokens.getPushToken())) return; // push is off on this device
    await pushApi.registerDevice(token, platform());
    await tokens.setPushToken(token);
  } catch {
    /* the next sync on sign-in corrects it */
  }
}
