// The only surface the web app gets in the desktop shell. Kept tiny on purpose: no Node, no file access, no generic IPC.
import { contextBridge, ipcRenderer } from "electron";
import { quasarRuntime } from "#q-app/electron/preload";

contextBridge.exposeInMainWorld("quasarRuntime", quasarRuntime);

contextBridge.exposeInMainWorld("psstDesktop", {
  platform: process.platform,
  /** Unread count for the dock / taskbar badge. */
  setBadge: (count: number) => ipcRenderer.send("psst:badge", count),
  /** Native notification; clicking it focuses the app and navigates to `path` (an in-app path). */
  notify: (input: { title: string; body: string; path?: string }) =>
    ipcRenderer.send("psst:notify", input),
  /** Called when a notification is clicked. */
  onNavigate: (handler: (path: string) => void) => {
    ipcRenderer.on("psst:navigate", (_e, path: unknown) => {
      if (typeof path === "string") handler(path);
    });
  }
});
