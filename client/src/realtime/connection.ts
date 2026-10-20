import type Echo from "laravel-echo";
import { apiOrigin, getAuthToken, xsrfToken } from "@/api/http";
import { isNativeApp } from "@/native/platform";
import type {
  BlockChangedPayload,
  MessagePostedPayload,
  MessagesReadPayload
} from "@/api/types";
import { config } from "@/utils/config";

export interface RealtimeHandlers {
  messagePosted(payload: MessagePostedPayload): void;
  blockChanged(payload: BlockChangedPayload): void;
  messagesRead(payload: MessagesReadPayload): void;
  /** Connection state changed. `reconnected` is true when we came back after a drop. */
  state(connected: boolean, reconnected: boolean): void;
}

interface PusherLike {
  connection: {
    bind(event: string, cb: (e: { current: string }) => void): void;
  };
}

let echo: Echo<"reverb"> | null = null;

/** Ask the API whether this session may join a private channel: cookie + CSRF on the web, bearer on a device. */
async function authorizeChannel(
  socketId: string,
  channelName: string
): Promise<unknown> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json"
  };
  if (isNativeApp) {
    const bearer = getAuthToken();
    if (bearer) headers.Authorization = `Bearer ${bearer}`;
  } else {
    const token = xsrfToken();
    if (token) headers["X-XSRF-TOKEN"] = token;
  }

  const response = await fetch(new URL("/api/broadcasting/auth", apiOrigin()), {
    method: "POST",
    credentials: isNativeApp ? "omit" : "include",
    headers,
    body: JSON.stringify({ socket_id: socketId, channel_name: channelName })
  });
  if (!response.ok)
    throw new Error(`Channel authorization failed (${response.status})`);
  return response.json();
}

/**
 * Open the Reverb connection and subscribe to the user's private channel.
 * Echo and Pusher are imported lazily so the public link page never downloads them.
 */
export async function openConnection(
  userId: string,
  handlers: RealtimeHandlers
): Promise<void> {
  closeConnection();

  const [{ default: EchoClient }, { default: Pusher }] = await Promise.all([
    import("laravel-echo"),
    import("pusher-js")
  ]);
  (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

  // Reverb lives behind the API's host (Caddy proxies /app); a device app's own origin is capacitor://localhost.
  const origin = new URL(apiOrigin());
  const secure = origin.protocol === "https:";
  const port = Number(origin.port) || (secure ? 443 : 80);

  const client = new EchoClient({
    broadcaster: "reverb",
    key: config.reverbKey,
    wsHost: origin.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: secure,
    enabledTransports: ["ws", "wss"],
    // Same-origin cookie session: authorize each private channel through the API with a fresh CSRF token.
    authorizer: (channel: { name: string }) => ({
      authorize: async (
        socketId: string,
        callback: (error: Error | null, data: unknown) => void
      ) => {
        try {
          callback(null, await authorizeChannel(socketId, channel.name));
        } catch (error) {
          callback(error as Error, null);
        }
      }
    })
  } as ConstructorParameters<typeof EchoClient>[0]) as Echo<"reverb">;
  echo = client;

  let wasConnected = false;
  const pusher = (client.connector as unknown as { pusher: PusherLike }).pusher;
  pusher.connection.bind("state_change", ({ current }) => {
    const connected = current === "connected";
    handlers.state(connected, connected && wasConnected);
    if (connected) wasConnected = true;
  });

  // A leading dot means "this exact event name" (no App\Events namespace prefix).
  client
    .private(`users.${userId}`)
    .listen(".message.posted", (payload: MessagePostedPayload) =>
      handlers.messagePosted(payload)
    )
    .listen(".thread.block-changed", (payload: BlockChangedPayload) =>
      handlers.blockChanged(payload)
    )
    .listen(".messages.read", (payload: MessagesReadPayload) =>
      handlers.messagesRead(payload)
    );
}

export function closeConnection(): void {
  echo?.disconnect();
  echo = null;
}
