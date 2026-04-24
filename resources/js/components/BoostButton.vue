<template>
<!--
  5개 리소스 공통 상위노출 버튼 + 모달.
  props:
    resource: 'market' | 'realestate' | 'jobs' | 'businesses' | 'clubs'
    item: 원본 아이템 객체 (boosted_until / promotion_tier / promotion_expires_at / state / category)
    size: 'sm' | 'md'
  emits:
    updated: 결제/해제 후 재로드 필요 시 부모가 처리
-->
<div class="boost-wrap">
  <!-- 활성 중 배너 -->
  <div v-if="isActive" class="boost-active">
    🚀 상위노출 중 · {{ remaining }}
  </div>
  <!-- 비활성: 버튼 -->
  <button v-else @click="showModal = true"
    class="boost-btn" :class="sizeClass">
    🚀 상위노출
  </button>

  <!-- 모달 -->
  <Teleport to="body">
    <div v-if="showModal" class="boost-modal-overlay" @click.self="showModal = false">
      <div class="boost-modal">
        <h3 class="modal-title">🚀 상위노출</h3>

        <div class="location-info">
          📍 내 지역({{ item?.state || '?' }} + 인접 주) 사용자에게 우선 노출됩니다
        </div>

        <div class="days-grid">
          <button v-for="opt in options" :key="opt.days"
            @click="selected = opt"
            class="day-btn"
            :class="{ active: selected?.days === opt.days }">
            <div class="day-num">{{ opt.days }}일</div>
            <div class="day-price">{{ opt.price }}P</div>
          </button>
        </div>

        <div class="total-box">
          <span class="total-label">총 결제</span>
          <span class="total-value">{{ selected?.price || 0 }}P</span>
        </div>

        <div class="modal-actions">
          <button @click="showModal = false" class="btn-cancel" :disabled="paying">취소</button>
          <button @click="pay" class="btn-pay" :disabled="paying || !selected">
            {{ paying ? '결제 중...' : '결제하기' }}
          </button>
        </div>

        <div v-if="errorMsg" class="err-msg">{{ errorMsg }}</div>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../stores/site'

const props = defineProps({
  resource: { type: String, required: true },
  item: { type: Object, required: true },
  size: { type: String, default: 'md' },
})
const emit = defineEmits(['updated'])
const site = useSiteStore()

const showModal = ref(false)
const paying = ref(false)
const errorMsg = ref('')
const options = [
  { days: 1, price: 100 },
  { days: 3, price: 300 },
  { days: 7, price: 700 },
]
const selected = ref(options[0])

// 활성 여부 — modern tier (state_plus 등) 또는 legacy boosted_until 둘 다 체크
const isActive = computed(() => {
  const it = props.item || {}
  if (it.promotion_tier && it.promotion_tier !== 'none' && it.promotion_expires_at
      && new Date(it.promotion_expires_at) > new Date()) return true
  if (it.boosted_until && new Date(it.boosted_until) > new Date()) return true
  return false
})
const expiresAt = computed(() => {
  const it = props.item || {}
  return it.promotion_expires_at || it.boosted_until
})
const remaining = computed(() => {
  if (!expiresAt.value) return ''
  const diff = new Date(expiresAt.value) - new Date()
  if (diff <= 0) return '만료됨'
  const h = Math.floor(diff / 3_600_000)
  const d = Math.floor(h / 24)
  const remH = h - d * 24
  if (d >= 1) return `${d}일 ${remH}시간 남음`
  const m = Math.floor(diff / 60_000) - h * 60
  return `${h}시간 ${m}분 남음`
})

const sizeClass = computed(() => props.size === 'sm' ? 'btn-sm' : 'btn-md')

