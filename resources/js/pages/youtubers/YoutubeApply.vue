<template>
<div class="min-h-screen">
  <div class="max-w-xl mx-auto px-4 py-5">
    <RouterLink to="/youtubers" class="inline-flex items-center gap-1 text-[13px] font-semibold text-ink-muted hover:text-amber-700 mb-3">← 한인 유튜버</RouterLink>
    <h1 class="text-xl font-extrabold text-ink mb-1">내 채널 등록</h1>
    <p class="text-[13.5px] text-ink-light mb-5">유튜브 채널 주소를 알려 주시면 확인 후 한인 유튜버 목록에 공개돼요. 등록은 무료예요. 영상과 쇼츠는 유튜브에서 자동으로 가져옵니다.</p>

    <form class="card p-5 space-y-4" @submit.prevent="submit">
      <div>
        <label class="input-label">채널 주소 <span class="text-red-500">*</span></label>
        <input v-model.trim="url" type="text" class="input-soft w-full" placeholder="https://www.youtube.com/@채널이름" maxlength="200" />
        <p class="mt-1 text-[12px] text-ink-muted">@이름이 들어간 주소나 채널 주소를 붙여 넣어 주세요. (영상 주소는 안 돼요)</p>
      </div>
      <div>
        <label class="input-label">분야 <span class="text-red-500">*</span></label>
        <div class="flex flex-wrap gap-2">
          <button v-for="c in categories" :key="c.key" type="button" @click="category = c.key" class="px-3.5 py-1.5 rounded-full text-[13px] border transition-colors"
            :class="category === c.key ? 'bg-amber-500 text-white border-amber-500 font-bold' : 'bg-white text-ink-light border-gray-200'">{{ c.label }}</button>
        </div>
      </div>
      <div>
        <label class="input-label">한 줄 소개 <span class="text-ink-faint font-normal">(선택)</span></label>
        <input v-model.trim="intro" type="text" class="input-soft w-full" placeholder="어떤 영상을 만드는 채널인가요?" maxlength="300" />
      </div>

      <div v-if="msg" class="text-[13px] rounded-lg px-3 py-2" :class="ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">{{ msg }}</div>
      <button type="submit" :disabled="busy" class="btn-primary w-full py-2.5 text-sm">{{ busy ? '확인 중...' : '신청하기' }}</button>
      <p class="text-[12px] text-ink-muted">신청에는 로그인과 이메일 인증이 필요해요. 한인 대상이 아니거나 부적절한 채널은 공개되지 않을 수 있어요.</p>
    </form>

    <section v-if="mine.length" class="mt-6">
      <h2 class="text-sm font-extrabold text-ink mb-2">내 신청 내역</h2>
      <div class="card divide-y divide-gray-50">
        <div v-for="m in mine" :key="m.id" class="p-3 flex items-center gap-3">
          <img v-if="m.thumbnail_url" :src="m.thumbnail_url" alt="" class="w-9 h-9 rounded-full object-cover bg-surface" @error="e => e.target.style.display = 'none'" />
          <div class="min-w-0 flex-1">
            <div class="text-[13.5px] font-semibold text-ink truncate">{{ m.title }}</div>
            <div v-if="m.status === 'rejected' && m.reject_reason" class="text-[11.5px] text-red-500">{{ m.reject_reason }}</div>
          </div>
          <RouterLink v-if="m.status === 'approved'" :to="`/youtubers/${m.id}`" class="text-[12px] font-bold text-green-700">공개 중 ›</RouterLink>
          <span v-else class="text-[12px] font-bold" :class="m.status === 'pending' ? 'text-amber-600' : 'text-red-500'">{{ m.status === 'pending' ? '검토 중' : '거절됨' }}</span>
        </div>
      </div>
    </section>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const categories = ref([
  { key: 'life', label: '생활·이민' }, { key: 'food', label: '먹방·맛집' }, { key: 'family', label: '육아·교육' }, { key: 'money', label: '재테크·세금' },
  { key: 'travel', label: '여행' }, { key: 'beauty', label: '뷰티·패션' }, { key: 'vlog', label: '일상 vlog' }, { key: 'etc', label: '기타' },
])
const url = ref('')
const category = ref('')
const intro = ref('')
const busy = ref(false)
const msg = ref('')
const ok = ref(false)
const mine = ref([])

async function loadMine() {
  try { const { data } = await axios.get('/api/youtubers/mine'); mine.value = data.data || [] } catch { /* 비로그인이면 비워 둠 */ }
}
async function submit() {
  msg.value = ''
  if (!url.value) { ok.value = false; msg.value = '채널 주소를 입력해 주세요.'; return }
  if (!category.value) { ok.value = false; msg.value = '분야를 골라 주세요.'; return }
  busy.value = true
  try {
    const { data } = await axios.post('/api/youtubers', { url: url.value, category: category.value, intro: intro.value || null })
    ok.value = true
    msg.value = data.message || '신청이 접수됐어요.'
    url.value = ''; intro.value = ''; category.value = ''
    loadMine()
  } catch (e) {
    ok.value = false
    msg.value = e.response?.data?.message || '신청하지 못했어요. 잠시 후 다시 시도해 주세요.'
  } finally { busy.value = false }
}
onMounted(loadMine)
</script>
