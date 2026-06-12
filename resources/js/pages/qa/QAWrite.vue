<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="help-circle" :size="20" /></span>
      질문 등록
    </h1>
    <div class="card p-5 space-y-4">
      <div>
        <label class="input-label">카테고리</label>
        <select v-model="form.category_id" class="input-soft px-3">
          <option value="">선택하세요</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div><label class="input-label">제목</label><input v-model="form.title" type="text" placeholder="질문을 간단히 요약해주세요" class="input-soft px-3" /></div>
      <div><label class="input-label">내용</label><textarea v-model="form.content" rows="8" placeholder="자세한 상황을 설명해주세요" class="input-soft px-3"></textarea></div>
      <div>
        <label class="input-label">현상금 포인트 (선택)</label>
        <input v-model.number="form.bounty_points" type="number" min="0" placeholder="0" class="input-soft px-3" />
        <p class="text-xs text-ink-muted mt-1">현상금을 설정하면 답변을 더 빨리 받을 수 있습니다. 채택 시 답변자에게 지급됩니다.</p>
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting" class="btn-primary px-6">{{ submitting ? '등록 중...' : '질문 등록' }}</button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
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
