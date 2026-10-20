/** Same-origin in-app paths only: whatever a link or notification carries, it can never navigate elsewhere. */
export function safePath(path: string): string | null {
  return path.startsWith("/") && !path.startsWith("//") ? path : null;
}
