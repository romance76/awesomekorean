<template>
<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between gap-3 mb-4">
    <h1 class="text-lg font-extrabold text-ink flex items-center gap-2"><AppIcon name="video" :size="20" />한인 유튜버</h1>
    <RouterLink to="/youtubers" target="_blank" class="text-[12.5px] font-semibold text-amber-700 hover:underline">사이트에서 보기 ↗</RouterLink>
  </div>

  <!-- 직접 등록 -->
  <div class="card p-4 mb-4">
    <div class="text-[13px] font-bold text-ink mb-2">채널 직접 등록 <span class="font-normal text-ink-muted">(바로 공개됩니다)</span></div>
    <div class="flex flex-col sm:flex-row gap-2">
      <input v-model.trim="addUrl" type="text" class="input-soft flex-1" placeholder="https://www.youtube.com/@채널이름" />
      <select v-model="addCat" class="input-soft sm:!w-40"><option v-for="c in categories" :key="c.key" :value="c.key">{{ c.label }}</option></select>
      <button @click="addChannel" :disabled="busy" class="btn-primary px-5 py-2 text-sm">등록</button>
    </div>
  </div>

  <div v-if="msg" class="mb-3 text-[13px] rounded-lg px-3 py-2" :class="ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">{{ msg }}</div>

  <!-- 상태 탭·검색 -->
  <div class="flex flex-wrap items-center gap-2 mb-3">
    <button v-for="t in statusTabs" :key="t.key" @click="setStatus(t.key)" class="px-3.5 py-1.5 rounded-full text-[13px] border transition-colors"
      :class="status === t.key ? 'bg-amber-500 text-white border-amber-500 font-bold' : 'bg-white text-ink-light border-gray-200'">{{ t.label }}<span v-if="counts[t.key] != null" class="ml-1 opacity-80">{{ counts[t.key] }}</span></button>
    <input v-model="search" @keyup.enter="load(1)" type="text" placeholder="채널 검색" class="input-soft !w-48 ml-auto" />
  </div>

  <div class="card divide-y divide-gray-50">
    <div v-if="!items.length" class="py-10 text-center text-sm text-ink-muted">채널이 없어요.</div>
    <div v-for="c in items" :key="c.id" class="p-3 flex flex-col lg:flex-row lg:items-center gap-3">
      <div class="flex items-center gap-3 min-w-0 lg:w-80 shrink-0">
        <img v-if="c.thumbnail_url" :src="c.thumbnail_url" alt="" class="w-11 h-11 rounded-full object-cover bg-surface" @error="e => e.target.style.display = 'none'" />
        <div class="min-w-0">
          <a :href="`https://www.youtube.com/channel/${c.channel_id}`" target="_blank" rel="noopener noreferrer" class="text-[14px] font-bold text-ink hover:text-amber-700 truncate block">{{ c.title }}</a>
          <div class="text-[11.5px] text-ink-muted truncate">{{ c.handle || c.channel_id }} · 구독 {{ Number(c.subscriber_count).toLocaleString() }} · 영상 {{ c.videos }}개</div>
          <div v-if="c.intro" class="text-[11.5px] text-ink-light truncate">{{ c.intro }}</div>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 flex-1">
        <span class="text-[11.5px] font-bold px-2 py-0.5 rounded-full" :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span>
        <span v-if="c.verified" class="text-[11.5px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-700">주인 인증됨 (회원 #{{ c.owner_user_id }})</span>
        <select :value="c.category" @change="e => update(c, { category: e.target.value })" class="input-soft !w-auto !py-1 text-[12.5px]">
          <option v-for="k in categories" :key="k.key" :value="k.key">{{ k.label }}</option>
        </select>
        <label class="inline-flex items-center gap-1 text-[12.5px] text-ink-light cursor-pointer">
          <input type="checkbox" :checked="c.is_featured" @change="e => update(c, { is_featured: e.target.checked })" />추천
        </label>
        <span v-if="c.last_synced_at" class="text-[11px] text-ink-faint">갱신 {{ String(c.last_synced_at).slice(5, 16).replace('T', ' ') }}</span>
        <span v-if="c.reject_reason" class="text-[11.5px] text-red-500">거절: {{ c.reject_reason }}</span>
      </div>

      <div class="flex items-center gap-1.5 shrink-0">
        <button v-if="c.status !== 'approved'" @click="act(c, 'approve')" class="px-3 py-1.5 rounded-lg text-[12.5px] font-bold bg-green-500 text-white hover:bg-green-600">승인</button>
        <button v-if="c.status !== 'rejected'" @click="reject(c)" class="px-3 py-1.5 rounded-lg text-[12.5px] font-bold bg-gray-100 text-ink-light hover:bg-gray-200">거절</button>
        <button @click="act(c, 'resync')" class="px-3 py-1.5 rounded-lg text-[12.5px] font-bold bg-gray-100 text-ink-light hover:bg-gray-200" title="유튜브에서 정보·영상 새로 읽기">새로 읽기</button>
        <button v-if="c.verified" @click="unverify(c)" class="px-3 py-1.5 rounded-lg text-[12.5px] font-bold bg-gray-100 text-ink-light hover:bg-gray-200" title="잘못 인증된 경우 주인 인증을 풀어요">인증 해제</button>
        <button @click="remove(c)" class="px-3 py-1.5 rounded-lg text-[12.5px] font-bold text-red-500 hover:bg-red-50">삭제</button>
      </div>
    </div>
  </div>

  <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
    <button :disabled="page <= 1" @click="load(page - 1)" class="btn-secondary px-4 py-1.5 text-sm">이전</button>
    <span class="text-sm text-ink-muted self-center">{{ page }} / {{ lastPage }}</span>
    <button :disabled="page >= lastPage" @click="load(page + 1)" class="btn-secondary px-4 py-1.5 text-sm">다음</button>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const items = ref([])
const counts = ref({})
const categories = ref([])
const status = ref('pending')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const busy = ref(false)
const msg = ref('')
const ok = ref(true)
const addUrl = ref('')
const addCat = ref('life')

const statusTabs = [{ key: 'pending', label: '승인 대기' }, { key: 'approved', label: '공개 중' }, { key: 'rejected', label: '거절됨' }, { key: '', label: '전체' }]
function statusLabel(s) { return s === 'approved' ? '공개 중' : s === 'pending' ? '승인 대기' : '거절됨' }
function statusClass(s) { return s === 'approved' ? 'bg-green-50 text-green-700' : s === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600' }
function say(text, good = true) { msg.value = text; ok.value = good }

async function load(p = 1) {
  try {
    const { data } = await axios.get('/api/admin/youtubers', { params: { status: status.value || undefined, q: search.value || undefined, page: p } })
    const d = data.data || {}
    items.value = d.items || []
    counts.value = { ...(d.counts || {}), '': (d.counts?.pending || 0) + (d.counts?.approved || 0) + (d.counts?.rejected || 0) }
    if (d.categories?.length) categories.value = d.categories
    page.value = d.meta?.current_page || 1
    lastPage.value = d.meta?.last_page || 1
  } catch (e) { say(e.response?.data?.message || '목록을 불러오지 못했어요.', false) }
}
function setStatus(k) { status.value = k; load(1) }

async function call(fn, okText) {
  busy.value = true
  try { const { data } = await fn(); say(data.message || okText); await load(page.value) }
  catch (e) { say(e.response?.data?.message || '처리하지 못했어요.', false) }
  finally { busy.value = false }
}
function addChannel() {
  if (!addUrl.value) return say('채널 주소를 입력해 주세요.', false)
  call(() => axios.post('/api/admin/youtubers', { url: addUrl.value, category: addCat.value }), '등록했어요.').then(() => { if (ok.value) addUrl.value = '' })
}
function act(c, what) { call(() => axios.post(`/api/admin/youtubers/${c.id}/${what}`), '처리했어요.') }
function reject(c) {
  const reason = window.prompt('거절 사유를 적어 주세요 (신청자에게 보여요, 비워도 돼요)', '')
  if (reason === null) return
  call(() => axios.post(`/api/admin/youtubers/${c.id}/reject`, { reason }), '거절했어요.')
}
function unverify(c) {
  if (!window.confirm(`'${c.title}' 채널의 주인 인증을 해제할까요?`)) return
  call(() => axios.post(`/api/admin/youtubers/${c.id}/unverify`), '해제했어요.')
}
function update(c, body) { call(() => axios.put(`/api/admin/youtubers/${c.id}`, body), '저장했어요.') }
function remove(c) {
  if (!window.confirm(`'${c.title}' 채널과 저장된 영상 목록을 삭제할까요?`)) return
  call(() => axios.delete(`/api/admin/youtubers/${c.id}`), '삭제했어요.')
}
onMounted(() => load(1))
</script>
