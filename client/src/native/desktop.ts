/** The bridge the Electron preload exposes (src-electron/electron-preload.ts). Absent in browsers and mobile apps. */
export interface DesktopBridge {
  platform: string;
  setBadge(count: number): void;
  notify(input: { title: string; body: string; path?: string }): void;
  onNavigate(handler: (path: string) => void): void;
}

declare global {
  interface Window {
    psstDesktop?: DesktopBridge;
  }
}

export const desktop: DesktopBridge | undefined =
  typeof window === "undefined" ? undefined : window.psstDesktop;
