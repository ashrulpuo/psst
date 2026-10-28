/**
 * Story stickers. Each message gets its own color so the inbox reads as a
 * stack of notes, while the app chrome stays neutral.
 * Stops stay dark enough for white type (WCAG AA).
 */
export const STORY_GRADIENTS = [
  "linear-gradient(165deg, #c2185b 0%, #6a1b9a 100%)",
  "linear-gradient(165deg, #283593 0%, #00695c 100%)",
  "linear-gradient(165deg, #e65100 0%, #ad1457 100%)",
  "linear-gradient(160deg, #4a148c 0%, #c2185b 100%)",
  "linear-gradient(165deg, #00695c 0%, #1a237e 100%)"
] as const;

export const SHARE_GRADIENT =
  "linear-gradient(165deg, #c2185b 0%, #6a1b9a 46%, #283593 100%)";

export function storyGradient(id: string): string {
  let n = 0;
  for (let i = 0; i < id.length; i++)
    n = (n + id.charCodeAt(i)) % STORY_GRADIENTS.length;
  return STORY_GRADIENTS[n] ?? STORY_GRADIENTS[0];
}
