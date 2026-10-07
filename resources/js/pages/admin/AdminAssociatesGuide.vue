<template>
<div>
  <p class="text-xs text-ink-muted mb-3">회원이 보는 "Amazon Associates 가입 방법" 페이지(<a href="/shopping/guide" target="_blank" class="text-blue-500 underline">/shopping/guide</a>)의 단계별 글과 화면 캡처예요. 단계마다 캡처 이미지를 올리면 글 아래에 크게 보여요. (Amazon 화면은 직접 가입하시면서 캡처해 올려주세요)</p>
  <div v-if="loading" class="text-center py-10 text-ink-muted">로딩중...</div>
  <div v-else class="space-y-3">
    <div v-for="(s, i) in steps" :key="i" class="border border-gray-100 rounded-xl p-3 space-y-2">
      <div class="flex gap-2">
        <input v-model="s.title" class="input-soft flex-1 font-bold" maxlength="120" placeholder="단계 제목" />
        <button @click="move(i, -1)" :disabled="i === 0" class="px-2 text-ink-muted disabled:opacity-30" title="위로">▲</button>
        <button @click="move(i, 1)" :disabled="i === steps.length - 1" class="px-2 text-ink-muted disabled:opacity-30" title="아래로">▼</button>
        <button @click="steps.splice(i, 1)" class="px-2 text-red-500" title="삭제">✕</button>
      </div>
      <textarea v-model="s.body" rows="4" class="input-soft" maxlength="3000" placeholder="설명"></textarea>
      <div class="flex items-center gap-3">
        <img v-if="s.image" :src="s.image" class="w-28 rounded border border-gray-100" />
        <label class="text-xs text-blue-600 font-bold cursor-pointer">
          {{ s.image ? '캡처 바꾸기' : '📷 캡처 이미지 올리기' }}
          <input type="file" accept="image/*" class="hidden" @change="e => upload(s, e)" />
        </label>
        <button v-if="s.image" @click="s.image = ''" class="text-xs text-red-500">이미지 빼기</button>
      </div>
    </div>
    <div class="flex gap-2">
      <button @click="steps.push({ title: '', body: '', image: '' })" class="btn-secondary px-4 py-2 text-sm">+ 단계 추가</button>
      <button @click="save" :disabled="saving" class="btn-primary px-6 py-2 text-sm font-bold disabled:opacity-50">{{ saving ? '저장 중...' : '저장' }}</button>
    </div>
    <p v-if="msg" class="text-sm" :class="ok ? 'text-green-600' : 'text-red-500'">{{ msg }}</p>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const steps = ref([])
const loading = ref(true)
const saving = ref(false)
const msg = ref('')
const ok = ref(true)

function move(i, d) { const a = steps.value; [a[i], a[i + d]] = [a[i + d], a[i]] }
async function upload(s, e) {
  const file = e.target.files?.[0]; e.target.value = ''
  if (!file) return
  const fd = new FormData(); fd.append('image', file)
  try { s.image = (await axios.post('/api/admin/associates-guide/image', fd)).data.data.url } catch (er) { msg.value = er.response?.data?.message || '업로드 실패'; ok.value = false }
}
async function save() {
  saving.value = true; msg.value = ''
  try {
    const { data } = await axios.put('/api/admin/associates-guide', { steps: steps.value })
    steps.value = data.data.steps; msg.value = data.message; ok.value = true
  } catch (e) { msg.value = e.response?.data?.message || '저장 실패'; ok.value = false }
  saving.value = false
}
onMounted(async () => {
  try { steps.value = (await axios.get('/api/shopping/associates-guide')).data.data.steps } catch {}
  loading.value = false
})
</script>
