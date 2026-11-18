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

export const SANS =
  '"Inter Variable", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
export const DISPLAY = `"Bricolage Grotesque Variable", "Nunito Variable", "Avenir Next", ${SANS}`;

export type Measure = (text: string, px: number) => number;

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

let segmenter: Intl.Segmenter | null | undefined;

/** User-perceived characters, so emoji sequences (ZWJ, flags, skin tones) are never cut in half. */
export function graphemes(text: string): string[] {
  if (segmenter === undefined) {
    segmenter =
      typeof Intl !== "undefined" && "Segmenter" in Intl
        ? new Intl.Segmenter(undefined, { granularity: "grapheme" })
        : null;
  }
  if (!segmenter) return Array.from(text);
  return Array.from(segmenter.segment(text), part => part.segment);
}

/** Shorten `text` with an ellipsis until it fits at the given size. Pure. */
export function ellipsize(
  measure: Measure,
  text: string,
  maxWidth: number,
  px: number
): string {
  if (measure(text, px) <= maxWidth) return text;
  const chars = graphemes(text);
  let lo = 0;
  let hi = chars.length;
  while (lo < hi) {
    const mid = Math.ceil((lo + hi) / 2);
    if (measure(chars.slice(0, mid).join("") + "…", px) <= maxWidth) lo = mid;
    else hi = mid - 1;
  }
  return chars.slice(0, lo).join("") + "…";
}

/** Greedy word wrap; words wider than maxWidth are hard-split. Pure . */
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
    while (measure(rest, px) > maxWidth && graphemes(rest).length > 1) {
      const chars = graphemes(rest);
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
  const load = Promise.all([
    document.fonts.load(`800 64px ${DISPLAY}`),
    document.fonts.load(`800 40px ${SANS}`),
    document.fonts.ready
  ]).catch(() => undefined);
  // A stalled font fetch must not block the card forever: draw with what we have.
  await Promise.race([load, new Promise(resolve => setTimeout(resolve, 2500))]);
}

/** Text-width measurer bound to a canvas context and font family (weight 800). */
export function measureIn(
  ctx: CanvasRenderingContext2D,
  family: string
): Measure {
  return (text, px) => {
    ctx.font = `800 ${px}px ${family}`;
    return ctx.measureText(text).width;
  };
}

export function canvasToBlob(canvas: HTMLCanvasElement): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob(blob => {
      if (blob) resolve(blob);
      else reject(new Error("Could not create the image"));
    }, "image/png");
  });
}

