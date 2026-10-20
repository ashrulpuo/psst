import type { LocationQueryValue } from "vue-router";

/** Only same-origin paths: never follow a `next` that could bounce the user to another site. */
export function safeNext(
  next: LocationQueryValue | LocationQueryValue[] | undefined
): string | null {
  return typeof next === "string" &&
    next.startsWith("/") &&
    !next.startsWith("//")
    ? next
    : null;
}
