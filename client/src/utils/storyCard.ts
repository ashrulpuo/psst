// Story cards: a 1080x1920 PNG the owner posts to a story. Task 13 adds the
// received-note card on top of the helpers below (one shared canvas, text
// fitting, font loading and shareImage).

export const STORY_TEMPLATES = [
  "sunset",
  "mint",
  "ink",
  "candy",
  "paper"
] as const;
export type StoryTemplate = (typeof STORY_TEMPLATES)[number];

export const CARD_WIDTH = 1080;
export const CARD_HEIGHT = 1920;

interface Palette {
  /** Gradient stops, top-left to bottom-right (like --grad-link). */
  stops: [string, string, ...string[]];
  text: string;
  /** Chip fill behind the link. */
  chip: string;
  chipText: string;
  accent: string;
}

const PALETTES: Record<StoryTemplate, Palette> = {
  sunset: {
    stops: ["#c2410c", "#d6004f", "#7c3aed"],
    text: "#ffffff",
    chip: "#ffffff",
    chipText: "#1b1024",
    accent: "#c9ff4f"
  },
  mint: {
    stops: ["#0f766e", "#10b981", "#c9ff4f"],
    text: "#06231f",
    chip: "#06231f",
    chipText: "#ffffff",
    accent: "#ffffff"
  },
  ink: {
    stops: ["#14101c", "#1b1024", "#3b1d6e"],
    text: "#f6f1ff",
    chip: "#c9ff4f",
    chipText: "#1b1024",
    accent: "#c9ff4f"
  },
  candy: {
    stops: ["#ff7eb6", "#ffb86b", "#fff176"],
    text: "#3a0a2a",
    chip: "#3a0a2a",
    chipText: "#fff7ef",
    accent: "#ffffff"
  },
  paper: {
    stops: ["#fff7ef", "#ffeede"],
    text: "#1b1024",
    chip: "#1b1024",
    chipText: "#fff7ef",
    accent: "#d6004f"
  }
};

/** CSS gradient for swatches and thumbnails, so the picker matches the PNG. */
export function storyGradientCss(template: StoryTemplate): string {
  return `linear-gradient(150deg, ${PALETTES[template].stops.join(", ")})`;
}

/** Foreground colour for text drawn over the template (thumbnails). */
export function storyTextColor(template: StoryTemplate): string {
  return PALETTES[template].text;
}

const SANS =
  '"Inter Variable", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
const DISPLAY = `"Bricolage Grotesque Variable", "Nunito Variable", "Avenir Next", ${SANS}`;

type Measure = (text: string, px: number) => number;

/** Largest font size in [min, start] at which `text` fits `maxWidth`. Pure. */
export function fitFontSize(
  measure: Measure,
  text: string,
  maxWidth: number,
  start: number,
  min: number
): number {
  const w = measure(text, start);
  if (w <= maxWidth || w === 0) return start;
  return Math.max(min, Math.floor((start * maxWidth) / w));
}

/** Shorten `text` with an ellipsis until it fits at the given size. Pure. */
export function ellipsize(
  measure: Measure,
  text: string,
  maxWidth: number,
  px: number
): string {
  if (measure(text, px) <= maxWidth) return text;
  const chars = Array.from(text);
  let lo = 0;
  let hi = chars.length;
  while (lo < hi) {
    const mid = Math.ceil((lo + hi) / 2);
    if (measure(chars.slice(0, mid).join("") + "…", px) <= maxWidth) lo = mid;
    else hi = mid - 1;
  }
  return chars.slice(0, lo).join("") + "…";
}

/** Greedy word wrap; words wider than maxWidth are hard-split. Pure (for Task 13). */
export function wrapText(
  measure: Measure,
  text: string,
  maxWidth: number,
  px: number
): string[] {
  const lines: string[] = [];
  let line = "";
  for (const word of text.split(/\s+/).filter(Boolean)) {
    const next = line ? `${line} ${word}` : word;
    if (measure(next, px) <= maxWidth) {
      line = next;
      continue;
    }
    if (line) lines.push(line);
    line = "";
    let rest = word;
    while (measure(rest, px) > maxWidth && Array.from(rest).length > 1) {
      const chars = Array.from(rest);
      let n = chars.length - 1;
      while (n > 1 && measure(chars.slice(0, n).join(""), px) > maxWidth) n--;
      lines.push(chars.slice(0, n).join(""));
      rest = chars.slice(n).join("");
    }
    line = rest;
  }
  if (line) lines.push(line);
  return lines;
}

let sharedCanvas: HTMLCanvasElement | null = null;

/** One canvas reused for every render, so only a single 1080x1920 bitmap lives. */
export function getCanvas(): HTMLCanvasElement {
  sharedCanvas ??= document.createElement("canvas");
  sharedCanvas.width = CARD_WIDTH;
  sharedCanvas.height = CARD_HEIGHT;
  return sharedCanvas;
}

/** Release the bitmap memory once a render is finished. */
export function releaseCanvas(): void {
  if (sharedCanvas) {
    sharedCanvas.width = 1;
    sharedCanvas.height = 1;
  }
}