export function roundRect(
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
  const display = measureIn(ctx, DISPLAY);
  const sans = measureIn(ctx, SANS);

  paintBackground(ctx, template);
  ctx.direction = "ltr";
  ctx.textAlign = "center";
  ctx.textBaseline = "alphabetic";
  const cx = CARD_WIDTH / 2;

  ctx.fillStyle = p.accent;
  ctx.font = `800 56px ${DISPLAY}`;
  // Instagram covers roughly the top 250px and bottom 340px with its own UI.
  ctx.fillText("psst", cx, 330);

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
  const chipY = 1400;
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

/** Longest note shown on a card, in user-perceived characters. */
export const NOTE_MAX_CHARS = 280;

/**
 * Make received text safe and predictable to draw: unify newlines, drop control and bidi
 * override characters (they can reorder or hide text), collapse blank-line runs, trim.
 */
export function cleanNote(raw: string): string {
  return raw
    .replace(/\r\n?|\u2028|\u2029/g, "\n")
    .replace(
      // eslint-disable-next-line no-control-regex
      /[\u0000-\u0008\u000b-\u001f\u007f-\u009f\u200e\u200f\u202a-\u202e\u2066-\u2069\ufeff]/g,
      ""
    )
    .replace(/[^\S\n]+/g, " ")
    .replace(/ ?\n ?/g, "\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

/** True when the first strongly-directional letter is right-to-left (Hebrew, Arabic, ...). */
export function isRtlText(text: string): boolean {
  const m = /[\p{L}]/u.exec(text);
  return m ? /[\u0590-\u08ff\ufb1d-\ufdff\ufe70-\ufeff]/.test(m[0]) : false;
}

/** The note as it will be drawn: cleaned and capped. Empty means there is nothing to share. */
export function prepareNote(raw: string): string {
  const text = cleanNote(raw);
  const chars = graphemes(text);
  if (chars.length <= NOTE_MAX_CHARS) return text;
  return chars.slice(0, NOTE_MAX_CHARS).join("").trimEnd() + "…";
}

/**
 * Render a received note as a speech-bubble story card (1080x1920 PNG). The note is drawn as
 * plain canvas text, on this device only. The card carries the text, the receiver's handle and
 * link, and nothing about the sender or when it arrived. Right-to-left notes are drawn with
 * RTL direction centred; mixed-direction lines rely on the browser's bidi handling.
 */
export async function renderNoteCard(
  template: StoryTemplate,
  note: string,
  username: string,
  url: string
): Promise<Blob> {
  const text = prepareNote(note);
  if (!text) throw new Error("Nothing to share");
  await ensureFonts();
  const canvas = getCanvas();
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Canvas is not available");
  const p = PALETTES[template];
  const margin = 96;
  const maxW = CARD_WIDTH - margin * 2;
  const display = measureIn(ctx, DISPLAY);
  const sans = measureIn(ctx, SANS);
  const cx = CARD_WIDTH / 2;

  paintBackground(ctx, template);
  ctx.textAlign = "center";
  ctx.textBaseline = "alphabetic";
  ctx.direction = "ltr";

  // Instagram covers roughly the top 250px and bottom 340px with its own UI.
  ctx.fillStyle = p.accent;
  ctx.font = `800 56px ${DISPLAY}`;
  ctx.fillText("psst", cx, 330);

  const handle = "@" + username;
  const labelPx = 40;
  ctx.fillStyle = p.text;
  ctx.font = `800 ${labelPx}px ${DISPLAY}`;
  ctx.fillText(
    ellipsize(display, `anonymous message for ${handle}`, maxW, labelPx),
    cx,
    400
  );

  // Bubble: fit the note by shrinking the font, then truncating if it still overflows.
  const pad = 64;
  const textW = maxW - pad * 2;
  const regionTop = 450;
  const regionBottom = 1130;
  const maxTextH = regionBottom - regionTop - pad * 2;
  const layout = (px: number): string[][] =>
    text
      .split("\n")
      .map(par => (par.trim() ? wrapText(display, par, textW, px) : []));
  const heightOf = (paras: string[][], px: number): number => {
    const lh = px * 1.25;
    const gap = px * 0.5;
    return paras.reduce(
      (h, lines, i) => h + (lines.length || 0.6) * lh + (i ? gap : 0),
      0
    );
  };
  let px = 84;
  let paras = layout(px);
  while (heightOf(paras, px) > maxTextH && px > 34) {
    px -= 2;
    paras = layout(px);
  }
  const lh = px * 1.25;
  const gap = px * 0.5;
  // Flatten to drawable rows; at the minimum size, drop rows that do not fit.
  const rows: (string | null)[] = [];
  paras.forEach((lines, i) => {
    if (i) rows.push(null);
    if (lines.length) rows.push(...lines);
    else rows.push(null);
  });
  let rowsH = heightOf(paras, px);
  if (rowsH > maxTextH) {
    const kept: (string | null)[] = [];
    let used = 0;
    for (const row of rows) {
      const next = used + (row === null ? gap : lh);
      if (next > maxTextH) break;
      kept.push(row);
      used = next;
    }
    while (kept.length && kept[kept.length - 1] === null) kept.pop();
    const lastIdx = kept.length - 1;
    const last = kept[lastIdx];
    if (last) kept[lastIdx] = ellipsize(display, last + "…", textW, px);
    rows.length = 0;
    rows.push(...kept);
    rowsH = used;
  } else {
    rowsH = rows.reduce<number>((h, r) => h + (r === null ? gap : lh), 0);
  }

  const bubbleH = Math.max(240, rowsH + pad * 2);
  const bubbleTop = regionTop + (regionBottom - regionTop - bubbleH) / 2;
  const bubbleX = margin;
  ctx.fillStyle = p.chip;
  roundRect(ctx, bubbleX, bubbleTop, maxW, bubbleH, 56);
  ctx.fill();
  // Tail at the bottom left, like a chat bubble.
  ctx.beginPath();
  ctx.moveTo(bubbleX + 90, bubbleTop + bubbleH - 2);
  ctx.lineTo(bubbleX + 60, bubbleTop + bubbleH + 52);
  ctx.lineTo(bubbleX + 170, bubbleTop + bubbleH - 2);
  ctx.closePath();
  ctx.fill();

  ctx.fillStyle = p.chipText;
  ctx.font = `800 ${px}px ${DISPLAY}`;
  ctx.direction = isRtlText(text) ? "rtl" : "ltr";
  let y = bubbleTop + pad;
  for (const row of rows) {
    if (row === null) {
      y += gap;
      continue;
    }
    ctx.fillText(row, cx, y + px * 0.95);
    y += lh;
  }
  ctx.direction = "ltr";

  ctx.fillStyle = p.text;
  const ctaPx = fitFontSize(
    display,
    "send me anonymous messages",
    maxW,
    64,
    34
  );
  ctx.font = `800 ${ctaPx}px ${DISPLAY}`;
  ctx.fillText("send me anonymous messages", cx, 1260);

  const chipPad = 48;
  const inner = maxW - chipPad * 2;
  const shown = url.replace(/^https?:\/\//, "");
  const urlPx = fitFontSize(sans, shown, inner, 54, 26);
  const urlText = ellipsize(sans, shown, inner, urlPx);
  const chipW = Math.min(maxW, sans(urlText, urlPx) + chipPad * 2);
  const chipH = 120;
  const chipY = 1330;
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
  let file: File | null = null;
  try {
    file = new File([blob], "psst-story.png", { type: "image/png" });
  } catch {
    /* no File constructor: download instead */
  }
  if (
    file &&
    typeof navigator.share === "function" &&
    typeof navigator.canShare === "function" &&
    navigator.canShare({ files: [file] })
  ) {
    try {
      await navigator.share({ files: [file], url });
      return "shared";
    } catch (e) {
      if (isShareCancel(e)) throw e;
      // e.g. NotAllowedError after the user activation expired: save the PNG instead.
    }
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
