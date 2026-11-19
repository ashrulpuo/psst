import { trackEvent } from "@/api/endpoints";

/** Set by an anonymous send; holds no identifying data. Cleared once the conversion is reported. */
const KEY = "psst.guest-sent";

export function markGuestSent(): void {
  try {
    localStorage.setItem(KEY, "1");
  } catch {
    /* private mode: the conversion just isn't counted */
  }
}

/** Report `own_link_after_guest` once, if this browser sent as a guest before. Never blocks or throws. */
export function reportOwnLinkAfterGuest(): void {
  try {
    if (localStorage.getItem(KEY) !== "1") return;
    localStorage.removeItem(KEY);
  } catch {
    return;
  }
  void trackEvent("own_link_after_guest");
}
