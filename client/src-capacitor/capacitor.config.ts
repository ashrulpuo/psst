import { defineCapacitorConfig } from "@quasar/app-vite/capacitor";

export default defineCapacitorConfig({
  appId: "app.psst.mobile",
  appName: "Psst",
  server: {
    // Android serves the app from https://localhost (not http), so the API's CORS allow-list and secure-context
    // features (crypto, service-less push) behave like production. iOS uses capacitor://localhost.
    androidScheme: "https"
  },
  plugins: {
    SplashScreen: {
      // The app hides it once it has rendered (see src/native/shell.ts), so there is no white flash.
      launchAutoHide: false,
      backgroundColor: "#5b4bf0",
      androidSplashResourceName: "splash",
      showSpinner: false
    },
    Keyboard: {
      // Shrink the web view so the composer stays above the keyboard.
      resize: "body",
      resizeOnFullScreen: true
    },
    FirebaseMessaging: {
      // While the app is open the in-app realtime UI shows the message: no system banner in the foreground.
      presentationOptions: []
    }
  }
});
