import { defineStore } from "pinia";
import { computed, reactive, ref } from "vue";
import { threadApi } from "@/api/endpoints";
import type { InboxThread, Message, SentThread } from "@/api/types";

type Thread = InboxThread | SentThread;

interface ListState<T> {
  items: T[];
  cursor: string | null;
  loading: boolean;
  loaded: boolean;
  error: string | null;
}

const emptyList = <T>(): ListState<T> => ({
  items: [],
  cursor: null,
  loading: false,
  loaded: false,
  error: null
});

/** Newest activity first; ids are time-ordered UUIDs so they break ties. */
function byRecency<T extends Thread>(a: T, b: T): number {
  return (
    (b.last_message_at ?? "").localeCompare(a.last_message_at ?? "") ||
    b.id.localeCompare(a.id)
  );
}

function mergeThreads<T extends Thread>(existing: T[], incoming: T[]): T[] {
  const map = new Map(existing.map(t => [t.id, t]));
  for (const thread of incoming) map.set(thread.id, thread);
  return [...map.values()].sort(byRecency);
}

let tempCounter = 0;

export const useMessagingStore = defineStore("messaging", () => {
  const inbox = reactive<ListState<InboxThread>>(emptyList());
  const sent = reactive<ListState<SentThread>>(emptyList());
  /** Messages per thread in reading order (oldest first). */
  const messages = reactive<Record<string, Message[]>>({});
  const olderCursor = reactive<Record<string, string | null>>({});
  const threadLoading = ref<string | null>(null);
  const threadError = ref<string | null>(null);

  const inboxUnread = computed(() =>
    inbox.items.reduce((sum, t) => sum + t.unread_count, 0)
  );
  const sentUnread = computed(() =>
    sent.items.reduce((sum, t) => sum + t.unread_count, 0)
  );

  function errorMessage(e: unknown): string {
    return e instanceof Error
      ? e.message
      : "Something went wrong. Please try again.";
  }

  // ---- thread lists ----

  async function loadList(kind: "inbox" | "sent", { more = false } = {}) {
    const list = (kind === "inbox" ? inbox : sent) as ListState<Thread>;
    if (list.loading || (more && !list.cursor)) return;
    list.loading = true;
    list.error = null;
    try {
      const page = await (kind === "inbox"
        ? threadApi.inbox(more ? (list.cursor ?? undefined) : undefined)
        : threadApi.sent(more ? (list.cursor ?? undefined) : undefined));
      list.items = mergeThreads(list.items as Thread[], page.data as Thread[]);
      // A refresh of page one must not discard the cursor of deeper pages we already hold.
      if (more || !list.loaded) list.cursor = page.meta.next_cursor;
      list.loaded = true;
    } catch (e) {
      list.error = errorMessage(e);
    } finally {
      list.loading = false;
    }
  }

  const loadInbox = (opts?: { more?: boolean }) => loadList("inbox", opts);
  const loadSent = (opts?: { more?: boolean }) => loadList("sent", opts);

  function findThread(threadId: string): Thread | undefined {
    return (
      inbox.items.find(t => t.id === threadId) ??
      sent.items.find(t => t.id === threadId)
    );
  }

  // ---- conversation ----

  function mergeMessages(existing: Message[], incoming: Message[]): Message[] {
    const map = new Map(existing.filter(m => !m.pending).map(m => [m.id, m]));
    for (const message of incoming) map.set(message.id, message);
    const confirmed = [...map.values()].sort((a, b) =>
      a.id.localeCompare(b.id)
    );
    return [...confirmed, ...existing.filter(m => m.pending || m.failed)];
  }

  async function openThread(threadId: string) {
    threadLoading.value = threadId;
    threadError.value = null;
    try {
      const page = await threadApi.messages(threadId);
      messages[threadId] = mergeMessages(
        messages[threadId] ?? [],
        [...page.data].reverse()
      );
      if (!(threadId in olderCursor))
        olderCursor[threadId] = page.meta.next_cursor;
    } catch (e) {
      threadError.value = errorMessage(e);
    } finally {
      threadLoading.value = null;
    }
  }

  /** Quietly re-fetch the newest page (used by polling): no spinner, no error banner. */
  async function refreshThread(threadId: string) {
    try {
      const page = await threadApi.messages(threadId);
      messages[threadId] = mergeMessages(
        messages[threadId] ?? [],
        [...page.data].reverse()
      );
    } catch {
      /* keep what we have; the next tick retries */
    }
  }

  async function loadOlder(threadId: string) {
    const cursor = olderCursor[threadId];
    if (!cursor || threadLoading.value === threadId) return;
    threadLoading.value = threadId;
    try {
      const page = await threadApi.messages(threadId, cursor);
      messages[threadId] = mergeMessages(
        messages[threadId] ?? [],
        [...page.data].reverse()
      );
      olderCursor[threadId] = page.meta.next_cursor;
    } catch (e) {
      threadError.value = errorMessage(e);
    } finally {
      threadLoading.value = null;
    }
  }

  function hasOlder(threadId: string): boolean {
    return Boolean(olderCursor[threadId]);
  }

  function touchThread(threadId: string, message: Message) {
    const thread = findThread(threadId);
    if (!thread) return;
    thread.last_message = message;
    thread.last_message_at = message.created_at;
    const list = inbox.items.some(t => t.id === threadId) ? inbox : sent;
    (list as ListState<Thread>).items = [...(list.items as Thread[])].sort(
      byRecency
    );
  }

  /** Optimistic send: the bubble appears instantly and is reconciled (or marked failed) afterwards. */
  async function send(threadId: string, body: string): Promise<void> {
    const temp: Message = {
      id: `tmp-${++tempCounter}`,
      body,
      mine: true,
      read_at: null,
      created_at: new Date().toISOString(),
      pending: true
    };
    messages[threadId] = [...(messages[threadId] ?? []), temp];

    try {
      const real = await threadApi.reply(threadId, body);
      messages[threadId] = mergeMessages(
        (messages[threadId] ?? []).filter(m => m.id !== temp.id),
        [real]
      );
      touchThread(threadId, real);
    } catch (e) {
      const list = messages[threadId] ?? [];
      messages[threadId] = list.map(m =>
        m.id === temp.id ? { ...m, pending: false, failed: true } : m
      );
      throw e;
    }
  }

  function discard(threadId: string, messageId: string) {
    messages[threadId] = (messages[threadId] ?? []).filter(
      m => m.id !== messageId
    );
  }

  async function markRead(threadId: string) {
    const thread = findThread(threadId);
    if (!thread || thread.unread_count === 0) return;
    thread.unread_count = 0;
    try {
      await threadApi.markRead(threadId);
    } catch {
      /* unread badge self-corrects on the next list refresh */
    }
  }

  async function setBlocked(threadId: string, blocked: boolean) {
    await (blocked ? threadApi.block(threadId) : threadApi.unblock(threadId));
    const thread = inbox.items.find(t => t.id === threadId);
    if (thread) thread.blocked = blocked;
  }

  async function report(messageId: string, reason: string) {
    await threadApi.report(messageId, reason);
  }

  /** Drop everything cached for the previous user. */
  function reset() {
    Object.assign(inbox, emptyList());
    Object.assign(sent, emptyList());
    for (const key of Object.keys(messages)) delete messages[key];
    for (const key of Object.keys(olderCursor)) delete olderCursor[key];
    threadError.value = null;
  }

  return {
    inbox,
    sent,
    messages,
    threadLoading,
    threadError,
    inboxUnread,
    sentUnread,
    loadInbox,
    loadSent,
    findThread,
    openThread,
    refreshThread,
    loadOlder,
    hasOlder,
    send,
    discard,
    markRead,
    setBlocked,
    report,
    reset
  };
});
