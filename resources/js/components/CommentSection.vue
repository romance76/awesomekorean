<template>
<div class="card overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-2">
    <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="message-circle" :size="14" /></span>댓글 {{ totalCount }}개
  </div>

  <!-- 댓글 입력 -->
  <div v-if="auth.isLoggedIn" class="px-5 py-3 border-b border-gray-50">
    <VerifyGate message="이메일 인증 후 댓글을 쓸 수 있어요.">
    <div class="flex gap-3">
      <div class="flex-shrink-0"><UserAvatar :user="auth.user" :size="46" /></div>
      <div class="flex-1">
        <textarea v-model="newComment" rows="1" placeholder="댓글 추가..." class="w-full border-0 border-b-2 border-gray-200 text-sm text-ink placeholder:text-ink-faint resize-none outline-none focus:border-amber-400 transition" style="padding:0;line-height:1.2;height:25px;margin:0" @focus="$event.target.rows=3" @blur="blurComment($event)" @keydown="onEnter($event, null)" @compositionend="onCompEnd"></textarea>
        <div v-if="newComment.trim()" class="flex justify-end gap-2 mt-2">
          <button @click="newComment=''" class="text-xs text-ink-muted px-3 py-1.5 rounded-full hover:bg-gray-100 transition-colors">취소</button>
          <button @click="submitComment(null)" class="text-xs bg-amber-400 text-white font-bold px-4 py-1.5 rounded-full hover:bg-amber-500 transition-colors">댓글</button>
        </div>
      </div>
    </div>
    </VerifyGate>
  </div>

  <!-- 댓글 목록 -->
  <div class="divide-y divide-gray-50">
    <div v-for="c in comments" :key="c.id" class="px-5 py-3">
      <CommentItem :comment="c" :type="type" :typeId="typeId" @reply="openReply" @refresh="loadComments" @deleted="onDeleted" @resend="resendComment" @discard="discardComment" />

      <!-- 대댓글 -->
      <div v-if="c.replies?.length" class="ml-11 mt-1">
        <button v-if="!c._showReplies" @click="c._showReplies = true" class="text-xs text-blue-600 font-bold py-1 hover:bg-blue-50 px-2 rounded-full transition-colors">
          ▼ 답글 {{ c.replies.length }}개
        </button>
        <template v-if="c._showReplies">
          <button @click="c._showReplies = false" class="text-xs text-blue-600 font-bold py-1 hover:bg-blue-50 px-2 rounded-full mb-1 transition-colors">
            ▲ 답글 숨기기
          </button>
          <div v-for="r in c.replies" :key="r.id" class="py-2">
            <CommentItem :comment="r" :type="type" :typeId="typeId" :isReply="true" @reply="openReply" @refresh="loadComments" @deleted="onDeleted" @resend="resendComment" @discard="discardComment" />
          </div>
        </template>
      </div>

      <!-- 답글 입력 -->
      <div v-if="replyTo === c.id" class="ml-11 mt-2">
        <VerifyGate message="이메일 인증 후 답글을 쓸 수 있어요.">
        <div class="flex gap-2">
          <div class="w-6 h-6 bg-amber-100 rounded-full flex items-center justify-center text-[11px] font-bold text-amber-700 flex-shrink-0 mt-0.5">{{ (auth.user?.name||'?')[0] }}</div>
          <div class="flex-1">
            <textarea v-model="replyText" rows="1" placeholder="답글 추가..." class="w-full border-0 border-b-2 border-gray-200 text-xs text-ink placeholder:text-ink-faint resize-none outline-none focus:border-amber-400 transition" style="padding:0;line-height:1.2;height:25px;margin:0" @focus="$event.target.rows=3" @keydown="onEnter($event, c.id)" @compositionend="onCompEnd"></textarea>
            <div class="flex justify-end gap-2 mt-1">
              <button @click="replyTo=null; replyText=''" class="text-[11px] text-ink-muted px-2 py-1 rounded-full hover:bg-gray-100 transition-colors">취소</button>
              <button @click="submitComment(c.id)" :disabled="!replyText.trim()" class="text-[11px] bg-amber-400 text-white font-bold px-3 py-1 rounded-full hover:bg-amber-500 disabled:opacity-50 transition-colors">답글</button>
            </div>
          </div>
        </div>
        </VerifyGate>
      </div>
    </div>
  </div>

  <div v-if="!comments.length" class="px-5 py-8 text-center text-sm text-ink-muted">첫 댓글을 남겨보세요!</div>
</div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'
import CommentItem from './CommentItem.vue'
import AppIcon from './AppIcon.vue'
import UserAvatar from './UserAvatar.vue'
import VerifyGate from './VerifyGate.vue'
import { useEnterSend } from '../composables/useEnterSend'

const props = defineProps({ type: String, typeId: [Number, String] })
const auth = useAuthStore()
const comments = ref([])
const newComment = ref('')
const replyTo = ref(null)
const replyName = ref('')
const replyText = ref('')

