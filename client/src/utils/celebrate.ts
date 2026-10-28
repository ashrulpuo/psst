/**
 * The reward. Twelve emoji leave the button on random arcs and the device ticks,
 * unless the person asked for less motion. Particles remove themselves.
 */
const GLYPHS = ["💌", "✨", "🤍", "🎉", "💫", "🔥"];

function reduced(): boolean {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

export function haptic(ms = 8): void {
  if (typeof navigator !== "undefined" && "vibrate" in navigator) {
    navigator.vibrate(ms);
  }
}

export function celebrate(host: HTMLElement | null, count = 12): void {
  if (!host || reduced()) return;

  host.classList.add("celebrate-host");
  const width = host.offsetWidth;

  for (let i = 0; i < count; i++) {
    const bit = document.createElement("span");
    bit.className = "celebrate-bit";
    bit.textContent = GLYPHS[i % GLYPHS.length] ?? GLYPHS[0]!;
    bit.style.left = `${width / 2}px`;
    bit.style.top = "0px";
    bit.style.setProperty("--dx", `${Math.round(Math.random() * 240 - 120)}px`);
    bit.style.setProperty("--dy", `${Math.round(-90 - Math.random() * 150)}px`);
    bit.style.setProperty(
      "--rot",
      `${Math.round(Math.random() * 360 - 180)}deg`
    );
    bit.style.animationDelay = `${i * 18}ms`;
    host.appendChild(bit);
    window.setTimeout(() => bit.remove(), 1100 + i * 18);
  }

  haptic(12);
}
