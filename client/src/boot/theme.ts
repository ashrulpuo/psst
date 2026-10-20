import { defineBoot } from "#q-app";
import { Dark } from "quasar";

export const THEME_KEY = "psst.theme";
export type ThemeChoice = "auto" | "light" | "dark";

export function applyTheme(choice: ThemeChoice) {
  Dark.set(choice === "auto" ? "auto" : choice === "dark");
}

export default defineBoot(() => {
  let saved: string | null = null;
  try {
    saved = localStorage.getItem(THEME_KEY);
  } catch {
    /* storage can be blocked (private mode); fall back to the device setting */
  }
  applyTheme(saved === "light" || saved === "dark" ? saved : "auto");
});
