const dateTime = new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" });
const dateOnly = new Intl.DateTimeFormat(undefined, { dateStyle: "medium" });
const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: "auto", style: "short" });
const compact = new Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 1 });
const full = new Intl.NumberFormat();

export const formatDateTime = (iso: string | null) => (iso ? dateTime.format(new Date(iso)) : "—");
export const formatDate = (iso: string | null) => (iso ? dateOnly.format(new Date(iso)) : "—");
export const formatNumber = (n: number) => full.format(n);
/** 1,284 stays exact; 12,900 becomes 12.9K. */
export const formatCompact = (n: number) => (n < 10000 ? full.format(n) : compact.format(n));

/** "now", "5 min ago", "2 hr ago", "3 days ago", then a date. */
export function timeAgo(iso: string | null, now = Date.now()): string {
  if (!iso) return "never";
  const diff = (new Date(iso).getTime() - now) / 1000;
  const abs = Math.abs(diff);
  if (abs < 45) return "just now";
  if (abs < 3600) return rtf.format(Math.round(diff / 60), "minute");
  if (abs < 86400) return rtf.format(Math.round(diff / 3600), "hour");
  if (abs < 14 * 86400) return rtf.format(Math.round(diff / 86400), "day");
  return dateOnly.format(new Date(iso));
}

export const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join("");

/** Turn "user.banned" into "User banned". */
export const humanizeAction = (action: string) => {
  const text = action.replace(/[._]/g, " ");
  return text.charAt(0).toUpperCase() + text.slice(1);
};
