import { ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

/**
 * useChat(conversationId)
 * Uses window.Echo (already initialized in bootstrap.js) for real-time messaging.
 * Uses axios (JWT auto-attached) for API calls.
 */
export function useChat(conversationId) {
  const messages    = ref([])
  const isLoading   = ref(false)
  const hasMore     = ref(true)
  const currentPage = ref(1)
  const isSending   = ref(false)
  let   channel     = null

  const auth = useAuthStore()

  /**
   * Load messages from API (paginated, newest first from server, reversed for display)
   */
  async function loadMessages(page = 1) {
    isLoading.value = true
    try {
      const { data } = await axios.get(
        `/api/comms/conversations/${conversationId}/messages`,
        { params: { page } }
      )
      if (page === 1) {
        messages.value = [...data.data].reverse()
      } else {
        messages.value = [...[...data.data].reverse(), ...messages.value]
      }
      hasMore.value     = !!data.next_page_url
      currentPage.value = page
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Send a message to a partner. Optimistic update with rollback on error.
   */
  async function sendMessage(partnerId, body) {
    if (!body.trim() || isSending.value) return
    isSending.value = true

    const myId = auth.user?.id
    const tempMsg = {
      id:         'temp-' + Date.now(),
      body,
      sender_id:  myId,
      created_at: new Date().toISOString(),
      isPending:  true,
    }
    messages.value.push(tempMsg)

    try {
      const { data } = await axios.post(
        `/api/comms/conversations/${partnerId}/send`,
        { body }
      )
      // Replace temp message with server response
      const idx = messages.value.findIndex(m => m.id === tempMsg.id)
      if (idx !== -1) {
        messages.value[idx] = { ...data, isPending: false }
      }
    } catch (err) {
      // Rollback optimistic update
      messages.value = messages.value.filter(m => m.id !== tempMsg.id)
      throw err
    } finally {
      isSending.value = false
    }
  }

  /**
   * Subscribe to real-time messages via window.Echo (Laravel Reverb).
   * Echo is already initialized in bootstrap.js — do NOT re-init.
   */
  function subscribe() {
    if (!window.Echo) {
      console.warn('[useChat] window.Echo not available. WebSocket disabled.')
      return
    }
    const myId = auth.user?.id
    channel = window.Echo
      .private(`conversation.${conversationId}`)
      .listen('.message.sent', (event) => {
        // Only add messages from other users (our own are added optimistically)
        if (event.sender_id !== myId) {
          messages.value.push(event)
        }
      })
  }

  /**
   * Unsubscribe from the conversation channel
   */
  function unsubscribe() {
    if (channel) {
      window.Echo?.leave(`conversation.${conversationId}`)
      channel = null
    }
  }

  /**
   * Load older messages (pagination)
   */
  async function loadMore() {
    if (!hasMore.value || isLoading.value) return
    await loadMessages(currentPage.value + 1)
  }

  return {
    messages,
    isLoading,
    isSending,
    hasMore,
    loadMessages,
    sendMessage,
    loadMore,
    subscribe,
    unsubscribe,
  }
}
