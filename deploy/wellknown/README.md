# Deep-link verification files

Served at `https://<APP_DOMAIN>/.well-known/` so `https://<APP_DOMAIN>/alice` opens the installed app.

| File | Replace | Where to find it |
|---|---|---|
| `assetlinks.json` | `sha256_cert_fingerprints` | `./gradlew signingReport` (release keystore), or Play Console → App integrity (Play App Signing key) |
| `apple-app-site-association` | the Team ID in `appIDs` | developer.apple.com → Membership |

Until they are replaced the links simply open in the browser. The app id is `app.psst.mobile` (see
`client/src-capacitor/capacitor.config.json`); change it in all three places if you rename it.