async function pay() {
  if (!selected.value) return
  paying.value = true
  errorMsg.value = ''
  try {
    // 모든 리소스 통일: state_plus tier (위치 기반) · 플랫 N일
    // 서버 HasPromotions 가 price_per_day 에 따라 차감 (point_settings)
    // 단순 UX를 위해 tier=state_plus 고정 (위치 기반 우선)
    const endpoint = endpointFor(props.resource)
    const { data } = await axios.post(`${endpoint}/${props.item.id}/promote`, {
      tier: 'state_plus',
      days: selected.value.days,
    })
    site.toast?.(data.message || '상위노출이 적용되었습니다', 'success')
    showModal.value = false
    emit('updated')
  } catch (e) {
    const msg = e.response?.data?.message || '처리 실패'
    errorMsg.value = msg
    // 이미 활성 중이면 부모에게 알림 (아이템 리로드 유도)
    if (e.response?.data?.data?.already_active) {
      site.toast?.(msg, 'warning')
      showModal.value = false
      emit('updated')
    }
  }
  paying.value = false
}

function endpointFor(r) {
  // URL 세그먼트 매핑
  return {
    market: '/api/market',
    realestate: '/api/realestate',
    jobs: '/api/jobs',
    businesses: '/api/businesses',
    clubs: '/api/clubs',
  }[r] || '/api/market'
}
</script>

<style scoped>
.boost-wrap { display: contents; }

.boost-btn {
  background: linear-gradient(135deg, #a855f7, #9333ea);
  color: #fff; border: none; font-weight: 800; border-radius: 10px; cursor: pointer;
  transition: transform 0.1s, box-shadow 0.15s;
}
.boost-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(168, 85, 247, 0.35); }
.boost-btn.btn-md { padding: 10px 16px; font-size: 13px; width: 100%; }
.boost-btn.btn-sm { padding: 6px 12px; font-size: 11px; }

.boost-active {
  background: linear-gradient(90deg, #f3e8ff, #fce7f3);
  border: 2px solid #c084fc;
  color: #7c3aed;
  font-weight: 800;
  text-align: center;
  border-radius: 10px;
  padding: 8px 14px;
  font-size: 12px;
}

.boost-modal-overlay {
  position: fixed; inset: 0; background: rgba(0, 0, 0, 0.55);
  z-index: 10000; display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.boost-modal {
  background: #fff; border-radius: 20px; padding: 22px;
  width: 100%; max-width: 380px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
}
.modal-title { font-size: 17px; font-weight: 900; color: #1f2937; margin-bottom: 10px; }
.location-info {
  background: #f3e8ff; color: #6b21a8; border-radius: 10px; padding: 10px 12px;
  font-size: 11px; font-weight: 700; margin-bottom: 14px;
}

.days-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.day-btn {
  background: #f3f4f6; border: 2px solid transparent; border-radius: 12px;
  padding: 12px 8px; cursor: pointer; transition: all 0.15s;
}
.day-btn:hover { background: #ede9fe; }
.day-btn.active { background: linear-gradient(135deg, #a855f7, #9333ea); border-color: #7c3aed; color: #fff; }
.day-num { font-size: 15px; font-weight: 900; }
.day-price { font-size: 11px; margin-top: 4px; opacity: 0.85; }

.total-box {
  background: linear-gradient(90deg, #f3e8ff, #fce7f3);
  border-radius: 12px; padding: 12px 16px; margin-bottom: 14px;
  display: flex; justify-content: space-between; align-items: center;
}
.total-label { font-size: 13px; color: #7c3aed; font-weight: 700; }
.total-value { font-size: 20px; font-weight: 900; color: #7c3aed; }

.modal-actions { display: flex; gap: 8px; }
.btn-cancel, .btn-pay { flex: 1; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 14px; border: none; cursor: pointer; transition: all 0.15s; }
.btn-cancel { background: #f3f4f6; color: #374151; }
.btn-cancel:hover:not(:disabled) { background: #e5e7eb; }
.btn-pay { background: linear-gradient(135deg, #a855f7, #9333ea); color: #fff; }
.btn-pay:hover:not(:disabled) { box-shadow: 0 4px 16px rgba(168, 85, 247, 0.4); }
.btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }

.err-msg {
  margin-top: 10px; padding: 10px; background: #fef2f2; border: 1px solid #fecaca;
  border-radius: 10px; color: #dc2626; font-size: 12px; font-weight: 600; text-align: center;
}
</style>
