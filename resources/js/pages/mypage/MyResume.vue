<template>
  <div class="bg-white rounded-xl shadow-sm p-5">
    <h3 class="font-bold mb-4">📄 이력서</h3>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else class="space-y-3">
      <label class="block">
        <span class="text-xs text-gray-500">제목</span>
        <input v-model="form.title" class="w-full border rounded px-3 py-2 mt-1 text-sm" placeholder="예: 시니어 소프트웨어 개발자" />
      </label>
      <label class="block">
        <span class="text-xs text-gray-500">연락 이메일</span>
        <input v-model="form.email" type="email" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
      </label>
      <label class="block">
        <span class="text-xs text-gray-500">연락 전화</span>
        <input v-model="form.phone" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
      </label>
      <label class="block">
        <span class="text-xs text-gray-500">희망 분야·기술</span>
        <input v-model="form.skills" class="w-full border rounded px-3 py-2 mt-1 text-sm" placeholder="예: Python, React, AWS" />
      </label>
      <label class="block">
        <span class="text-xs text-gray-500">경력 (요약)</span>
        <textarea v-model="form.experience" rows="4" class="w-full border rounded px-3 py-2 mt-1 text-sm"></textarea>
      </label>
      <label class="block">
        <span class="text-xs text-gray-500">학력</span>
        <textarea v-model="form.education" rows="2" class="w-full border rounded px-3 py-2 mt-1 text-sm"></textarea>
      </label>
      <label class="block">
        <span class="text-xs text-gray-500">자기소개</span>
        <textarea v-model="form.introduction" rows="5" class="w-full border rounded px-3 py-2 mt-1 text-sm"></textarea>
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" v-model="form.is_public" />
        <span>공개 (구인자 검색 가능)</span>
      </label>
      <div class="flex justify-end pt-2">
        <button @click="save" :disabled="saving" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          {{ saving ? '저장 중...' : '💾 저장' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'
const site = useSiteStore()
const form = reactive({ title: '', email: '', phone: '', skills: '', experience: '', education: '', introduction: '', is_public: false })
const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/my-resume').catch(() => ({ data: { data: null } }))
    const r = data?.data
    if (r) Object.keys(form).forEach(k => { if (r[k] !== undefined && r[k] !== null) form[k] = r[k] })
  } finally { loading.value = false }
})

async function save() {
  saving.value = true
  try {
    await axios.post('/api/resumes', form)
    site.toast('이력서가 저장되었습니다', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '저장 실패', 'error')
  } finally { saving.value = false }
}
</script>
