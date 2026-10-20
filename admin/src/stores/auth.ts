import { defineStore } from "pinia";
import { computed, ref } from "vue";
import { api } from "@/lib/api";
import type { Me, TwoFactorStatus } from "@/lib/types";

/** Where the signed-in person stands on the way into the admin area. */
export type Gate = "guest" | "not-admin" | "setup-2fa" | "verify-2fa" | "ready";

export const useAuthStore = defineStore("auth", () => {
  const me = ref<Me | null>(null);
  const twoFactor = ref<TwoFactorStatus | null>(null);
  const ready = ref(false);

  const gate = computed<Gate>(() => {
    if (!me.value) return "guest";
    if (me.value.role !== "admin") return "not-admin";
    if (!twoFactor.value?.enrolled) return "setup-2fa";
    return twoFactor.value.verified ? "ready" : "verify-2fa";
  });

  /**
   * Re-read who is signed in and where they stand on two-factor. Both values are assigned together: setting
   * `me` first would show an enrolled admin as "not enrolled" for a moment and bounce them to the setup screen.
   */
  async function refresh(): Promise<void> {
    let nextMe: Me | null = null;
    let nextTwoFactor: TwoFactorStatus | null = null;
    try {
      nextMe = (await api.get<{ data: Me }>("/me", undefined, true)).data;
      if (nextMe.role === "admin") {
        nextTwoFactor = (await api.get<{ data: TwoFactorStatus }>("/admin/2fa", undefined, true))
          .data;
      }
    } catch {
      nextMe = null;
      nextTwoFactor = null;
    }
    me.value = nextMe;
    twoFactor.value = nextTwoFactor;
  }

  let initializing: Promise<void> | null = null;
  function init(): Promise<void> {
    initializing ??= refresh().finally(() => {
      ready.value = true;
    });
    return initializing;
  }

  async function devLogin(email: string) {
    await api.post("/dev/login", { email });
    await refresh();
  }

  async function googleLogin(idToken: string, nonce: string) {
    await api.post("/auth/google", { id_token: idToken, nonce });
    await refresh();
  }

  async function verify(code: string) {
    await api.post("/admin/2fa/verify", { code });
    await refresh();
  }

  async function logout() {
    try {
      await api.post("/auth/logout");
    } finally {
      me.value = null;
      twoFactor.value = null;
    }
  }

  return { me, twoFactor, ready, gate, init, refresh, devLogin, googleLogin, verify, logout };
});
