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
  /** Local-only shortcut: shows demo-account sign-in. The API refuses it in production regardless. */
  // Quasar parses "true"/"false" in .env files into booleans, so compare the string form.
  devLogin: String(import.meta.env.QCLI_DEV_LOGIN) === "true"
} as const;
