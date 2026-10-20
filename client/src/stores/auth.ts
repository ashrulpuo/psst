import { defineStore } from "pinia";
import { computed, ref } from "vue";
import { authApi, guestApi, type TokenLogin } from "@/api/endpoints";
import { setAuthToken } from "@/api/http";
import type { Me, Provider } from "@/api/types";
import { nativeAdapters } from "@/native/adapters";
import { isNativeApp, nativePlatform } from "@/native/platform";
import { config } from "@/utils/config";
import { clearGuestToken, guestToken } from "@/utils/guest";
import { randomNonce } from "@/utils/format";

export const useAuthStore = defineStore("auth", () => {
  const user = ref<Me | null>(null);
  /** True once the first /me check has finished, so guards never flash the wrong screen. */
  const ready = ref(false);

  const isAuthenticated = computed(() => user.value !== null);

  /**
   * The shareable public link. A native app's own origin is capacitor://localhost, which is useless to
   * anyone else, so it uses the configured public URL.
   */
  const profileLink = computed(() => {
    if (!user.value?.username) return "";
    const origin = isNativeApp ? config.publicUrl : window.location.origin;
    return `${origin}/${user.value.username}`;
  });

  let initializing: Promise<void> | null = null;

  /** Native: pick up the bearer token saved in the Keychain/Keystore before asking who is signed in. */
  async function restoreNativeSession(): Promise<void> {
    if (!isNativeApp) return;
    const token = await (await nativeAdapters()).tokens.get();
    setAuthToken(token);
  }

  /**
   * Anonymous threads this browser started become the user's own once they sign in, so receivers can reply.
   * Best effort: if it fails the token is kept and the next sign-in retries.
   */
  async function claimGuestThreads(): Promise<void> {
    const token = guestToken();
    if (!token || !user.value) return;
    try {
      await guestApi.claim(token);
      clearGuestToken();
    } catch {
      /* retried on the next sign-in or app start */
    }
  }

  function init(): Promise<void> {
    initializing ??= restoreNativeSession()
      .then(() => authApi.me())
      .then(async me => {
        user.value = me;
        await claimGuestThreads();
      })
      .catch(() => {
        user.value = null;
      })
      .finally(() => {
        ready.value = true;
      });
    return initializing;
  }

  async function adoptToken(login: TokenLogin): Promise<void> {
    await (await nativeAdapters()).tokens.set(login.token);
    setAuthToken(login.token);
    user.value = login.user;
    await claimGuestThreads();
  }

  /** Browser sign-in: the provider's ID token is exchanged for a cookie session. */
  async function signIn(
    provider: Provider,
    idToken: string,
    nonce: string,
    name?: string
  ) {
    user.value = await authApi.signIn(provider, idToken, nonce, name);
    await claimGuestThreads();
  }

  /** Device sign-in: the native Google/Apple sheet, then the same verification, answered with a bearer token. */
  async function signInNative(provider: Provider) {
    const native = await nativeAdapters();
    const nonce = randomNonce();
    const { idToken, name } = await native.signIn.signIn(provider, nonce);
    await adoptToken(
      await authApi.signInToken(
        provider,
        idToken,
        nonce,
        `Psst ${nativePlatform}`,
        name
      )
    );
  }

  async function devLogin(email: string) {
    const result = await authApi.devLogin(email);
    if ("token" in result) await adoptToken(result);
    else user.value = result.data;
    await claimGuestThreads();
  }

  async function setUsername(username: string) {
    user.value = await authApi.setUsername(username);
  }

  async function setPushPreviews(enabled: boolean) {
    user.value = await authApi.setPushPreviews(enabled);
  }

  /** Forget the session locally (sign-out, or the server saying 401). */
  function clear() {
    user.value = null;
    if (isNativeApp) {
      setAuthToken(null);
      void nativeAdapters().then(n => n.tokens.clear());
    }
  }

  async function logout() {
    try {
      await authApi.logout(); // revokes the bearer token server-side on native
    } finally {
      if (isNativeApp)
        await (await nativeAdapters()).signIn.signOut().catch(() => undefined);
      clear();
    }
  }

  /** Erase the account, then forget the session locally (the server already dropped it). */
  async function deleteAccount(confirmation: string) {
    await authApi.deleteAccount(confirmation);
    if (isNativeApp)
      await (await nativeAdapters()).signIn.signOut().catch(() => undefined);
    clear();
  }

  return {
    user,
    ready,
    isAuthenticated,
    profileLink,
    init,
    signIn,
    signInNative,
    devLogin,
    setUsername,
    setPushPreviews,
    clear,
    deleteAccount,
    logout
  };
});
