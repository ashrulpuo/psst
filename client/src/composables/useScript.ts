const loaded = new Map<string, Promise<void>>();

/** Load a third-party script once; resolves when it has executed. */
export function loadScript(src: string): Promise<void> {
  let promise = loaded.get(src);
  if (!promise) {
    promise = new Promise<void>((resolve, reject) => {
      const el = document.createElement("script");
      el.src = src;
      el.async = true;
      el.onload = () => resolve();
      el.onerror = () => {
        loaded.delete(src);
        reject(new Error(`Failed to load ${src}`));
      };
      document.head.appendChild(el);
    });
    loaded.set(src, promise);
  }
  return promise;
}
