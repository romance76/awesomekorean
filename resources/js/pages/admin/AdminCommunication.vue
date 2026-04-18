<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-gray-500">관리자 › 회원 › 소통 관리</div>
    <h1 class="text-2xl font-black text-gray-800 mt-1">💬 소통 관리</h1>
    <p class="text-xs text-gray-400 mt-0.5">채팅방과 통화 내역을 한 페이지에서 관리합니다</p>
  </div>

  <!-- 요약 카드 -->
  <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-4">
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">💬 채팅방</div>
      <div class="text-xl font-bold">{{ chatStats.total || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">오늘 메시지</div>
      <div class="text-xl font-bold text-blue-600">{{ chatStats.today || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">📞 전체 통화</div>
      <div class="text-xl font-bold">{{ callStats.total || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">통화 성공</div>
      <div class="text-xl font-bold text-green-600">{{ callStats.answered || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">부재중</div>
      <div class="text-xl font-bold text-red-600">{{ callStats.missed || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">오늘 통화</div>
      <div class="text-xl font-bold text-purple-600">{{ callStats.today || 0 }}</div>
    </div>
  </div>

  <!-- 탭 -->
  <div class="bg-white rounded-t-lg border border-b-0">
    <div class="flex overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab=t.key"
        class="px-4 py-3 text-sm whitespace-nowrap border-b-2 transition"
        :class="activeTab===t.key ? 'border-amber-500 text-amber-700 font-bold bg-amber-50' : 'border-transparent text-gray-500 hover:text-gray-800'">
        {{ t.label }}
      </button>
    </div>
  </div>

  <div class="bg-white rounded-b-lg border border-t-0 p-4 min-h-[500px]">
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
      <div class="text-sm text-gray-600 mb-3">채팅/통화 기본 설정</div>
      <div class="space-y-3">
        <div v-for="(def, key) in settingSchema" :key="key" class="flex items-center gap-3 border-b pb-2">
          <label class="text-sm flex-1">
            <div class="font-medium text-gray-800">{{ def.label }}</div>
            <div class="text-[10px] text-gray-400">comm.{{ key }}</div>
          </label>
          <template v-if="def.type==='bool'">
            <input type="checkbox" v-model="settings[key]" class="w-4 h-4" />
          </template>
          <template v-else-if="def.type==='number'">
            <input type="number" v-model.number="settings[key]" class="border rounded px-2 py-1 text-sm w-28" />
          </template>
          <template v-else>
            <input type="text" v-model="settings[key]" class="border rounded px-2 py-1 text-sm w-56" />
          </template>
        </div>
      </div>
      <div class="mt-4 text-right">
        <button @click="saveSettings" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded text-sm">저장</button>
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

const activeTab = ref('chat')
const tabs = [
  { key: 'chat',  label: '💬 채팅방' },
  { key: 'calls', label: '📞 통화내역' },
  { key: 'set',   label: '⚙️ 설정' },
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
