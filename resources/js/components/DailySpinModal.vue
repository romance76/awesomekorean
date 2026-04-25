<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center" @click.self="close">
      <!-- 배경 오버레이 -->
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

      <!-- 모달 -->
      <div class="relative w-[340px] max-w-[95vw] bg-gradient-to-b from-purple-900 to-indigo-900 rounded-3xl p-6 shadow-2xl border border-purple-500/30">
        <!-- 닫기 -->
        <button @click="close" class="absolute top-3 right-3 text-white/50 hover:text-white text-xl w-8 h-8 flex items-center justify-center">
          &times;
        </button>

        <h2 class="text-center text-xl font-black text-yellow-300 mb-4">🎰 오늘의 무료 포인트</h2>

        <!-- 룰렛 휠 -->
        <div class="relative w-[260px] h-[260px] mx-auto mb-4">
          <!-- 화살표 표시 -->
          <div class="absolute top-[-12px] left-1/2 -translate-x-1/2 z-10 text-2xl" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,.5));">
            <svg width="24" height="28" viewBox="0 0 24 28" fill="none">
              <path d="M12 28L0 0h24L12 28z" fill="#EF4444"/>
              <path d="M12 24L3 4h18L12 24z" fill="#F87171"/>
            </svg>
          </div>

          <!-- 휠 컨테이너 -->
          <div class="w-full h-full rounded-full border-4 border-yellow-400 shadow-xl overflow-hidden"
            :style="{ transform: `rotate(${rotation}deg)`, transition: spinning ? 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)' : 'none' }">
            <svg viewBox="0 0 260 260" class="w-full h-full">
              <g v-for="(sector, i) in sectors" :key="i">
                <path :d="sectorPath(i)" :fill="sector.color" stroke="#1e1b4b" stroke-width="1"/>
                <text :transform="sectorTextTransform(i)"
                  text-anchor="middle" dominant-baseline="central"
                  class="text-[13px] font-black" :fill="sector.textColor || '#fff'">
                  {{ sector.label }}
                </text>
              </g>
              <!-- 중앙 원 -->
              <circle cx="130" cy="130" r="28" fill="#1e1b4b" stroke="#fbbf24" stroke-width="3"/>
              <text x="130" y="130" text-anchor="middle" dominant-baseline="central" class="text-[10px] font-bold" fill="#fbbf24">SPIN</text>
            </svg>
          </div>
        </div>

        <!-- 스핀 버튼 -->
        <button v-if="!alreadySpun && !spinning && !resultPoints"
          @click="spin"
          class="w-full bg-gradient-to-r from-yellow-400 to-orange-500 text-yellow-900 font-black py-3 rounded-xl text-lg hover:from-yellow-300 hover:to-orange-400 transition shadow-lg active:scale-95">
          돌리기!
        </button>

        <!-- 돌리는 중 -->
        <div v-else-if="spinning" class="text-center text-yellow-300 font-bold py-3 animate-pulse">
          돌리는 중...
        </div>

        <!-- 결과 표시 -->
        <div v-else-if="resultPoints !== null" class="text-center">
          <div :class="isJackpot ? 'animate-bounce' : ''" class="mb-2">
            <span v-if="isJackpot" class="text-4xl">&#127881;</span>
            <span class="text-2xl font-black" :class="isJackpot ? 'text-yellow-300' : 'text-white'">
              {{ resultPoints > 0 ? '+' + resultPoints.toLocaleString() + 'P' : '꽝!' }}
            </span>
            <span v-if="isJackpot" class="text-4xl">&#127881;</span>
          </div>
          <div v-if="isJackpot" class="text-yellow-400 font-bold text-sm mb-2">잭팟!!</div>
          <div v-if="resultPoints > 0" class="text-purple-300 text-xs mb-3">포인트가 적립되었습니다</div>
          <div v-else class="text-purple-300 text-xs mb-3">내일 다시 도전하세요!</div>
          <button @click="close" class="w-full bg-purple-700 hover:bg-purple-600 text-white font-bold py-2.5 rounded-xl transition">
            확인
          </button>
        </div>

        <!-- 이미 사용 -->
        <div v-else-if="alreadySpun" class="text-center">
          <div class="text-4xl mb-2">🌙</div>
          <div class="text-yellow-300 font-bold text-base mb-1">오늘 이미 돌렸습니다</div>
          <div class="text-purple-300 text-xs mb-3">자정 이후 다시 도전 가능합니다</div>
          <button @click="close" class="w-full bg-purple-700 hover:bg-purple-600 text-white font-bold py-2.5 rounded-xl transition">
            닫기
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  show: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'earned'])

