<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">❓ 질문 등록</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <div>
        <label class="text-sm font-semibold text-gray-700">카테고리</label>
        <select v-model="form.category_id" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
          <option value="">선택하세요</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div><label class="text-sm font-semibold text-gray-700">제목</label><input v-model="form.title" type="text" placeholder="질문을 간단히 요약해주세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">내용</label><textarea v-model="form.content" rows="8" placeholder="자세한 상황을 설명해주세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea></div>
      <div>
        <label class="text-sm font-semibold text-gray-700">현상금 포인트 (선택)</label>
        <input v-model.number="form.bounty_points" type="number" min="0" placeholder="0" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        <p class="text-xs text-gray-400 mt-1">현상금을 설정하면 답변을 더 빨리 받을 수 있습니다. 채택 시 답변자에게 지급됩니다.</p>
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">{{ submitting ? '등록 중...' : '질문 등록' }}</button>
        <button @click="$router.back()" class="text-gray-500 px-6 py-2.5">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
const router = useRouter()
const form = reactive({ category_id:'',title:'',content:'',bounty_points:0 })
const categories = ref([])
const error = ref('')
const submitting = ref(false)
async function submit() {
  if (!form.title || !form.content) { error.value = '제목과 내용을 입력해주세요'; return }
  submitting.value = true; error.value = ''
  try { const { data } = await axios.post('/api/qa', form); router.push(`/qa/${data.data.id}`) }
  catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}
onMounted(async () => { try { const { data } = await axios.get('/api/qa/categories'); categories.value = data.data || [] } catch {} })
</script>