/** Make sure the app fonts are loaded before drawing, so text never falls back. */
export async function ensureFonts(): Promise<void> {
  try {
    await Promise.all([
      document.fonts.load(`800 64px ${DISPLAY}`),
      document.fonts.load(`800 40px ${SANS}`)
    ]);
    await document.fonts.ready;
  } catch {
    /* draw with whatever is available */
  }
}

export function canvasToBlob(canvas: HTMLCanvasElement): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob(blob => {
      if (blob) resolve(blob);
      else reject(new Error("Could not create the image"));
    }, "image/png");
  });
}

function roundRect(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  w: number,
  h: number,
  r: number
): void {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

export function paintBackground(
  ctx: CanvasRenderingContext2D,
  template: StoryTemplate
): void {
  const { stops } = PALETTES[template];
  const grad = ctx.createLinearGradient(
    CARD_WIDTH * 0.2,
    0,
    CARD_WIDTH * 0.8,
    CARD_HEIGHT
  );
  stops.forEach((c, i) => grad.addColorStop(i / (stops.length - 1), c));
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, CARD_WIDTH, CARD_HEIGHT);
  ctx.fillStyle = "rgba(255,255,255,0.12)";
  ctx.beginPath();
  ctx.arc(CARD_WIDTH - 40, 180, 360, 0, Math.PI * 2);
  ctx.fill();
}

/**
 * Render the "ask me anything" story card as a 1080x1920 PNG. Shows only the
 * public handle and link; no message content.
 */
export async function renderStoryCard(
  template: StoryTemplate,
  username: string,
  url: string
): Promise<Blob> {
  await ensureFonts();
  const canvas = getCanvas();
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Canvas is not available");
  const p = PALETTES[template];
  const margin = 96;
  const maxW = CARD_WIDTH - margin * 2;
  const measureIn =
    (family: string): Measure =>
    (text, px) => {
      ctx.font = `800 ${px}px ${family}`;
      return ctx.measureText(text).width;
    };
  const display = measureIn(DISPLAY);
  const sans = measureIn(SANS);

  paintBackground(ctx, template);
  ctx.direction = "ltr";
  ctx.textAlign = "center";
  ctx.textBaseline = "alphabetic";
  const cx = CARD_WIDTH / 2;

  ctx.fillStyle = p.accent;
  ctx.font = `800 56px ${DISPLAY}`;
  ctx.fillText("psst", cx, 220);

  ctx.fillStyle = p.text;
  const promptLines = wrapText(display, "ask me anything", maxW, 150);
  ctx.font = `800 150px ${DISPLAY}`;
  promptLines.forEach((line, i) => {
    ctx.fillText(line, cx, 900 + i * 160);
  });

  const handle = "@" + username;
  const handlePx = fitFontSize(display, handle, maxW, 84, 40);
  const handleText = ellipsize(display, handle, maxW, handlePx);
  ctx.font = `800 ${handlePx}px ${DISPLAY}`;
  ctx.fillText(handleText, cx, 900 + promptLines.length * 160 + 40);

  const chipPad = 48;
  const inner = maxW - chipPad * 2;
  const shown = url.replace(/^https?:\/\//, "");
  const urlPx = fitFontSize(sans, shown, inner, 54, 26);
  const urlText = ellipsize(sans, shown, inner, urlPx);
  const chipW = Math.min(maxW, sans(urlText, urlPx) + chipPad * 2);
  const chipH = 120;
  const chipY = 1440;
  ctx.fillStyle = p.chip;
  roundRect(ctx, cx - chipW / 2, chipY, chipW, chipH, chipH / 2);
  ctx.fill();
  ctx.fillStyle = p.chipText;
  ctx.font = `800 ${urlPx}px ${SANS}`;
  ctx.textBaseline = "middle";
  ctx.fillText(urlText, cx, chipY + chipH / 2 + 2);

  try {
    return await canvasToBlob(canvas);
  } finally {
    releaseCanvas();
  }
}

/**
 * Hand a PNG to the share sheet when the browser can share files, otherwise
 * download it. A dismissed share sheet rejects with the browser's AbortError;
 * callers should treat that as a silent cancel (see isShareCancel).
 */
export async function shareImage(
  blob: Blob,
  url: string
): Promise<"shared" | "downloaded"> {
  const file = new File([blob], "psst-story.png", { type: "image/png" });
  if (
    typeof navigator.share === "function" &&
    typeof navigator.canShare === "function" &&
    navigator.canShare({ files: [file] })
  ) {
    await navigator.share({ files: [file], url });
    return "shared";
  }
  const href = URL.createObjectURL(blob);
  try {
    const a = document.createElement("a");
    a.href = href;
    a.download = "psst-story.png";
    a.rel = "noopener";
    document.body.appendChild(a);
    a.click();
    a.remove();
  } finally {
    setTimeout(() => URL.revokeObjectURL(href), 10_000);
  }
  return "downloaded";
}

export function isShareCancel(e: unknown): boolean {
  return e instanceof DOMException && e.name === "AbortError";
}
