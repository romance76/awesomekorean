<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">✏️ 글쓰기</h1>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <div>
        <label class="text-sm font-semibold text-gray-700">게시판</label>
        <select v-model="form.board_id" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
          <option value="">게시판을 선택하세요</option>
          <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
      <div>
        <label class="text-sm font-semibold text-gray-700">제목</label>
        <input v-model="form.title" type="text" placeholder="제목을 입력하세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
      </div>
      <div>
        <label class="text-sm font-semibold text-gray-700">내용</label>
        <textarea v-model="form.content" rows="10" placeholder="내용을 입력하세요..." class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea>
      </div>
      <div>
        <label class="text-sm font-semibold text-gray-700">이미지 (선택)</label>
        <input type="file" multiple accept="image/*" @change="onFiles" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" />
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">
          {{ submitting ? '등록 중...' : '등록하기 (+5P)' }}
        </button>
        <button @click="$router.back()" class="text-gray-500 px-6 py-2.5">취소</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const boards = ref([])
const form = ref({ board_id: '', title: '', content: '' })
const files = ref([])
const error = ref('')
const submitting = ref(false)

function onFiles(e) { files.value = Array.from(e.target.files) }

async function submit() {
  if (!form.value.board_id || !form.value.title || !form.value.content) {
    error.value = '게시판, 제목, 내용을 모두 입력해주세요'; return
  }
  submitting.value = true; error.value = ''
  try {
    const fd = new FormData()
    fd.append('board_id', form.value.board_id)
    fd.append('title', form.value.title)
    fd.append('content', form.value.content)
    files.value.forEach(f => fd.append('images[]', f))
    const { data } = await axios.post('/api/posts', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    router.push(`/community/free/${data.data.id}`)
  } catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/boards')
    boards.value = data.data || []
    if (route.params.board) {
      const b = boards.value.find(b => b.slug === route.params.board)
      if (b) form.value.board_id = b.id
    }
  } catch {}
})
</script>