const alreadySpun = ref(false)
const spinning = ref(false)
const resultPoints = ref(null)
const isJackpot = ref(false)
const newBalance = ref(null)
const todayPoints = ref(0)
const rotation = ref(0)

// 룰렛 섹터 정의
const sectors = [
  { value: 0,   label: '0',   color: '#6366f1' },
  { value: 10,  label: '10',  color: '#8b5cf6' },
  { value: 20,  label: '20',  color: '#6366f1' },
  { value: 30,  label: '30',  color: '#8b5cf6' },
  { value: 50,  label: '50',  color: '#6366f1' },
  { value: 100, label: '100', color: '#8b5cf6' },
  { value: 200, label: '200', color: '#6366f1', textColor: '#fde68a' },
  { value: 300, label: '300', color: '#dc2626', textColor: '#fef08a' },
]

const sectorAngle = 360 / sectors.length

function sectorPath(index) {
  const cx = 130, cy = 130, r = 128
  const startAngle = (index * sectorAngle - 90) * Math.PI / 180
  const endAngle = ((index + 1) * sectorAngle - 90) * Math.PI / 180
  const x1 = cx + r * Math.cos(startAngle)
  const y1 = cy + r * Math.sin(startAngle)
  const x2 = cx + r * Math.cos(endAngle)
  const y2 = cy + r * Math.sin(endAngle)
  const largeArc = sectorAngle > 180 ? 1 : 0
  return `M${cx},${cy} L${x1},${y1} A${r},${r} 0 ${largeArc} 1 ${x2},${y2} Z`
}

function sectorTextTransform(index) {
  const cx = 130, cy = 130, r = 85
  const angle = ((index + 0.5) * sectorAngle - 90) * Math.PI / 180
  const x = cx + r * Math.cos(angle)
  const y = cy + r * Math.sin(angle)
  const rotDeg = (index + 0.5) * sectorAngle
  return `translate(${x},${y}) rotate(${rotDeg})`
}

// 결과값에 가장 가까운 섹터 인덱스
function findSectorIndex(points) {
  // 정확한 값 먼저 탐색
  const exact = sectors.findIndex(s => s.value === points)
  if (exact >= 0) return exact
  // 가장 가까운 값
  let closest = 0
  let minDiff = Math.abs(sectors[0].value - points)
  for (let i = 1; i < sectors.length; i++) {
    const diff = Math.abs(sectors[i].value - points)
    if (diff < minDiff) { minDiff = diff; closest = i }
  }
  return closest
}

async function spin() {
  if (spinning.value || alreadySpun.value) return

  spinning.value = true
  resultPoints.value = null

  try {
    const { data } = await axios.post('/api/points/daily-spin')
    const points = data.data?.points_won ?? 0

    // 결과 섹터 계산
    const targetIndex = findSectorIndex(points)
    // 섹터의 중앙 각도 (화살표는 상단=0도 위치)
    // 휠이 회전하면 화살표가 가리키는 섹터: 화살표는 0도 위치에 있고, 휠이 X도 회전하면
    // 화살표가 가리키는 섹터는 360-X도 위치의 섹터
    const targetAngle = targetIndex * sectorAngle + sectorAngle / 2
    // 최종 정지 각도: 화살표(0도)에 targetAngle 섹터가 오도록
    const stopAngle = 360 - targetAngle
    // 5바퀴 + 보정
    const totalRotation = 360 * 5 + stopAngle

    rotation.value = rotation.value + totalRotation

    // 4초 후 결과 표시 (CSS transition duration과 맞춤)
    setTimeout(() => {
      spinning.value = false
      resultPoints.value = points
      isJackpot.value = points >= 200
      emit('earned', { points })
    }, 4200)
  } catch (e) {
    spinning.value = false
    const msg = e.response?.data?.message || ''
    if (msg.includes('이미') || e.response?.status === 400) {
      alreadySpun.value = true
    } else {
      alert(msg || '룰렛 오류가 발생했습니다')
    }
  }
}

async function checkStatus() {
  try {
    const { data } = await axios.get('/api/points/balance')
    alreadySpun.value = !!data.daily_spin_done
    todayPoints.value = 0
  } catch {}
}

function close() {
  emit('close')
}

import { watch } from 'vue'
watch(() => props.show, (v) => { if (v) checkStatus() })

onMounted(() => {
  if (props.show) checkStatus()
})
</script>
