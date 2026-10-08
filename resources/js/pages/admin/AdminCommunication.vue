<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <div class="grid grid-cols-3 gap-2">
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">채팅방</div><div class="text-[20px] font-bold text-ink tabular-nums">{{ chatStats.total || 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">오늘 메시지</div><div class="text-[20px] font-bold text-blue-600 tabular-nums">{{ chatStats.today || 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">오늘 통화</div><div class="text-[20px] font-bold text-purple-600 tabular-nums">{{ callStats.today || 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">전체 통화</div><div class="text-[20px] font-bold text-ink tabular-nums">{{ callStats.total || 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">통화 성공</div><div class="text-[20px] font-bold text-green-600 tabular-nums">{{ callStats.answered || 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">부재중</div><div class="text-[20px] font-bold text-red-600 tabular-nums">{{ callStats.missed || 0 }}</div></div>
  </div>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="소통 메뉴">
    <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key" :aria-pressed="activeTab === t.key"
      class="shrink-0 min-h-[44px] px-5 rounded-full border text-[15px]" :class="activeTab === t.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ t.label }}</button>
  </div>
  <AdminChats v-if="activeTab === 'chat'" />
  <AdminCalls v-else-if="activeTab === 'calls'" />
  <div v-else class="space-y-2">
    <p class="text-[13px] text-ink-muted px-0.5">채팅/통화 기본 설정이에요.</p>
    <template v-for="(def, key) in settingSchema" :key="key">
      <button v-if="def.type === 'bool'" type="button" @click="settings[key] = !settings[key]" role="switch" :aria-checked="!!settings[key]"
        class="w-full min-h-[56px] rounded-2xl border px-4 flex items-center justify-between gap-3 text-left" :class="settings[key] ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200'">
        <span class="min-w-0"><span class="block text-[15px] font-bold text-ink">{{ def.label }}</span></span>
        <span class="shrink-0 text-[14px] font-bold" :class="settings[key] ? 'text-green-700' : 'text-ink-muted'">{{ settings[key] ? '켜짐' : '꺼짐' }}</span>
      </button>
      <div v-else class="bg-white border border-gray-100 rounded-2xl p-3.5">
        <label class="block text-[15px] font-bold text-ink mb-1" :for="'cs-' + key">{{ def.label }}</label>
        <input v-if="def.type === 'number'" :id="'cs-' + key" type="number" inputmode="numeric" v-model.number="settings[key]" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
        <input v-else :id="'cs-' + key" type="text" v-model="settings[key]" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
      </div>
    </template>
    <button @click="saveSettings" :disabled="saving" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ saving ? '저장 중...' : '설정 저장' }}</button>
  </div>
  <Teleport to="body">
    <div v-if="toast && isMobile" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 회원 › 소통 관리</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="message-circle" :size="20" /></span>
      소통 관리
    </h1>
    <p class="text-xs text-ink-muted mt-0.5">채팅방과 통화 내역을 한 페이지에서 관리합니다</p>
  </div>

  <!-- 요약 카드 -->
  <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-4">
    <div class="card p-3">
      <div class="flex items-center gap-1 text-xs text-ink-muted"><AppIcon name="message-circle" :size="11" /> 채팅방</div>
      <div class="text-xl font-bold text-ink">{{ chatStats.total || 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">오늘 메시지</div>
      <div class="text-xl font-bold text-blue-600">{{ chatStats.today || 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="flex items-center gap-1 text-xs text-ink-muted"><AppIcon name="phone" :size="11" /> 전체 통화</div>
      <div class="text-xl font-bold text-ink">{{ callStats.total || 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">통화 성공</div>
      <div class="text-xl font-bold text-green-600">{{ callStats.answered || 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">부재중</div>
      <div class="text-xl font-bold text-red-600">{{ callStats.missed || 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">오늘 통화</div>
      <div class="text-xl font-bold text-purple-600">{{ callStats.today || 0 }}</div>
    </div>
  </div>

  <!-- 탭 -->
  <div class="bg-white rounded-t-2xl shadow-card overflow-hidden">
    <div class="flex overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab=t.key"
        class="inline-flex items-center gap-1.5 px-4 py-3 text-sm whitespace-nowrap border-b-2 transition-colors"
        :class="activeTab===t.key ? 'border-amber-500 text-amber-700 font-bold bg-amber-50' : 'border-transparent text-ink-muted hover:text-ink'">
        <AppIcon :name="t.icon" :size="14" /> {{ t.label }}
      </button>
    </div>
  </div>

  <div class="bg-white rounded-b-2xl shadow-card border-t border-gray-50 p-4 min-h-[500px]">
    <!-- 💬 채팅 -->
    <div v-if="activeTab==='chat'">
      <AdminChats />
    </div>

    <!-- 📞 통화 -->
    <div v-else-if="activeTab==='calls'">
      <AdminCalls />
    </div>

    <!-- ⚙️ 설정 -->
    <div v-else-if="activeTab==='set'">
      <div class="text-sm text-ink-light mb-3">채팅/통화 기본 설정</div>
      <div class="space-y-3">
        <div v-for="(def, key) in settingSchema" :key="key" class="flex items-center gap-3 border-b border-gray-50 pb-2">
          <label class="text-sm flex-1">
            <div class="font-medium text-ink">{{ def.label }}</div>
            <div class="text-[11px] text-ink-faint">comm.{{ key }}</div>
          </label>
          <template v-if="def.type==='bool'">
            <input type="checkbox" v-model="settings[key]" class="w-4 h-4 accent-amber-500" />
          </template>
          <template v-else-if="def.type==='number'">
            <input type="number" v-model.number="settings[key]" class="input-soft !w-28 !px-2 !py-1 !text-sm" />
          </template>
          <template v-else>
            <input type="text" v-model="settings[key]" class="input-soft !w-56 !px-2 !py-1 !text-sm" />
          </template>
        </div>
      </div>
      <div class="mt-4 text-right">
        <button @click="saveSettings" class="btn-primary !px-4 !py-2">저장</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AdminChats from './AdminChats.vue'
import AdminCalls from './AdminCalls.vue'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 칩 탭 + 큰 스위치 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
onBeforeUnmount(() => clearTimeout(toastTimer))
const saving = ref(false)
const activeTab = ref('chat')
const tabs = [
  { key: 'chat',  icon: 'message-circle', label: '채팅방' },
  { key: 'calls', icon: 'phone',          label: '통화내역' },
  { key: 'set',   icon: 'settings',       label: '설정' },
]

const chatStats = ref({})
const callStats = ref({})
const settings = ref({})

const settingSchema = {
  chat_enabled:           { label: '채팅 기능 활성화',        type: 'bool',    default: true },
  call_enabled:           { label: '통화 기능 활성화',        type: 'bool',    default: true },
  allow_group_chat:       { label: '그룹 채팅 허용',          type: 'bool',    default: true },
  max_room_members:       { label: '채팅방 최대 인원',        type: 'number',  default: 100 },
  chat_archive_days:      { label: '메시지 보관 (일)',        type: 'number',  default: 0 },
  call_max_duration_min:  { label: '통화 최대 (분, 0=무제한)', type: 'number',  default: 60 },
  call_require_friend:    { label: '친구만 통화 가능',        type: 'bool',    default: false },
  block_spam_auto:        { label: '스팸 자동 차단 (AI)',     type: 'bool',    default: true },
}

async function loadStats() {
  try { const { data } = await axios.get('/api/admin/chat-stats'); chatStats.value = data.data || data || {} } catch {}
  try { const { data } = await axios.get('/api/admin/call-stats'); callStats.value = data.data || {} } catch {}
}

async function loadSettings() {
  try {
    const { data } = await axios.get('/api/admin/settings', { params: { prefix: 'comm.' } })
    const map = {}
    const items = data.data || []
    items.forEach(s => { map[s.key?.replace('comm.', '')] = s.value })
    const defaults = {}
    Object.keys(settingSchema).forEach(k => {
      let v = map[k]
      if (v === undefined) v = settingSchema[k].default
      if (settingSchema[k].type === 'bool') v = v === true || v === 'true' || v === '1' || v === 1
      if (settingSchema[k].type === 'number') v = Number(v || 0)
      defaults[k] = v
    })
    settings.value = defaults
  } catch {}
}

async function saveSettings() {
  const payload = {}
  Object.keys(settings.value).forEach(k => { payload[`comm.${k}`] = settings.value[k] })
  saving.value = true
  try {
    await axios.post('/api/admin/board-manager/community/settings', { settings: payload })
    if (isMobile.value) say('저장했어요'); else alert('저장되었습니다')
  } catch (e) { if (isMobile.value) say(e.response?.data?.message || '저장하지 못했어요', true); else alert(e.response?.data?.message || '저장 실패') }
  saving.value = false
}

onMounted(() => { loadStats(); loadSettings() })
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
