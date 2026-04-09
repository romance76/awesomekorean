<template>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
  <div class="px-5 py-3 border-b font-bold text-sm text-gray-800">💬 댓글 {{ totalCount }}개</div>

  <!-- 댓글 입력 -->
  <div v-if="auth.isLoggedIn" class="px-5 py-3 border-b">
    <div class="flex gap-3">
      <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center text-xs font-bold text-amber-700 flex-shrink-0 mt-0.5">{{ (auth.user?.name||'?')[0] }}</div>
      <div class="flex-1">
        <textarea v-model="newComment" rows="2" placeholder="댓글 추가..." class="w-full border-0 border-b border-gray-200 px-0 py-1 text-sm resize-none outline-none focus:border-amber-400 transition"></textarea>
        <div v-if="newComment.trim()" class="flex justify-end gap-2 mt-2">
          <button @click="newComment=''" class="text-xs text-gray-500 px-3 py-1.5 rounded-full hover:bg-gray-100">취소</button>
          <button @click="submitComment(null)" class="text-xs bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-full hover:bg-amber-500">댓글</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 댓글 목록 -->
  <div class="divide-y">
    <div v-for="c in comments" :key="c.id" class="px-5 py-3">
      <CommentItem :comment="c" :type="type" :typeId="typeId" @reply="openReply" @refresh="loadComments" />

      <!-- 대댓글 -->
      <div v-if="c.replies?.length" class="ml-11 mt-1">
        <button v-if="!c._showReplies" @click="c._showReplies = true" class="text-xs text-blue-600 font-bold py-1 hover:bg-blue-50 px-2 rounded-full">
          ▼ 답글 {{ c.replies.length }}개
        </button>
        <template v-if="c._showReplies">
          <button @click="c._showReplies = false" class="text-xs text-blue-600 font-bold py-1 hover:bg-blue-50 px-2 rounded-full mb-1">
            ▲ 답글 숨기기
          </button>
          <div v-for="r in c.replies" :key="r.id" class="py-2">
            <CommentItem :comment="r" :type="type" :typeId="typeId" :isReply="true" @reply="openReply" @refresh="loadComments" />
          </div>
        </template>
      </div>

      <!-- 답글 입력 -->
      <div v-if="replyTo === c.id" class="ml-11 mt-2">
        <div class="flex gap-2">
          <div class="w-6 h-6 bg-amber-100 rounded-full flex items-center justify-center text-[10px] font-bold text-amber-700 flex-shrink-0 mt-0.5">{{ (auth.user?.name||'?')[0] }}</div>
          <div class="flex-1">
            <textarea v-model="replyText" rows="2" placeholder="답글 추가..." class="w-full border-0 border-b border-gray-200 px-0 py-1 text-xs resize-none outline-none focus:border-amber-400"></textarea>
            <div class="flex justify-end gap-2 mt-1">
              <button @click="replyTo=null; replyText=''" class="text-[10px] text-gray-500 px-2 py-1 rounded-full hover:bg-gray-100">취소</button>
              <button @click="submitComment(c.id)" :disabled="!replyText.trim()" class="text-[10px] bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded-full hover:bg-amber-500 disabled:opacity-50">답글</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-if="!comments.length" class="px-5 py-8 text-center text-sm text-gray-400">첫 댓글을 남겨보세요!</div>
</div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'
import CommentItem from './CommentItem.vue'

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

function openReply(commentId, userName) {
  replyTo.value = commentId
  replyName.value = userName
  replyText.value = ''
}

async function submitComment(parentId) {
  const content = parentId ? replyText.value.trim() : newComment.value.trim()
  if (!content) return
  try {
    await axios.post('/api/comments', {
      commentable_type: props.type,
      commentable_id: props.typeId,
      content,
      parent_id: parentId,
    })
    if (parentId) { replyTo.value = null; replyText.value = '' }
    else newComment.value = ''
    await loadComments()
  } catch {}
}

async function loadComments() {
  try {
    const { data } = await axios.get(`/api/comments/${props.type}/${props.typeId}`)
    comments.value = (data.data || []).map(c => ({ ...c, _showReplies: false }))
  } catch {}
}

onMounted(loadComments)
defineExpose({ loadComments })
</script>
