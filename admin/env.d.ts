/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Public Google OAuth client ID (same one the main app uses). */
  readonly VITE_GOOGLE_CLIENT_ID?: string;
  /** Local development only: show demo-account sign-in. */
  readonly VITE_DEV_LOGIN?: string;
}
