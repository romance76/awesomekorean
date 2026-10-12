<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="[{ label: editId ? '글 수정' : '새 정보 올리기' }]" />

    <form @submit.prevent="submit" class="card p-5 md:p-6 space-y-4">
      <label class="block">
        <span class="input-label">카페</span>
        <select v-model="cafe" :disabled="!!editId" class="input-soft">
          <option value="" disabled>어느 카페에 올릴까요?</option>
          <option v-for="c in cafes" :key="c.slug" :value="c.slug">{{ c.icon }} {{ c.name }}</option>
        </select>
      </label>
      <div>
        <span class="input-label">글 종류</span>
        <div class="grid grid-cols-3 gap-1 bg-gray-100 p-1 rounded-xl max-w-sm">
          <button v-for="k in kinds" :key="k.v" type="button" @click="kind = k.v" class="min-h-[40px] rounded-lg text-sm font-bold"
            :class="kind === k.v ? 'bg-white text-emerald-700 shadow-sm' : 'text-ink-muted'">{{ k.l }}</button>
        </div>
      </div>
      <label class="block">
        <span class="input-label">제목</span>
        <input v-model="title" maxlength="200" class="input-soft" placeholder="예: 코스트코 멤버십, 1년 써보니 아낀 금액" />
      </label>
      <label class="block">
        <span class="input-label">내용</span>
        <textarea v-model="body" rows="12" class="input-soft" placeholder="경험·정보·질문을 자유롭게 적어 주세요. 빈 줄로 문단을 나눠요."></textarea>
      </label>
      <p class="text-xs text-ink-muted leading-relaxed">광고·홍보·특정 상품 권유, 개인정보(계좌·전화번호 등)는 올리지 마세요. {{ DISCLAIMER }}</p>
      <p v-if="error" class="text-sm text-red-600 font-bold">{{ error }}</p>
      <div class="flex gap-2 justify-end">
        <button type="button" @click="router.back()" class="btn-secondary px-5 py-2.5">취소</button>
        <button type="submit" :disabled="busy" class="btn-primary px-6 py-2.5 disabled:opacity-50">{{ busy ? '저장 중...' : (editId ? '수정하기' : '올리기') }}</button>
      </div>
    </form>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'
import MoneyHead from '../../components/money/MoneyHead.vue'
import { textToHtml, htmlToText, DISCLAIMER } from '../../components/money/moneyText'

const route = useRoute(); const router = useRouter(); const site = useSiteStore()
const cafes = ref([]); const cafe = ref(String(route.query.cafe || ''))
const kinds = [{ v: 'info', l: '정보' }, { v: 'question', l: '질문' }, { v: 'talk', l: '소통' }]
const kind = ref('info'); const title = ref(''); const body = ref('')
const editId = ref(route.query.id ? Number(route.query.id) : null)
const busy = ref(false); const error = ref('')

onMounted(async () => {
  try { const { data } = await axios.get('/api/money/hub'); cafes.value = data.data?.cafes || [] } catch {}
  if (editId.value) {
    try {
      const { data } = await axios.get(`/api/money/posts/${editId.value}`)
      const p = data.data
      cafe.value = p.cafe?.slug || ''; kind.value = p.kind; title.value = p.title; body.value = htmlToText(p.content)
    } catch { error.value = '글을 불러오지 못했어요.' }
  }
})

async function submit() {
  error.value = ''
  if (!cafe.value) { error.value = '어느 카페에 올릴지 골라 주세요.'; return }
  if (!title.value.trim() || !body.value.trim()) { error.value = '제목과 내용을 적어 주세요.'; return }
  busy.value = true
  try {
    const payload = { cafe: cafe.value, kind: kind.value, title: title.value.trim(), content: textToHtml(body.value) }
    const { data } = editId.value
      ? await axios.put(`/api/money/posts/${editId.value}`, payload)
      : await axios.post('/api/money/posts', payload)
    site.toast?.(editId.value ? '수정했어요' : '올렸어요', 'success')
    router.replace(`/money/post/${data.data.id}`)
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = (errs ? Object.values(errs).flat().join(' ') : e.response?.data?.message) || '저장하지 못했어요.'
  }
  busy.value = false
}
</script>
