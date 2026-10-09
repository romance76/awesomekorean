import { ref } from 'vue'
import axios from 'axios'

/**
 * 쪽지(POST /api/messages) 낙관적 전송 — 쪽지 팝업과 마이페이지 쪽지 탭이 같이 쓴다.
 * - send(): 즉시 임시 말풍선을 목록에 넣고(대기 상태) 뒤에서 순서대로 전송. 입력창은 막지 않는다.
 * - 성공하면 임시 말풍선을 서버 쪽지로 교체, 실패하면 _failed 로 표시(재전송/삭제 가능).
 * - merge(): 주기적 새로고침 결과에 전송 중/실패 말풍선을 이어 붙여 사라지지 않게 한다.
 *
 * @param list  쪽지 배열 ref (오래된 것 → 최신)
 * @param myId  () => 내 사용자 id
 */
export function useMessageSender(list, myId) {
  let chain = Promise.resolve()
  let seq = 0
  let lastDoneAt = 0
  const pending = ref(0)

  async function deliver(receiverId, temp) {
    try {
      const { data } = await axios.post('/api/messages', { receiver_id: receiverId, content: temp.content })
      lastDoneAt = Date.now()
      const saved = data.data
        ? { ...data.data, sender_id: Number(data.data.sender_id ?? myId()) }
        : { ...temp, id: temp.id, _tmp: false, _failed: false }
      const dup = saved.id !== temp.id && list.value.some(m => m.id === saved.id)
      list.value = dup
        ? list.value.filter(m => m.id !== temp.id)
        : list.value.map(m => (m.id === temp.id ? saved : m))
    } catch (e) {
      const i = list.value.findIndex(m => m.id === temp.id)
      if (i !== -1) {
        list.value[i] = {
          ...list.value[i],
          _tmp: true,
          _failed: true,
          _error: e?.response?.data?.message || (e?.response?.data?.errors && Object.values(e.response.data.errors)[0]?.[0]) || '',
        }
      }
    }
  }

  function enqueue(receiverId, temp) {
    pending.value++
    chain = chain
      .then(() => deliver(receiverId, temp))
      .catch(() => {})
      .finally(() => { pending.value-- })
  }

  /** 즉시 말풍선 표시 후 전송 대기열에 넣는다. 만든 임시 말풍선을 돌려준다. */
  function send(receiverId, content) {
    content = (content || '').trim()
    if (!content || !receiverId) return null
    const temp = {
      id: 'tmp-' + Date.now() + '-' + (++seq),
      sender_id: Number(myId()),
      receiver_id: receiverId,
      content,
      created_at: new Date().toISOString(),
      _tmp: true,
      _failed: false,
    }
    list.value.push(temp)
    enqueue(receiverId, temp)
    return temp
  }

  function retry(receiverId, tempId) {
    const i = list.value.findIndex(m => m.id === tempId)
    if (i === -1) return
    const temp = { ...list.value[i], _tmp: true, _failed: false, _error: '' }
    list.value[i] = temp
    enqueue(receiverId, temp)
  }

  function discard(tempId) {
    list.value = list.value.filter(m => m.id !== tempId)
  }

  /**
   * 서버에서 새로 받은 목록(rows)에 전송 중/실패 말풍선을 이어 붙인다.
   * startedAt: 그 요청을 시작한 시각(ms) — 요청 도중 전송이 끝난 쪽지가 목록에서 잠깐 사라지는 것을 막는다.
   */
  function merge(rows, startedAt) {
    const ids = new Set(rows.map(r => r.id))
    const keep = list.value.filter(m =>
      m._tmp || (startedAt < lastDoneAt && !ids.has(m.id) && typeof m.id === 'number' && Number(m.sender_id) === Number(myId()))
    )
    return [...rows, ...keep]
  }

  return { send, retry, discard, merge, pending }
}
