<template>
<div class="flex gap-2.5">
  <!-- 작성자 사진(등급 링) — 이름·시간이 들어갈 폭을 너무 줄이지 않게 적당한 크기 -->
  <div class="flex-shrink-0">
    <UserAvatar :user="comment.user" :size="isReply ? 38 : 46" />
  </div>
  <div class="flex-1 min-w-0">
    <!-- 헤더: 이름 + 시간(줄바꿈 없이) + 수정/삭제/신고 -->
    <div class="flex items-center gap-1.5 min-w-0">
      <UserName :userId="comment.user?.id" :name="comment.user?.name" :className="(isReply ? 'text-xs' : 'text-[13px]') + ' font-bold text-ink whitespace-nowrap truncate max-w-[9rem]'" />
      <span class="text-[11px] text-ink-muted whitespace-nowrap flex-shrink-0">{{ relativeDate }}</span>
      <span class="text-[11px] text-ink-faint whitespace-nowrap hidden sm:inline">{{ fullDate }}</span>
      <span class="ml-auto flex items-center gap-2 flex-shrink-0">
        <button v-if="auth.user?.id === comment.user_id && !editing && !comment._local" @click="startEdit" class="text-gray-300 hover:text-amber-600 transition-colors" title="수정"><AppIcon name="edit" :size="14" /></button>
        <button v-if="auth.user?.id === comment.user_id && !comment._local" @click="deleteComment" class="text-gray-300 hover:text-red-500 transition-colors" title="삭제"><AppIcon name="trash" :size="14" /></button>
        <button v-if="!comment._local" @click="showReportModal=true" class="text-gray-300 hover:text-ink-muted transition-colors" title="신고"><AppIcon name="flag" :size="14" /></button>
      </span>
    </div>
    <!-- 내용 -->
    <div v-if="!editing" class="text-sm text-ink-light mt-0.5 whitespace-pre-wrap leading-relaxed break-words" :class="comment._pending ? 'opacity-60' : ''">{{ comment.content }}</div>
    <template v-if="!editing">
      <div v-if="comment._pending" class="text-[11px] text-ink-faint mt-0.5">등록 중...</div>
      <div v-else-if="comment._failed" class="text-[11px] text-red-500 mt-0.5">
        {{ comment._error || '등록 실패' }}
        <button type="button" @click="$emit('resend', comment)" class="ml-1 font-bold underline">재전송</button>
        <button type="button" @click="$emit('discard', comment)" class="ml-1 text-ink-faint underline">삭제</button>
      </div>
    </template>
    <!-- 수정 입력 상자 — 수정 버튼을 눌렀을 때만 나타난다 (예전엔 v-else 연결이 잘못돼 항상 떠 있었음) -->
    <div v-if="editing" class="mt-1">
      <textarea ref="editBox" v-model="editText" rows="3" maxlength="1000" class="input-soft w-full text-sm" @keydown="onEditEnter" @compositionend="onEditCompEnd" @keydown.esc="editing = false"></textarea>
      <div class="flex justify-end gap-2 mt-1">
        <button @click="editing = false" class="text-xs text-ink-muted px-3 py-1 rounded-full hover:bg-gray-100">취소</button>
        <button @click="saveEdit" :disabled="!editText.trim()" class="text-xs bg-amber-400 text-white font-bold px-3 py-1 rounded-full hover:bg-amber-500 disabled:opacity-50">저장</button>
      </div>
    </div>    <!-- 액션: 좋아요 싫어요 답글 -->
    <div v-if="!comment._local" class="flex items-center gap-3 mt-1.5">
      <button @click="vote('like')" class="flex items-center gap-1 text-xs hover:bg-gray-100 px-1.5 py-0.5 rounded-full transition-colors" :class="myVote==='like' ? 'text-blue-600' : 'text-ink-muted'">
        <AppIcon name="thumbs-up" :size="13" /> <span v-if="localLikes">{{ localLikes }}</span>
      </button>
      <button @click="vote('dislike')" class="flex items-center gap-1 text-xs hover:bg-gray-100 px-1.5 py-0.5 rounded-full transition-colors" :class="myVote==='dislike' ? 'text-red-500' : 'text-ink-muted'">
        <AppIcon name="thumbs-up" :size="13" class="rotate-180" /> <span v-if="localDislikes">{{ localDislikes }}</span>
      </button>
      <button v-if="!isReply && auth.user?.id !== comment.user_id" @click="$emit('reply', comment.id, comment.user?.name)" class="text-xs text-ink-muted font-bold hover:bg-gray-100 px-2 py-0.5 rounded-full transition-colors">답글</button>
    </div>
  </div>
</div>

