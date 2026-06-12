<template>
<div>
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
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AdminChats from './AdminChats.vue'
import AdminCalls from './AdminCalls.vue'
import AppIcon from '../../components/AppIcon.vue'

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
  try {
    await axios.post('/api/admin/board-manager/community/settings', { settings: payload })
    alert('저장되었습니다')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

onMounted(() => { loadStats(); loadSettings() })
</script>
