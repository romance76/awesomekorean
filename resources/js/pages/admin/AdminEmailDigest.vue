<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 광고·매출 › 메일 발송</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="mail" :size="18" /></span>
      월요일 정기 소식 메일
    </h1>
    <p class="text-xs text-ink-faint mt-0.5">수신 동의한 회원에게 매주 월요일 오전 9시(받는 사람 현지 시각)에 보내는 소식 메일입니다. 맨 아래에 사이드 광고가 "우리 동네 광고" 띠로 무작위로 들어갑니다.</p>
  </div>

  <!-- 잠금 상태 -->
  <div v-if="st" class="card p-4 mb-4 border" :class="st.live ? 'border-green-200 bg-green-50/40' : 'border-amber-200 bg-amber-50/50'">
    <div class="flex items-center gap-2 font-bold" :class="st.live ? 'text-green-700' : 'text-amber-800'">
      <AppIcon :name="st.live ? 'check-circle' : 'lock'" :size="16" />
      {{ st.live ? '실제 발송이 켜져 있어요' : '실제 발송은 잠겨 있어요' }}
    </div>
    <p v-if="st.lock_reason" class="text-sm text-ink-light mt-1">{{ st.lock_reason }}</p>
    <p class="text-xs text-ink-muted mt-1">보내는 주소 {{ st.from }} · {{ st.schedule }}</p>
  </div>

  <!-- 통계 -->
  <div v-if="st" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="card p-3"><div class="text-xs text-ink-muted">받을 수 있는 회원</div><div class="text-xl font-bold text-ink">{{ st.eligible_total }}</div><div class="text-[11px] text-ink-faint">동의 + 이메일 인증</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">동의한 전체 회원</div><div class="text-xl font-bold text-blue-600">{{ st.consented_total }}</div><div class="text-[11px] text-ink-faint">인증 전 포함</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">이번 주 발송</div><div class="text-xl font-bold text-green-600">{{ st.sent_this_week }}</div><div class="text-[11px] text-ink-faint">{{ st.week_key }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">이번 주 실패</div><div class="text-xl font-bold text-red-600">{{ st.failed_this_week }}</div></div>
  </div>

  <div v-if="st && st.by_state.length" class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-2">주별 받을 수 있는 회원 수</h2>
    <div class="flex flex-wrap gap-2">
      <span v-for="s in st.by_state" :key="s.state" class="text-xs rounded-full bg-gray-100 px-2.5 py-1"><b>{{ s.state }}</b> {{ s.count }}명</span>
    </div>
    <p class="text-[11px] text-ink-faint mt-2">주를 입력하지 않은 회원은 전국 광고만 받아요.</p>
  </div>

  <!-- 우편 주소 + 실제 발송 -->
  <div class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-1">1. 우편 주소 (메일 맨 아래에 표시)</h2>
    <p class="text-xs text-ink-muted mb-2">법적으로 광고 메일에는 우편물을 받을 수 있는 실제 주소가 필요해요. 도로명·번지(또는 사서함 번호), 도시, 주, 우편번호까지 모두 적어 주세요. 예) P.O. Box 000, Suwanee, GA 30024</p>
    <div class="flex gap-2 flex-wrap">
      <input v-model="addr" maxlength="200" placeholder="예) P.O. Box 000, Suwanee, GA 30024" class="input-soft act-input flex-1 min-w-[240px]" />
      <button class="btn-primary act-btn" :disabled="busy" @click="saveAddr">저장</button>
    </div>
    <h2 class="font-bold text-ink text-sm mt-4 mb-1">2. 실제 발송 스위치</h2>
    <p class="text-xs text-ink-muted mb-2">켜면 다음 월요일부터 동의한 회원에게 실제로 발송돼요. 우편 주소가 없으면 켤 수 없어요. 처음엔 아래 시험 발송으로 먼저 확인하세요.</p>
    <button class="act-btn" :class="st && st.live_switch ? 'btn-secondary' : 'btn-primary'" :disabled="busy || !st || (!st.postal_address && !st.live_switch)" @click="toggleLive">
      {{ st && st.live_switch ? '실제 발송 끄기' : '실제 발송 켜기' }}
    </button>
    <span v-if="st && !st.postal_address" class="text-xs text-amber-700 ml-2">우편 주소를 먼저 저장하세요</span>
  </div>

  <!-- 미리보기 + 시험 발송 -->
  <div class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-2">3. 미리보기 · 시험 발송</h2>
    <div class="flex flex-wrap items-center gap-2 mb-3">
      <label class="text-xs text-ink-muted">받는 사람 주</label>
      <input v-model="state" maxlength="2" class="input-soft act-input w-20 uppercase" placeholder="GA" />
      <button class="btn-secondary act-btn" :disabled="busy" @click="loadPreview">미리보기 새로 뽑기</button>
      <span class="text-xs text-ink-faint">누를 때마다 광고가 무작위로 다시 뽑혀요 (광고 {{ previewAds }}개)</span>
    </div>
    <iframe v-if="preview" :srcdoc="preview" class="w-full rounded-xl border border-gray-200 bg-white" style="height:640px" title="메일 미리보기"></iframe>
    <div class="flex flex-wrap items-center gap-2 mt-3">
      <input v-model="testEmail" type="email" placeholder="시험 메일 받을 이메일" class="input-soft act-input flex-1 min-w-[220px]" />
      <button class="btn-primary act-btn" :disabled="busy || !testEmail" @click="sendTest">시험 메일 보내기</button>
    </div>
    <p v-if="msg" class="text-sm mt-2" :class="msgOk ? 'text-green-600' : 'text-red-500'">{{ msg }}</p>
    <p class="text-[11px] text-ink-faint mt-1">시험 메일은 입력한 주소 한 곳으로만 가고, 광고 노출 집계·발송 기록에는 반영되지 않아요.</p>
  </div>

  <!-- 광고별 집계 -->
  <div class="card p-4">
    <h2 class="font-bold text-ink text-sm mb-2">광고 띠 집계 (최근 28일)</h2>
    <p v-if="!st || !st.ad_stats.length" class="text-sm text-ink-muted">아직 실제로 보낸 메일이 없어서 집계가 없어요.</p>
    <table v-else class="w-full text-sm">
      <thead><tr class="text-xs text-ink-muted text-left"><th class="py-1">광고</th><th class="py-1 text-right">실린 메일 수</th><th class="py-1 text-right">클릭</th></tr></thead>
      <tbody><tr v-for="a in st.ad_stats" :key="a.id" class="border-t border-gray-50"><td class="py-1.5">{{ a.title }}</td><td class="py-1.5 text-right">{{ a.sent }}</td><td class="py-1.5 text-right">{{ a.clicks }}</td></tr></tbody>
    </table>
    <p class="text-[11px] text-ink-faint mt-2">광고주에게는 이 숫자를 공개하지 않아요(관리자만 확인). 받는 사람 정보는 기록하지 않아요.</p>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const st = ref(null)
