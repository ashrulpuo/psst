/**
 * Build-time settings. Quasar v3 exposes variables prefixed QCLI_ from the environment or a .env file
 * (see client/.env.example). None of these are secrets: OAuth client IDs are public by design.
 *
 * Each variable must be written out as a literal `import.meta.env.QCLI_*` expression: the bundler
 * substitutes those exact expressions, so aliasing `import.meta.env` would leave them undefined.
 */
export const config = {
  googleClientId:
    (import.meta.env.QCLI_GOOGLE_CLIENT_ID as string | undefined) ?? "",
  appleClientId:
    (import.meta.env.QCLI_APPLE_CLIENT_ID as string | undefined) ?? "",
  appleRedirectUri:
    (import.meta.env.QCLI_APPLE_REDIRECT_URI as string | undefined) ?? "",
  /** Public Reverb app key (not the secret). Empty disables realtime and the app falls back to polling. */
  reverbKey: (import.meta.env.QCLI_REVERB_KEY as string | undefined) ?? "",
  /**
   * Origin of the API when the UI is not served from it (the native apps load from capacitor://localhost or
   * https://localhost). Empty for the web apps, which are same-origin.
   */
  apiUrl: ((import.meta.env.QCLI_API_URL as string | undefined) ?? "").replace(
    /\/+$/,
    ""
  ),
  /** Public https origin of the deployment: what shared links and app links point at. Needed on native. */
  publicUrl: (
    (import.meta.env.QCLI_PUBLIC_URL as string | undefined) ?? ""
  ).replace(/\/+$/, ""),
  /** iOS OAuth client ID for native Google sign-in (the web client ID above is used on Android). */
  googleIosClientId:
    (import.meta.env.QCLI_GOOGLE_IOS_CLIENT_ID as string | undefined) ?? "",
  /** Test builds only: run the native code paths in a normal browser against in-page fakes (see native/simulator.ts). */
  nativeSimulator: String(import.meta.env.QCLI_NATIVE_SIMULATOR) === "true",
  /** Local-only shortcut: shows demo-account sign-in. The API refuses it in production regardless. */
  // Quasar parses "true"/"false" in .env files into booleans, so compare the string form.
  devLogin: String(import.meta.env.QCLI_DEV_LOGIN) === "true"
} as const;
