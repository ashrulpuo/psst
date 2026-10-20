import { defineStore } from "pinia";
import { ref } from "vue";
import { closeConnection, openConnection } from "@/realtime/connection";
import { config } from "@/utils/config";
import { useMessagingStore } from "./messaging";

export const useRealtimeStore = defineStore("realtime", () => {
  const connected = ref(false);
  const messaging = useMessagingStore();

  /** Subscribe for this user. No-op (polling only) when no Reverb key is configured. */
  async function start(userId: string): Promise<void> {
    if (!config.reverbKey) return;
    try {
      await openConnection(userId, {
        messagePosted: messaging.applyMessagePosted,
        blockChanged: messaging.applyBlockChanged,
        messagesRead: messaging.applyMessagesRead,
        state: (isConnected, reconnected) => {
          connected.value = isConnected;
          // Events missed while offline are gone: catch up from the API.
          if (reconnected) void messaging.resync();
        }
      });
    } catch {
      // Library failed to load or the socket cannot open: polling keeps the app live.
      connected.value = false;
    }
  }

  function stop(): void {
    closeConnection();
    connected.value = false;
  }

  return { connected, start, stop };
});
