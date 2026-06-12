<template>
<div class="flex gap-3">
  <div class="flex-shrink-0 mt-0.5" :class="isReply ? 'w-6 h-6' : 'w-8 h-8'">
    <div class="w-full h-full bg-amber-100 rounded-full flex items-center justify-center font-bold text-amber-700"
      :class="isReply ? 'text-[11px]' : 'text-xs'">{{ (comment.user?.name||'?')[0] }}</div>
  </div>
  <div class="flex-1 min-w-0">
    <!-- 헤더: 이름 + 날짜 + 신고 -->
    <div class="flex items-center gap-2">
      <UserName :userId="comment.user?.id" :name="comment.user?.name" :className="isReply ? 'text-xs font-bold text-ink' : 'text-sm font-bold text-ink'" />
      <span class="text-[11px] text-ink-muted">{{ relativeDate }}</span>
      <span class="text-[11px] text-ink-faint">{{ fullDate }}</span>
      <button v-if="auth.user?.id === comment.user_id" @click="deleteComment" class="ml-auto text-gray-300 hover:text-red-500 transition-colors" title="삭제"><AppIcon name="trash" :size="14" /></button>
      <button @click="showReportModal=true" class="text-gray-300 hover:text-ink-muted transition-colors" :class="auth.user?.id === comment.user_id ? '' : 'ml-auto'" title="신고"><AppIcon name="flag" :size="14" /></button>
    </div>
    <!-- 내용 -->
    <div class="text-sm text-ink-light mt-0.5 whitespace-pre-wrap leading-relaxed">{{ comment.content }}</div>
    <!-- 액션: 좋아요 싫어요 답글 -->
    <div class="flex items-center gap-3 mt-1.5">
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
import { ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useModal } from '../composables/useModal'
import axios from 'axios'
import AppIcon from './AppIcon.vue'

const props = defineProps({ comment: Object, type: String, typeId: [Number, String], isReply: Boolean })
const emit = defineEmits(['reply', 'refresh', 'deleted'])
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

async function deleteComment() {
  if (!confirm('댓글을 삭제하시겠습니까?')) return
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
