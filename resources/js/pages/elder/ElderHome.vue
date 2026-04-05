<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-5 rounded-2xl">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-black">💙 노인 안심 서비스</h1>
          <p class="text-sm opacity-80 mt-0.5">매일 체크인으로 보호자에게 안전 알림</p>
        </div>
      </div>
    </div>
    </div>

    <div class="max-w-lg mx-auto px-4 mt-6 space-y-5">
      <!-- Card 1: Today Checkin -->
      <div class="bg-white rounded-2xl shadow-md p-6">
        <div class="flex items-center gap-4 mb-3">
          <span class="text-5xl">{{ todayCheckedIn ? '✅' : '⬜' }}</span>
          <div>
            <h2 class="text-xl font-bold text-gray-800">오늘 체크인</h2>
            <p class="text-base text-gray-500">매일 체크인하면 포인트 5점을 드려요</p>
          </div>
        </div>
        <div v-if="todayCheckedIn" class="mb-3 text-center">
          <p class="text-green-600 font-bold text-lg">✅ 오늘 체크인 완료!</p>
          <p class="text-gray-500 text-base">{{ formatTime(settings.last_checkin_at) }}</p>
        </div>
        <router-link
          to="/elder/checkin"
          class="block w-full text-center bg-orange-500 hover:bg-orange-600 text-white font-bold text-xl py-4 rounded-xl transition"
        >
          체크인 하러 가기
        </router-link>
      </div>

      <!-- Card 2: SOS -->
      <div class="bg-white rounded-2xl shadow-md p-6 text-center">
        <h2 class="text-xl font-bold text-red-600 mb-2">🚨 긴급 SOS</h2>
        <p class="text-base text-gray-500 mb-4">3초 꾹 누르면 보호자에게 긴급 연락</p>

        <!-- SOS Button -->
        <div class="flex justify-center mb-4">
          <div class="relative">
            <!-- Progress ring -->
            <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 120 120">
              <circle cx="60" cy="60" r="54" fill="none" stroke="#fee2e2" stroke-width="8" />
              <circle
                cx="60" cy="60" r="54" fill="none"
                stroke="#ef4444" stroke-width="8"
                stroke-linecap="round"
                :stroke-dasharray="339.292"
                :stroke-dashoffset="339.292 - (339.292 * sosProgress / 100)"
                class="transition-all duration-100"
              />
            </svg>
            <button
              class="absolute inset-0 m-auto w-24 h-24 rounded-full bg-red-600 hover:bg-red-700 text-white font-bold text-2xl shadow-lg active:scale-95 transition select-none"
              @mousedown="startSOS"
              @mouseup="cancelSOS"
              @mouseleave="cancelSOS"
              @touchstart.prevent="startSOS"
              @touchend.prevent="cancelSOS"
              @touchcancel.prevent="cancelSOS"
            >
              SOS
            </button>
          </div>
        </div>

        <!-- SOS Active State -->
        <div v-if="sosActive" class="bg-red-50 border border-red-200 rounded-xl p-4 mt-3">
          <p class="text-red-700 font-bold text-lg">🚨 SOS 발동됨!</p>
          <p class="text-red-600 text-base">보호자에게 알림이 전송되었습니다</p>
          <p v-if="sosLocation" class="text-gray-500 text-sm mt-1">
            위치: {{ sosLocation.lat.toFixed(5) }}, {{ sosLocation.lng.toFixed(5) }}
          </p>
          <p class="text-gray-500 text-sm">시간: {{ sosTime }}</p>
          <button
            v-if="sosCancelable"
            @click="cancelSOSAlert"
            class="mt-3 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg text-base"
          >
            취소 ({{ sosCancelCountdown }}초)
          </button>
        </div>
      </div>

      <!-- Card 3: Guardian Settings -->
      <div class="bg-white rounded-2xl shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-3">👨‍👩‍👧 보호자 설정</h2>
        <div v-if="settings.guardian_name" class="space-y-2">
          <div class="flex items-center gap-2">
            <span class="text-green-500 text-lg">●</span>
            <span class="text-lg text-gray-700">{{ settings.guardian_name }} ({{ settings.guardian_phone }})</span>
          </div>
          <div v-if="settings.guardian2_name" class="flex items-center gap-2">
            <span class="text-blue-500 text-lg">●</span>
            <span class="text-lg text-gray-700">{{ settings.guardian2_name }} ({{ settings.guardian2_phone }})</span>
          </div>
        </div>
        <div v-else class="text-gray-400 text-lg mb-3">
          보호자가 설정되지 않았습니다
        </div>
        <button
          @click="showSettingsModal = true"
          class="mt-3 w-full bg-blue-500 hover:bg-blue-600 text-white font-bold text-lg py-3 rounded-xl transition"
        >
          설정 변경
        </button>
      </div>

      <!-- Card 4: Guardian Dashboard -->
      <div class="bg-white rounded-2xl shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-2">📊 보호자 대시보드</h2>
        <p class="text-base text-gray-500 mb-4">가족의 안전 상태를 확인하세요</p>
        <router-link
          to="/elder/guardian"
          class="block w-full text-center bg-indigo-500 hover:bg-indigo-600 text-white font-bold text-lg py-3 rounded-xl transition"
        >
          보호자 화면 보기
        </router-link>
      </div>

      <!-- Checkin Calendar (30 days) -->
      <div class="bg-white rounded-2xl shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold text-gray-800">📅 체크인 달력</h2>
          <span v-if="streakDays > 0" class="text-orange-500 font-bold text-base">
            🔥 연속 {{ streakDays }}일
          </span>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-sm mb-2">
          <span v-for="d in ['일','월','화','수','목','금','토']" :key="d" class="text-gray-400 font-medium py-1">{{ d }}</span>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center">
          <!-- empty slots for alignment -->
          <span v-for="n in calendarStartOffset" :key="'e'+n"></span>
          <span
            v-for="day in calendarDays"
            :key="day.date"
            class="py-1 rounded-lg text-sm"
            :class="{
              'bg-green-100 text-green-700': day.status === 'checked',
              'bg-red-100 text-red-600': day.status === 'missed',
              'bg-gray-50 text-gray-300': day.status === 'future',
            }"
            :title="day.date"
          >
            {{ day.day }}
            <span class="block text-xs">{{ day.status === 'checked' ? '✅' : day.status === 'missed' ? '❌' : '⬜' }}</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Settings Modal -->
    <div v-if="showSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-end sm:items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white px-6 py-4 border-b flex items-center justify-between rounded-t-2xl">
          <h2 class="text-xl font-bold">설정</h2>
          <button @click="showSettingsModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <div class="p-6 space-y-5">
          <!-- Guardian 1 -->
          <div>
            <h3 class="font-bold text-lg text-gray-800 mb-2">보호자 1 (필수)</h3>
            <div class="space-y-3">
              <div>
                <label class="block text-base text-gray-600 mb-1">이름</label>
                <input v-model="form.guardian_name" type="text" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="보호자 이름" />
              </div>
              <div>
                <label class="block text-base text-gray-600 mb-1">전화번호</label>
                <input v-model="form.guardian_phone" type="tel" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="010-0000-0000" />
              </div>
            </div>
          </div>

          <!-- Guardian 2 -->
          <div>
            <h3 class="font-bold text-lg text-gray-800 mb-2">보호자 2 (선택)</h3>
            <div class="space-y-3">
              <div>
                <label class="block text-base text-gray-600 mb-1">이름</label>
                <input v-model="form.guardian2_name" type="text" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="2차 보호자 이름" />
              </div>
              <div>
                <label class="block text-base text-gray-600 mb-1">전화번호</label>
                <input v-model="form.guardian2_phone" type="tel" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="010-0000-0000" />
              </div>
            </div>
          </div>

          <!-- Search member as guardian -->
          <div>
            <h3 class="font-bold text-lg text-gray-800 mb-2">사이트 회원 중 보호자 검색</h3>
            <div class="flex gap-2">
              <input
                v-model="guardianSearch"
                type="text"
                class="flex-1 border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
                placeholder="이메일 또는 닉네임"
                @input="searchGuardian"
              />
            </div>
            <ul v-if="guardianResults.length" class="mt-2 border rounded-xl overflow-hidden">
              <li
                v-for="u in guardianResults"
                :key="u.id"
                class="flex items-center justify-between px-4 py-3 border-b last:border-b-0 hover:bg-blue-50 cursor-pointer"
                @click="selectGuardian(u)"
              >
                <span class="text-base">{{ u.name }} ({{ u.email }})</span>
                <button class="text-blue-500 font-bold text-sm">선택</button>
              </li>
            </ul>
          </div>

          <!-- Checkin time -->
          <div>
            <label class="block font-bold text-lg text-gray-800 mb-2">체크인 시간</label>
            <select v-model="form.checkin_time" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
              <option v-for="h in checkinHours" :key="h.value" :value="h.value">{{ h.label }}</option>
            </select>
          </div>

          <!-- Timezone -->
          <div>
            <label class="block font-bold text-lg text-gray-800 mb-2">시간대</label>
            <select v-model="form.timezone" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
              <option value="America/New_York">동부시간 (ET)</option>
              <option value="America/Chicago">중부시간 (CT)</option>
              <option value="America/Denver">산악시간 (MT)</option>
              <option value="America/Los_Angeles">태평양시간 (PT)</option>
              <option value="Pacific/Honolulu">하와이시간 (HT)</option>
              <option value="America/Anchorage">알래스카시간 (AKT)</option>
              <option value="Asia/Seoul">한국시간 (KST)</option>
            </select>
          </div>

          <!-- Toggles -->
          <div class="space-y-3">
            <label class="flex items-center justify-between">
              <span class="text-lg text-gray-700">체크인 알림</span>
              <button
                @click="form.checkin_enabled = !form.checkin_enabled"
                class="relative w-14 h-8 rounded-full transition"
                :class="form.checkin_enabled ? 'bg-blue-500' : 'bg-gray-300'"
              >
                <span
                  class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full shadow transition-transform"
                  :class="form.checkin_enabled ? 'translate-x-6' : ''"
                ></span>
              </button>
            </label>
            <label class="flex items-center justify-between">
              <span class="text-lg text-gray-700">SOS 기능</span>
              <button
                @click="form.sos_enabled = !form.sos_enabled"
                class="relative w-14 h-8 rounded-full transition"
                :class="form.sos_enabled ? 'bg-red-500' : 'bg-gray-300'"
              >
                <span
                  class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full shadow transition-transform"
                  :class="form.sos_enabled ? 'translate-x-6' : ''"
                ></span>
              </button>
            </label>
          </div>

          <!-- Save -->
          <button
            @click="saveSettings"
            :disabled="saving"
            class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white font-bold text-lg py-4 rounded-xl transition"
          >
            {{ saving ? '저장 중...' : '설정 저장' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Call Message -->
    <div v-if="callMessage" class="fixed top-4 left-1/2 -translate-x-1/2 z-40 bg-blue-600 text-white px-6 py-3 rounded-xl shadow-lg text-lg font-bold">
      {{ callMessage }}
    </div>

    <!-- WebRTC Call Modal -->
    <CallModal
      :callState="callState"
      :callType="callType"
      :callDuration="callDuration"
      :localStream="localStream"
      :remoteStream="remoteStream"
      :remoteUser="remoteUser"
      :isMuted="isMuted"
      :isVideoOff="isVideoOff"
      :formatDuration="formatDuration"
      @answerCall="answerCall"
      @rejectCall="rejectCall"
      @endCall="endCall"
      @toggleMute="toggleMute"
      @toggleVideo="toggleVideo"
    />

    <!-- SOS Confirm Modal -->
    <div v-if="showSOSConfirm" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-sm p-6 text-center">
        <p class="text-4xl mb-3">🚨</p>
        <h2 class="text-2xl font-bold text-red-600 mb-2">긴급상황인가요?</h2>
        <p class="text-lg text-gray-600 mb-6">보호자에게 긴급 알림이 전송됩니다</p>
        <div class="flex gap-3">
          <button
            @click="showSOSConfirm = false"
            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold text-lg py-3 rounded-xl"
          >
            아니요
          </button>
          <button
            @click="confirmSOS"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold text-lg py-3 rounded-xl"
          >
            네, 긴급이에요!
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import { useSocket } from '../../composables/useSocket'
import { useWebRTC } from '../../composables/useWebRTC'
import CallModal from '../../components/CallModal.vue'
import { usePushNotification } from '../../composables/usePushNotification'

const auth = useAuthStore()
const { subscribe: pushSubscribe, requestPermission } = usePushNotification()
const token = localStorage.getItem('sk_token')
const headers = { Authorization: `Bearer ${token}` }

// State
const settings = reactive({
  elder_mode: false,
  guardian_name: '',
  guardian_phone: '',
  guardian2_name: '',
  guardian2_phone: '',
  checkin_time: '09:00',
  checkin_enabled: true,
  sos_enabled: true,
  last_checkin_at: null,
  missed_count: 0,
  recent_logs: [],
  timezone: 'America/New_York',
})
const loading = ref(true)
const showSettingsModal = ref(false)
const saving = ref(false)

// Settings form
const form = reactive({
  guardian_name: '',
  guardian_phone: '',
  guardian2_name: '',
  guardian2_phone: '',
  checkin_time: '09:00',
  checkin_enabled: true,
  sos_enabled: true,
  timezone: 'America/New_York',
})

// Guardian search
const guardianSearch = ref('')
const guardianResults = ref([])
let searchTimeout = null

// SOS
const sosProgress = ref(0)
const sosActive = ref(false)
const sosLocation = ref(null)
const sosTime = ref('')
const showSOSConfirm = ref(false)
const sosCancelable = ref(false)
const sosCancelCountdown = ref(10)
let sosTimer = null
let sosProgressInterval = null
let sosCancelTimer = null
let sosCancelInterval = null

// WebRTC & Socket
const { isConnected: socketConnected, connect: socketConnect, emit: socketEmit, on: socketOn, off: socketOff, socket: getSocket } = useSocket()
const {
  localStream, remoteStream, peerConnection,
  callState, callType, callDuration,
  isMuted, isVideoOff, remoteUser,
  setSocket, setupSocketListeners, removeSocketListeners,
  startCall, answerCall, rejectCall, endCall,
  toggleMute, toggleVideo, formatDuration,
} = useWebRTC()
const guardianOnline = ref(false)
const callMessage = ref('')

// Calendar
const checkinHistory = ref([])
const streakDays = ref(0)

// Computed
const todayCheckedIn = computed(() => {
  if (!settings.last_checkin_at) return false
  const today = new Date().toISOString().slice(0, 10)
  const last = new Date(settings.last_checkin_at).toISOString().slice(0, 10)
  return today === last
})

const checkinHours = computed(() => {
  const hours = []
  for (let h = 6; h <= 22; h++) {
    const label = h < 12 ? `오전 ${h}시` : h === 12 ? '오후 12시' : `오후 ${h - 12}시`
    hours.push({ value: `${String(h).padStart(2, '0')}:00`, label })
  }
  return hours
})

const calendarDays = computed(() => {
  const days = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const checkedDates = new Set(
    checkinHistory.value.map(h => new Date(h.checked_at || h.created_at).toISOString().slice(0, 10))
  )
  for (let i = 29; i >= 0; i--) {
    const d = new Date(today)
    d.setDate(d.getDate() - i)
    const dateStr = d.toISOString().slice(0, 10)
    let status = 'future'
    if (d <= today) {
      status = checkedDates.has(dateStr) ? 'checked' : 'missed'
    }
    if (d.toDateString() === today.toDateString() && !checkedDates.has(dateStr)) {
      status = 'future' // today not yet missed
    }
    days.push({ date: dateStr, day: d.getDate(), status })
  }
  return days
})

const calendarStartOffset = computed(() => {
  if (!calendarDays.value.length) return 0
  const firstDate = new Date(calendarDays.value[0].date)
  return firstDate.getDay()
})

// Methods
function formatTime(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return d.toLocaleString('ko-KR', { month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function loadSettings() {
  try {
    const { data } = await axios.get('/api/elder/settings', { headers })
    const s = data.settings || data
    Object.assign(settings, s)
    if (data.recent_logs) settings.recent_logs = data.recent_logs
    Object.assign(form, {
      guardian_name: s.guardian_name || '',
      guardian_phone: s.guardian_phone || '',
      guardian2_name: s.guardian2_name || '',
      guardian2_phone: s.guardian2_phone || '',
      checkin_time: s.checkin_time || '09:00',
      checkin_enabled: s.checkin_enabled ?? true,
      sos_enabled: s.sos_enabled ?? true,
      timezone: s.timezone || 'America/New_York',
    })
  } catch (e) {
    console.error('Failed to load elder settings', e)
  } finally {
    loading.value = false
  }
}

async function loadCheckinHistory() {
  try {
    const { data } = await axios.get('/api/elder/checkin-history', { headers })
    checkinHistory.value = data.data || data || []
    calculateStreak()
  } catch (e) {
    console.error('Failed to load checkin history', e)
  }
}

function calculateStreak() {
  const checkedDates = new Set(
    checkinHistory.value.map(h => new Date(h.checked_at || h.created_at).toISOString().slice(0, 10))
  )
  let streak = 0
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  for (let i = 0; i < 365; i++) {
    const d = new Date(today)
    d.setDate(d.getDate() - i)
    const dateStr = d.toISOString().slice(0, 10)
    if (checkedDates.has(dateStr)) {
      streak++
    } else if (i > 0) {
      break
    }
  }
  streakDays.value = streak
}

async function saveSettings() {
  saving.value = true
  try {
    await axios.put('/api/elder/settings', { ...form }, { headers: { ...headers, 'Content-Type': 'application/json' } })
    Object.assign(settings, form)
    showSettingsModal.value = false
  } catch (e) {
    alert('설정 저장에 실패했습니다')
  } finally {
    saving.value = false
  }
}

async function searchGuardian() {
  clearTimeout(searchTimeout)
  if (guardianSearch.value.length < 2) {
    guardianResults.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    try {
      const { data } = await axios.get('/api/elder/guardian-search', {
        headers,
        params: { q: guardianSearch.value }
      })
      guardianResults.value = data.data || data || []
    } catch (e) {
      guardianResults.value = []
    }
  }, 300)
}

async function selectGuardian(user) {
  try {
    await axios.post('/api/elder/link-guardian', { guardian_user_id: user.id }, { headers })
    form.guardian_name = user.name
    form.guardian_phone = user.phone || ''
    guardianSearch.value = ''
    guardianResults.value = []
  } catch (e) {
    alert('보호자 연결에 실패했습니다')
  }
}

// SOS Long Press
function startSOS() {
  sosProgress.value = 0
  const startTime = Date.now()
  sosProgressInterval = setInterval(() => {
    const elapsed = Date.now() - startTime
    sosProgress.value = Math.min((elapsed / 3000) * 100, 100)
    if (elapsed >= 3000) {
      clearInterval(sosProgressInterval)
      sosProgressInterval = null
      showSOSConfirm.value = true
    }
  }, 50)
}

function cancelSOS() {
  if (sosProgressInterval) {
    clearInterval(sosProgressInterval)
    sosProgressInterval = null
  }
  sosProgress.value = 0
}

async function confirmSOS() {
  showSOSConfirm.value = false
  sosProgress.value = 0

  let lat = null, lng = null
  try {
    const pos = await new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 })
    })
    lat = pos.coords.latitude
    lng = pos.coords.longitude
  } catch (e) {
    // GPS unavailable - still send SOS
  }

  try {
    await axios.post('/api/elder/sos', { lat, lng }, { headers })
    sosActive.value = true
    sosLocation.value = lat ? { lat, lng } : null
    sosTime.value = new Date().toLocaleString('ko-KR')
    sosCancelable.value = true
    sosCancelCountdown.value = 10

    sosCancelInterval = setInterval(() => {
      sosCancelCountdown.value--
      if (sosCancelCountdown.value <= 0) {
        clearInterval(sosCancelInterval)
        sosCancelable.value = false
      }
    }, 1000)

    // Socket SOS + WebRTC call to guardian
    if (settings.guardian_user_id) {
      socketEmit('elder:sos', {
        guardianUserId: String(settings.guardian_user_id),
        elderName: auth.user?.name || '어르신',
        lat, lng,
      })

      // Check guardian online status and start call
      try {
        const { data: onlineData } = await axios.get('/api/socket/online/' + settings.guardian_user_id)
        if (onlineData.online) {
          guardianOnline.value = true
          callMessage.value = ''
          startCall(
            { id: String(settings.guardian_user_id), name: settings.guardian_name || '보호자', _callerName: auth.user?.name || '어르신' },
            'audio'
          )
        } else {
          guardianOnline.value = false
          callMessage.value = '보호자에게 알림을 보냈습니다 (오프라인)'
          setTimeout(() => { callMessage.value = '' }, 5000)
        }
      } catch(e) {
        callMessage.value = '보호자에게 알림을 보냈습니다'
        setTimeout(() => { callMessage.value = '' }, 5000)
      }
    }
  } catch (e) {
    alert('SOS 전송에 실패했습니다. 직접 112에 연락해주세요.')
  }
}

function cancelSOSAlert() {
  sosActive.value = false
  sosCancelable.value = false
  if (sosCancelInterval) clearInterval(sosCancelInterval)
}

// Lifecycle
onMounted(() => {
  loadSettings()
  loadCheckinHistory()

  // 푸시 알림 구독
  requestPermission().then(perm => {
    if (perm === 'granted' && auth.user?.id) {
      pushSubscribe(auth.user.id)
    }
  })

  // Socket connection + WebRTC setup
  socketConnect()
  const initWebRTCSocket = () => {
    const s = getSocket()
    if (s) {
      setSocket(s)
      setupSocketListeners()
    } else {
      setTimeout(initWebRTCSocket, 500)
    }
  }
  setTimeout(initWebRTCSocket, 500)
})

onUnmounted(() => {
  if (sosProgressInterval) clearInterval(sosProgressInterval)
  if (sosCancelInterval) clearInterval(sosCancelInterval)
  removeSocketListeners()
})
</script>