const addr = ref('')
const busy = ref(false)
const state = ref('GA')
const preview = ref('')
const previewAds = ref(0)
const testEmail = ref('')
const msg = ref('')
const msgOk = ref(true)

function say(text, ok = true) { msg.value = text; msgOk.value = ok }
async function load() {
  try { const { data } = await axios.get('/api/admin/email-digest'); st.value = data.data; addr.value = data.data.postal_address || '' } catch { say('상태를 불러오지 못했어요.', false) }
}
async function saveAddr() {
  busy.value = true
  try { const { data } = await axios.put('/api/admin/email-digest/address', { postal_address: addr.value }); st.value = data.data; say('우편 주소를 저장했어요.') }
  catch (e) { say(e.response?.data?.message || '저장하지 못했어요.', false) } finally { busy.value = false }
}
async function toggleLive() {
  const turnOn = !st.value.live_switch
  if (turnOn && !window.confirm('실제 발송을 켜면 다음 월요일부터 동의한 회원에게 메일이 나가요. 켤까요?')) return
  busy.value = true
  try { const { data } = await axios.put('/api/admin/email-digest/live', { live: turnOn }); st.value = data.data; say(turnOn ? '실제 발송을 켰어요.' : '실제 발송을 껐어요.') }
  catch (e) { say(e.response?.data?.message || '바꾸지 못했어요.', false) } finally { busy.value = false }
}
async function loadPreview() {
  busy.value = true
  try { const { data } = await axios.get('/api/admin/email-digest/preview', { params: { state: state.value.toUpperCase() } }); preview.value = data.html; previewAds.value = data.ads }
  catch { say('미리보기를 불러오지 못했어요.', false) } finally { busy.value = false }
}
async function sendTest() {
  busy.value = true; msg.value = ''
  try { const { data } = await axios.post('/api/admin/email-digest/test', { email: testEmail.value, state: state.value.toUpperCase() }); say(data.message) }
  catch (e) { say(e.response?.data?.message || '시험 메일을 보내지 못했어요.', false) } finally { busy.value = false }
}
onMounted(() => { load(); loadPreview() })
</script>
