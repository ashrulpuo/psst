import { fileURLToPath, URL } from "node:url";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

// Production serves the app under /admin/ on the same origin as the API (Caddy), so the session cookie is shared.
export default defineConfig(({ command }) => ({
  base: command === "build" ? "/admin/" : "/",
  plugins: [vue(), tailwindcss()],
  resolve: { alias: { "@": fileURLToPath(new URL("./src", import.meta.url)) } },
  server: {
    port: 9100,
    // Same origin in dev as in production: no CORS, cookie auth just works.
    proxy: { "/api": "http://127.0.0.1:8000", "/sanctum": "http://127.0.0.1:8000" },
  },
  build: { target: "baseline-widely-available", sourcemap: false },
}));
