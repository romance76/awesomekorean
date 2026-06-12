<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="video" :size="20" /></span>
      숏츠 업로드
    </h1>
    <div class="card p-5 space-y-4">
      <div>
        <label class="input-label">YouTube URL</label>
        <input v-model="form.video_url" type="url" placeholder="https://youtube.com/shorts/... 또는 https://youtu.be/..."
          @input="parseUrl" class="input-soft px-3" />
      </div>
      <div v-if="preview" class="bg-[#F4F6F8] rounded-xl p-3">
        <img :src="preview" class="w-32 h-56 object-cover rounded-xl mx-auto" />
        <div class="text-center text-xs text-ink-muted mt-2">미리보기</div>
      </div>
      <div>
        <label class="input-label">제목</label>
        <input v-model="form.title" type="text" placeholder="숏츠 제목" class="input-soft px-3" />
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting || !form.video_url" class="btn-primary px-6">{{ submitting ? '업로드 중...' : '업로드' }}</button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
const router = useRouter()
const form = reactive({ title: '', video_url: '' })
const preview = ref(null)
const error = ref('')
const submitting = ref(false)
function parseUrl() {
  const m = form.video_url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|shorts\/))([a-zA-Z0-9_-]+)/)
  if (m) { preview.value = `https://img.youtube.com/vi/${m[1]}/hqdefault.jpg`; if (!form.title) form.title = '숏츠 영상' }
  else preview.value = null
}
async function submit() {
  if (!form.video_url || !form.title) { error.value = 'URL과 제목을 입력해주세요'; return }
  submitting.value = true; error.value = ''
  try { await axios.post('/api/shorts', form); router.push('/shorts') }
  catch (e) { error.value = e.response?.data?.message || '업로드 실패' }
  submitting.value = false
}
</script>
