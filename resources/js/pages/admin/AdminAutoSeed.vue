<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 소통 › 자동 게시</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-emerald-50 text-emerald-600"><AppIcon name="sparkles" :size="18" /></span>
      커뮤니티·Q&A 자동 게시
    </h1>
    <p class="text-xs text-ink-faint mt-0.5">레딧·쿼라에서 한국과 관련된 새 글(한 달 미만)을 한국어로 옮겨, 가상 회원 이름으로 하루에 정해진 개수만큼 무작위 시각에 올립니다. 전체 회원 수가 상한을 넘으면 저절로 멈춰요.</p>
  </div>

  <div v-if="st" class="card p-4 mb-4 border" :class="st.running ? 'border-green-200 bg-green-50/40' : 'border-amber-200 bg-amber-50/50'">
    <div class="flex items-center gap-2 font-bold" :class="st.running ? 'text-green-700' : 'text-amber-800'">
      <AppIcon :name="st.running ? 'check-circle' : 'lock'" :size="16" />
      {{ st.running ? '자동 게시 중' : (st.stopped_reason === 'member_cap' ? '회원 수가 상한을 넘어 멈췄어요' : '꺼져 있어요') }}
    </div>
    <p class="text-xs text-ink-muted mt-1">전체 회원 {{ st.member_count.toLocaleString() }}명 / 상한 {{ st.config.member_cap.toLocaleString() }}명 · 글쓴이로 쓸 가상 회원 {{ st.author_count }}명</p>
  </div>

  <div v-if="st" class="grid grid-cols-2 gap-3 mb-4">
    <div v-for="k in kinds" :key="k.key" class="card p-3">
      <div class="text-xs text-ink-muted">오늘 {{ k.label }}</div>
      <div class="text-xl font-bold text-ink">{{ st.today[k.key]?.done ?? 0 }} / {{ st.today[k.key]?.target ?? 0 }}</div>
      <div class="text-[11px] text-ink-faint">다음 예정 {{ st.today[k.key]?.next_slot ? fmt(st.today[k.key].next_slot) : '—' }}</div>
    </div>
  </div>

  <div v-if="st" class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-2">설정</h2>
    <label class="flex items-center gap-2 text-sm mb-3"><input type="checkbox" v-model="form.enabled" /> 자동 게시 켜기</label>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <label class="text-xs text-ink-muted">하루 최소 개수 (각 게시판)<input type="number" min="0" max="30" v-model.number="form.daily_min" class="input-soft w-full mt-1" /></label>
      <label class="text-xs text-ink-muted">하루 최대 개수 (각 게시판)<input type="number" min="0" max="30" v-model.number="form.daily_max" class="input-soft w-full mt-1" /></label>
      <label class="text-xs text-ink-muted">멈출 전체 회원 수<input type="number" min="0" v-model.number="form.member_cap" class="input-soft w-full mt-1" /></label>
      <label class="text-xs text-ink-muted">올리는 시간대 (애틀랜타)
        <span class="flex items-center gap-1 mt-1"><input type="number" min="0" max="22" v-model.number="form.start_hour" class="input-soft w-full" />~<input type="number" min="1" max="24" v-model.number="form.end_hour" class="input-soft w-full" />시</span>
      </label>
    </div>
    <p class="text-[11px] text-ink-faint mt-2">커뮤니티 게시판과 Q&A에 각각 하루 최소~최대 사이의 개수를 무작위로 정해 올려요. 회원 수가 상한을 <b>넘으면</b> 멈추고, 0이면 상한 없이 계속해요.</p>
    <div class="mt-3 flex items-center gap-3">
      <button class="btn-primary act-btn" :disabled="busy" @click="save">저장</button>
      <span v-if="msg" class="text-sm" :class="msgOk ? 'text-green-600' : 'text-red-500'">{{ msg }}</span>
    </div>
  </div>

  <div v-if="st" class="card p-4">
    <h2 class="font-bold text-ink text-sm mb-2">최근에 올린 글</h2>
    <p v-if="!st.recent.length" class="text-xs text-ink-faint">아직 올린 글이 없어요.</p>
    <ul class="divide-y divide-gray-100">
      <li v-for="(l, i) in st.recent" :key="i" class="py-2 text-sm flex flex-wrap items-center gap-x-2">
        <span class="text-[11px] rounded-full px-2 py-0.5" :class="l.kind === 'qa' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'">{{ l.kind === 'qa' ? 'Q&A' : '커뮤니티' }}</span>
        <a :href="l.path || '#'" target="_blank" class="text-ink font-medium hover:underline">{{ l.title }}</a>
        <span class="text-[11px] text-ink-faint">{{ l.author }} · {{ fmt(l.at) }}</span>
        <a v-if="l.source" :href="l.source" target="_blank" rel="noopener noreferrer" class="text-[11px] text-ink-faint underline">원문</a>
      </li>
    </ul>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const kinds = [{ key: 'community', label: '커뮤니티' }, { key: 'qa', label: 'Q&A' }]
const st = ref(null)
const form = ref({ enabled: true, daily_min: 5, daily_max: 7, member_cap: 3000, start_hour: 8, end_hour: 23 })
const busy = ref(false)
const msg = ref('')
const msgOk = ref(true)

function say(t, ok = true) { msg.value = t; msgOk.value = ok }
function fmt(iso) { try { return new Date(iso).toLocaleString('ko-KR', { month: 'numeric', day: 'numeric', hour: 'numeric', minute: '2-digit' }) } catch { return iso } }
function apply(d) { st.value = d; form.value = { ...d.config } }
async function load() {
  try { const { data } = await axios.get('/api/admin/auto-seed'); apply(data.data) } catch { say('불러오지 못했어요.', false) }
}
async function save() {
  busy.value = true; msg.value = ''
  try { const { data } = await axios.put('/api/admin/auto-seed', form.value); apply(data.data); say('저장했어요.') }
  catch (e) { say(e.response?.data?.message || '저장하지 못했어요.', false) } finally { busy.value = false }
}
onMounted(load)
</script>