<!-- 신고 모달 -->
<Teleport to="body">
  <div v-if="showReportModal" class="fixed inset-0 z-[100] flex items-center justify-center" @click.self="showReportModal=false">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
      <div class="px-5 py-3 flex items-center justify-between border-b border-gray-50">
        <span class="font-black text-ink flex items-center gap-2">
          <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="flag" :size="14" /></span>신고
        </span>
        <button @click="showReportModal=false" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="18" /></button>
      </div>
      <div class="px-5 py-3 max-h-80 overflow-y-auto">
        <div v-for="r in reportReasons" :key="r" class="py-2.5 border-b border-gray-50 last:border-0">
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" v-model="selectedReason" :value="r" class="w-4 h-4 text-amber-500" />
            <span class="text-sm text-ink-light">{{ r }}</span>
          </label>
        </div>
        <div class="py-2.5">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="radio" v-model="selectedReason" value="기타" class="w-4 h-4 text-amber-500 mt-0.5" />
            <div class="flex-1">
              <span class="text-sm text-ink-light">기타</span>
              <textarea v-if="selectedReason==='기타'" v-model="customReason" rows="2" placeholder="신고 사유를 입력하세요..." class="input-soft mt-1"></textarea>
            </div>
          </label>
        </div>
      </div>
      <div class="px-5 py-3 border-t border-gray-50">
        <button @click="submitReport" :disabled="!selectedReason || (selectedReason==='기타' && !customReason.trim())"
          class="btn-primary w-full">신고</button>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useModal } from '../composables/useModal'
import { useEnterSend } from '../composables/useEnterSend'
import axios from 'axios'
import AppIcon from './AppIcon.vue'
import UserAvatar from './UserAvatar.vue'

const props = defineProps({ comment: Object, type: String, typeId: [Number, String], isReply: Boolean })
const emit = defineEmits(['reply', 'refresh', 'deleted', 'resend', 'discard'])
const auth = useAuthStore()
const { showAlert, showConfirm } = useModal()

const showReportModal = ref(false)
const selectedReason = ref('')
const customReason = ref('')
const reportReasons = ['성적인 콘텐츠', '폭력적 또는 혐오스러운 콘텐츠', '증오 또는 악의적 콘텐츠', '괴롭힘 또는 따돌림', '유해하거나 위험한 행위', '허위 정보', '아동 학대', '스팸 또는 사기', '개인정보 침해']

const localLikes = ref(props.comment.likes || 0)
const localDislikes = ref(props.comment.dislikes || 0)
const myVote = ref(null)

const relativeDate = computed(() => {
  if (!props.comment.created_at) return ''
  const h = Math.floor((Date.now() - new Date(props.comment.created_at).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h / 24) + '일 전'
})

const fullDate = computed(() => {
  if (!props.comment.created_at) return ''
  const d = new Date(props.comment.created_at)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
})

const editing = ref(false)
const editText = ref('')
const editBox = ref(null)
function startEdit() { editText.value = props.comment.content; editing.value = true; nextTick(() => editBox.value?.focus()) }
const editEnter = useEnterSend(() => saveEdit())   // 한글 조합 중 Enter 도 조합이 끝나면 저장
function onEditEnter(e) { editEnter.onKeydown(e) }
const onEditCompEnd = () => editEnter.onCompositionend()
async function saveEdit() {
  const content = editText.value.trim()
  if (!content) return
  try {
    await axios.put(`/api/comments/${props.comment.id}`, { content })
    props.comment.content = content   // 목록을 다시 불러오지 않고 바로 반영
    editing.value = false
  } catch (e) { alert(e.response?.data?.message || '수정에 실패했습니다.') }
}

async function deleteComment() {
  if (!await showConfirm('댓글을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/comments/${props.comment.id}`)
    emit('deleted', props.comment.id)
  } catch { alert('삭제에 실패했습니다.') }
}

async function vote(type) {
  if (!auth.isLoggedIn) { await showAlert('로그인이 필요합니다.', '알림'); return }
  try {
    const { data } = await axios.post(`/api/comments/${props.comment.id}/vote`, { vote: type })
    localLikes.value = data.likes
    localDislikes.value = data.dislikes
    myVote.value = data.action === 'removed' ? null : data.action
  } catch {}
}

async function submitReport() {
  if (!auth.isLoggedIn) { showReportModal.value = false; await showAlert('로그인이 필요합니다.', '알림'); return }
  const reason = selectedReason.value === '기타' ? customReason.value.trim() : selectedReason.value
  try {
    await axios.post('/api/reports', { reportable_type: 'comment', reportable_id: props.comment.id, reason })
    showReportModal.value = false; selectedReason.value = ''; customReason.value = ''
    await showAlert('신고가 접수되었습니다.', '완료')
  } catch (e) {
    await showAlert(e.response?.data?.message || '신고 실패', '오류')
  }
}
</script>
