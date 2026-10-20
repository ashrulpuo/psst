/**
 * The secret that lets an unsigned sender's browser be recognised (and later claim its threads once they sign in).
 * The server issues it on the first anonymous send; only its hash is stored there.
 */
const KEY = "psst.guest-token";

export function guestToken(): string | null {
  try {
    return localStorage.getItem(KEY);
  } catch {
    return null;
  }
}

export function setGuestToken(token: string): void {
  try {
    localStorage.setItem(KEY, token);
  } catch {
    /* private mode: the thread just can't be claimed later */
  }
}

export function clearGuestToken(): void {
  try {
    localStorage.removeItem(KEY);
  } catch {
    /* non-fatal */
  }
}
