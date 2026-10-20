import { createPinia } from "pinia";
import { createApp } from "vue";
import App from "./App.vue";
import { router } from "./router";
import { applyTheme } from "./lib/theme";
import "./style.css";

applyTheme();
// Follow the OS theme while the user has not picked one explicitly.
window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => applyTheme());

createApp(App).use(createPinia()).use(router).mount("#app");
