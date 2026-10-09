import { ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

/**
 * useChat(conversationId, partnerId)
 * - conversationId 는 없을 수 있음(null): 친구 목록에서 처음 여는 경우.
 *   그때는 /api/comms/conversations 에서 partnerId 로 기존 대화를 찾고,
 *   없으면 첫 메시지 전송 시 서버가 만든 conversation_id 를 받아 구독한다.
 * - 서버는 최신순(id desc)으로 40개씩 내려주고, 화면용으로 뒤집어 오래된 것이 위로 오게 한다.
 */
export function useChat(initialConversationId, partnerId) {
  const messages    = ref([])
  const isLoading   = ref(false)
  const hasMore     = ref(false)
  const currentPage = ref(1)
  const isSending   = ref(false)
  let   convId      = initialConversationId ? Number(initialConversationId) : null
  let   channel     = null

  const auth = useAuthStore()
  const myId = () => Number(auth.user?.id)

  function normalize(m) {
    return { ...m, sender_id: Number(m.sender_id) }
  }

  function mergeUnique(list) {
    const seen = new Set()
    return list.filter(m => {
      const k = String(m.id)
      if (seen.has(k)) return false
      seen.add(k)
      return true
    })
  }

  /** partnerId 로 기존 대화 찾기 */
  async function resolveConversation() {
    if (convId) return convId
    if (!partnerId) return null
    try {
      const { data } = await axios.get('/api/comms/conversations')
      const list = Array.isArray(data) ? data : (data.data || [])
      const found = list.find(c => Number(c.partner?.id) === Number(partnerId))
      if (found) convId = Number(found.id)
    } catch { /* 대화 없음으로 취급 */ }
    return convId
  }

  async function loadMessages(page = 1) {
    isLoading.value = true
    try {
      const id = await resolveConversation()
      if (!id) { messages.value = []; hasMore.value = false; return }
      const { data } = await axios.get(
        `/api/comms/conversations/${id}/messages`,
        { params: { page } }
      )
      const rows = [...(data.data || [])].map(normalize).reverse() // 최신순 → 오래된 순
      if (page === 1) {
        const pending = messages.value.filter(m => m.isPending)
        messages.value = mergeUnique([...rows, ...pending])
      } else {
        messages.value = mergeUnique([...rows, ...messages.value])
      }
      hasMore.value     = !!data.next_page_url
      currentPage.value = page
    } finally {
      isLoading.value = false
    }
  }

  async function sendMessage(pId, body) {
    if (!body.trim() || isSending.value) return
    isSending.value = true

    const tempMsg = {
      id:         'temp-' + Date.now(),
      body,
      sender_id:  myId(),
      created_at: new Date().toISOString(),
      isPending:  true,
    }
    messages.value.push(tempMsg)

    try {
      const { data } = await axios.post(
        `/api/comms/conversations/${pId}/send`,
        { body }
      )
      const saved = { ...normalize(data), sender_id: myId(), isPending: false }
      // 첫 메시지로 대화가 생긴 경우 → 지금부터 실시간 구독
      if (!convId && data.conversation_id) {
        convId = Number(data.conversation_id)
        subscribe()
      }
      // 임시 메시지를 서버 응답으로 교체 (이미 같은 id 가 있으면 중복 제거)
      messages.value = mergeUnique(
        messages.value.map(m => (m.id === tempMsg.id ? saved : m))
      )
    } catch (err) {
      messages.value = messages.value.filter(m => m.id !== tempMsg.id)
      throw err
    } finally {
      isSending.value = false
    }
  }

  function subscribe() {
    if (!window.Echo) {
      console.warn('[useChat] window.Echo not available. WebSocket disabled.')
      return
    }
    if (!convId || channel) return
    channel = window.Echo
      .private(`conversation.${convId}`)
      .listen('.comm.message.sent', (event) => {
        // 내가 보낸 것은 낙관적 업데이트/서버 응답으로 이미 들어 있음
        if (Number(event.sender_id) === myId()) return
        if (messages.value.some(m => String(m.id) === String(event.id))) return
        messages.value.push(normalize(event))
      })
  }

  function unsubscribe() {
    if (channel && convId) {
      window.Echo?.leave(`conversation.${convId}`)
    }
    channel = null
  }

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