const totalCount = computed(() => {
  let c = comments.value.length
  comments.value.forEach(cm => { c += cm.replies?.length || 0 })
  return c
})

// Enter = 바로 등록, Shift+Enter = 줄바꿈 (한글 입력 중 조합 상태의 Enter 는 무시)
// Enter 전송 (한글 조합 중 Enter 도 조합이 끝나면 바로 전송 — useEnterSend 참고)
const enterSend = useEnterSend((parentId) => submitComment(parentId))
function onEnter(e, parentId) { enterSend.onKeydown(e, parentId) }
const onCompEnd = () => enterSend.onCompositionend()

function blurComment(e) { if (!newComment.value.trim()) e.target.rows = 1 }

function openReply(commentId, userName) {
  replyTo.value = commentId
  replyName.value = userName
  replyText.value = ''
}

// 낙관적 등록: 입력창을 바로 비우고 댓글을 즉시 목록에 올린 뒤(보내는 중), 서버 응답으로 확정한다.
// 연속으로 여러 개 보내도 막지 않으며, 실패한 댓글은 "재전송"/"삭제"를 고를 수 있다.
let tmpSeq = 0
function submitComment(parentId) {
  const content = parentId ? replyText.value.trim() : newComment.value.trim()
  if (!content) return
  if (parentId) { replyTo.value = null; replyText.value = '' }
  else newComment.value = ''

  const tmp = reactive({
    id: 'tmp-' + (++tmpSeq), _local: true, _pending: true, _failed: false,
    _parentId: parentId || null,
    user_id: auth.user?.id, user: auth.user,
    content, parent_id: parentId || null,
    created_at: new Date().toISOString(),
    likes: 0, dislikes: 0, replies: [], _showReplies: false,
  })
  if (parentId) {
    const parent = comments.value.find(c => c.id === parentId)
    if (parent) {
      parent.replies = [...(parent.replies || []), tmp]
      parent._showReplies = true
    }
  } else {
    comments.value.unshift(tmp)
  }
  postComment(tmp)
}

function findTmp(tmp) {
  if (tmp._parentId) {
    const parent = comments.value.find(c => c.id === tmp._parentId)
    return { list: parent?.replies, parent }
  }
  return { list: comments.value, parent: null }
}

async function postComment(tmp) {
  tmp._pending = true
  tmp._failed = false
  tmp._error = ''
  const typeAtSend = props.type, idAtSend = props.typeId
  try {
    const { data } = await axios.post('/api/comments', {
      commentable_type: props.type,
      commentable_id: props.typeId,
      content: tmp.content,
      parent_id: tmp._parentId,
    })
    if (typeAtSend !== props.type || idAtSend !== props.typeId) return   // 그 사이 다른 글로 이동
    const real = { ...data.data, replies: [], _showReplies: false }
    const { list, parent } = findTmp(tmp)
    if (!list) return
    const idx = list.findIndex(x => x.id === tmp.id)
    if (idx === -1) return
    if (parent) parent.replies = list.map(x => x.id === tmp.id ? real : x)
    else comments.value[idx] = real
  } catch (e) {
    tmp._pending = false
    tmp._failed = true
    tmp._error = e.response?.data?.message || (e.response ? '댓글을 등록하지 못했습니다.' : '네트워크 연결을 확인해주세요.')
  }
}

function resendComment(c) {
  const { list } = findTmp(c)
  const tmp = list?.find(x => x.id === c.id)
  if (tmp && !tmp._pending) postComment(tmp)
}

function discardComment(c) {
  const { list, parent } = findTmp(c)
  if (!list) return
  const rest = list.filter(x => x.id !== c.id)
  if (parent) parent.replies = rest
  else comments.value = rest
}

// 삭제는 목록 전체를 다시 불러오지 않고 해당 댓글만 화면에서 제거
function onDeleted(id) {
  const top = comments.value.findIndex(c => c.id === id)
  if (top !== -1) { comments.value.splice(top, 1); return }
  comments.value.forEach(c => {
    if (c.replies?.some(r => r.id === id)) c.replies = c.replies.filter(r => r.id !== id)
  })
}

async function loadComments() {
  try {
    const { data } = await axios.get(`/api/comments/${props.type}/${props.typeId}`)
    comments.value = (data.data || []).map(c => ({ ...c, _showReplies: false }))
  } catch {}
}

onMounted(loadComments)

// typeId 변경 시 이전 글의 댓글/입력 상태를 완전 초기화 + 리로드
// (PostNavigator 등으로 같은 페이지에서 글 이동할 때 이전 댓글이 남던 버그 수정)
watch(() => [props.type, props.typeId], ([t, id], [pt, pid]) => {
  if (t === pt && id === pid) return
  comments.value = []
  newComment.value = ''
  replyTo.value = null
  replyName.value = ''
  replyText.value = ''
  if (id) loadComments()
})

defineExpose({ loadComments })
</script>
