import { defineStore } from "pinia";
import { computed, ref } from "vue";
import { authApi } from "@/api/endpoints";
import type { Me, Provider } from "@/api/types";

export const useAuthStore = defineStore("auth", () => {
  const user = ref<Me | null>(null);
  /** True once the first /me check has finished, so guards never flash the wrong screen. */
  const ready = ref(false);

  const isAuthenticated = computed(() => user.value !== null);
  const profileLink = computed(() =>
    user.value?.username
      ? `${window.location.origin}/${user.value.username}`
      : ""
  );

  let initializing: Promise<void> | null = null;

  function init(): Promise<void> {
    initializing ??= authApi
      .me()
      .then(me => {
        user.value = me;
      })
      .catch(() => {
        user.value = null;
      })
      .finally(() => {
        ready.value = true;
      });
    return initializing;
  }

  async function signIn(
    provider: Provider,
    idToken: string,
    nonce: string,
    name?: string
  ) {
    user.value = await authApi.signIn(provider, idToken, nonce, name);
  }

  async function devLogin(email: string) {
    user.value = await authApi.devLogin(email);
  }

  async function setUsername(username: string) {
    user.value = await authApi.setUsername(username);
  }

  /** Forget the session locally (used on sign-out and when the server says 401). */
  function clear() {
    user.value = null;
  }

  async function logout() {
    try {
      await authApi.logout();
    } finally {
      clear();
    }
  }

  return {
    user,
    ready,
    isAuthenticated,
    profileLink,
    init,
    signIn,
    devLogin,
    setUsername,
    clear,
    logout
  };
});
