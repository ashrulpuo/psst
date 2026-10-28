/**
 * Story stickers. Each message gets its own color so the inbox reads as a
 * stack of notes, while the app chrome stays neutral.
 * Stops stay dark enough for white type (WCAG AA).
 */
export const STORY_GRADIENTS = [
  "linear-gradient(150deg, #d6004f 0%, #7c3aed 100%)",
  "linear-gradient(150deg, #1d4ed8 0%, #0f766e 100%)",
  "linear-gradient(150deg, #c2410c 0%, #9d174d 100%)",
  "linear-gradient(150deg, #6d28d9 0%, #be185d 100%)",
  "linear-gradient(150deg, #0f766e 0%, #1e3a8a 100%)"
] as const;

export const SHARE_GRADIENT =
  "linear-gradient(165deg, #c2185b 0%, #6a1b9a 46%, #283593 100%)";

export function storyGradient(id: string): string {
  let n = 0;
  for (let i = 0; i < id.length; i++)
    n = (n + id.charCodeAt(i)) % STORY_GRADIENTS.length;
  return STORY_GRADIENTS[n] ?? STORY_GRADIENTS[0];
}
