import { ref } from "vue";
import { pushApi } from "@/api/endpoints";
import { isNativeApp } from "@/native/platform";
import {
  detachNative,
  disableNative,
  enableNative,
  refreshNative,
  syncNative
} from "./nativePush";

/**
 * - unsupported:   this browser/context can't do Web Push (or no service worker is registered)
 * - unconfigured:  the server has no VAPID key
 * - blocked:       the user denied permission in the browser
 * - off / on:      permission and subscription state for this device
 */
export type PushState =
  | "checking"
  | "unsupported"
  | "unconfigured"
  | "blocked"
  | "off"
  | "on";

const state = ref<PushState>("checking");
const busy = ref(false);
const failed = ref(false);

const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
const context = { state, busy, failed };

/** VAPID public keys travel as base64url; the Push API wants raw bytes. */
function keyToBytes(key: string): Uint8Array<ArrayBuffer> {
  const padded = key + "=".repeat((4 - (key.length % 4)) % 4);
  const raw = atob(padded.replace(/-/g, "+").replace(/_/g, "/"));
  const bytes = new Uint8Array(new ArrayBuffer(raw.length));
  for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
  return bytes;
}

function supported(): boolean {
  return (
    "serviceWorker" in navigator &&
    "PushManager" in window &&
    "Notification" in window
  );
}

async function registration(): Promise<ServiceWorkerRegistration | undefined> {
  return navigator.serviceWorker.getRegistration();
}

function toRegistration(sub: PushSubscription) {
  const json = sub.toJSON();
  return {
    endpoint: sub.endpoint,
    keys: { p256dh: json.keys?.p256dh ?? "", auth: json.keys?.auth ?? "" }
  };
}

/** Work out where this device stands. Safe to call repeatedly. */
async function refresh(): Promise<void> {
  if (isNativeApp) return refreshNative(context);
  if (!supported() || !(await registration())) {
    state.value = "unsupported";
    return;
  }
  let key: string | null;
  try {
    key = await pushApi.publicKey();
  } catch {
    state.value = "unsupported";
    return;
  }
  if (!key) {
    state.value = "unconfigured";
    return;
  }
  if (Notification.permission === "denied") {
    state.value = "blocked";
    return;
  }
  const sub = await (await registration())?.pushManager.getSubscription();
  state.value = sub && Notification.permission === "granted" ? "on" : "off";
}

/** Must be called from a click: browsers only show the permission prompt after a user gesture. */
async function enable(): Promise<void> {
  if (isNativeApp) return enableNative(context);
  busy.value = true;
  failed.value = false;
  try {
    const key = await pushApi.publicKey();
    const reg = await registration();
    if (!key || !reg) return await refresh();

    const permission = await Notification.requestPermission();
    if (permission !== "granted") {
      state.value = permission === "denied" ? "blocked" : "off";
      return;
    }

    const sub =
      (await reg.pushManager.getSubscription()) ??
      (await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: keyToBytes(key)
      }));
    await pushApi.register(toRegistration(sub));
    state.value = "on";
  } catch {
    failed.value = true;
    await refresh();
  } finally {
    busy.value = false;
  }
}

async function disable(): Promise<void> {
  if (isNativeApp) return disableNative(context);
  busy.value = true;
  failed.value = false;
  try {
    const sub = await (await registration())?.pushManager.getSubscription();
    if (sub) {
      await pushApi.unregister(sub.endpoint);
      await sub.unsubscribe();
    }
    state.value = "off";
  } catch {
    failed.value = true;
  } finally {
    busy.value = false;
  }
}

/**
 * After sign-in on a device that already has a subscription: re-register it for the *current* user (the
 * server moves a device to whoever last registered it) and heal browser-rotated subscriptions.
 */
async function syncThisDevice(): Promise<void> {
  if (isNativeApp) return syncNative();
  try {
    if (!supported() || Notification.permission !== "granted") return;
    const sub = await (await registration())?.pushManager.getSubscription();
    if (sub) await pushApi.register(toRegistration(sub));
  } catch {
    /* best effort: the Settings screen shows the real state */
  }
}

/**
 * Before sign-out: stop pushes for this account on this device. The browser subscription stays, so the
 * next person who signs in here is simply re-registered by syncThisDevice().
 */
async function detachThisDevice(): Promise<void> {
  if (isNativeApp) return detachNative();
  try {
    if (!supported()) return;
    const sub = await (await registration())?.pushManager.getSubscription();
    if (sub) await pushApi.unregister(sub.endpoint);
  } catch {
    /* sign-out must never be blocked by this */
  }
}

export function usePush() {
  return {
    state,
    busy,
    failed,
    isIos,
    refresh,
    enable,
    disable,
    syncThisDevice,
    detachThisDevice
  };
}
