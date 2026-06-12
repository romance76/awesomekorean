<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="20" /></span>
    가격/할인 센터
  </h1>
  <p class="text-xs text-ink-muted mb-4">포인트 설정, 구매 패키지, 광고 슬롯 가격, 할인 이벤트를 한 곳에서 관리</p>

  <!-- 탭 네비게이션 -->
  <div class="flex gap-1 mb-5 bg-gray-100 rounded-xl p-1 overflow-x-auto">
    <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
      class="flex-1 inline-flex items-center justify-center gap-1 text-xs py-2 px-3 whitespace-nowrap transition-colors"
      :class="activeTab === t.key ? 'bg-white text-ink font-semibold rounded-lg shadow-sm' : 'text-ink-muted'">
      <AppIcon :name="t.icon" :size="13" /> {{ t.label }}
    </button>
  </div>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>

  <!-- ═══ 탭 1: 포인트 설정 (카테고리 카드 그리드) ═══ -->
  <div v-else-if="activeTab === 'points'">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-5">
      <div v-for="cat in pointCategories" :key="cat" class="card overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-50 font-bold text-sm flex items-center justify-between" :class="catStyles[cat].bg">
          <span class="flex items-center gap-1.5"><AppIcon :name="catStyles[cat].icon" :size="14" /> {{ catStyles[cat].label }}</span>
          <span class="text-xs font-normal opacity-70">{{ (grouped[cat]||[]).length }}개</span>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="item in grouped[cat] || []" :key="item.key" class="px-4 py-2 flex items-center gap-3">
            <div class="flex-1 min-w-0">
              <div class="text-xs font-semibold text-ink truncate">{{ item.label }}</div>
              <div class="text-[11px] text-ink-faint truncate">{{ item.key }}<span v-if="item.description"> · {{ item.description }}</span></div>
            </div>
            <input v-model="item.value" class="input-soft !w-32 !px-2 !py-1 text-xs text-right font-mono" />
          </div>
        </div>
      </div>
    </div>
    <button @click="savePointSettings" :disabled="savingPoints" class="btn-primary">
      {{ savingPoints ? '저장중...' : '포인트 설정 저장' }}
    </button>
    <span v-if="pointMsg" class="ml-3 text-xs" :class="pointMsgOk ? 'text-green-600' : 'text-red-500'">{{ pointMsg }}</span>
  </div>

  <!-- ═══ 탭 2: 포인트 패키지 ═══ -->
  <div v-else-if="activeTab === 'packages'">
    <div class="card p-4 mb-4">
      <div class="text-xs text-ink-muted mb-3">형식: <code class="bg-gray-100 px-1 rounded">가격($)|포인트|보너스</code> (예: <code class="bg-gray-100 px-1 rounded">4.99|500|0</code>)</div>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <div v-for="pkg in grouped.package || []" :key="pkg.key" class="bg-amber-50 rounded-xl border border-amber-200 p-3">
          <div class="text-xs font-bold text-amber-800 mb-2">{{ pkg.label }}</div>
          <input v-model="pkg.value" class="input-soft !px-2 !py-1.5 text-xs font-mono text-center" />
          <div class="text-[11px] text-ink-faint mt-1 truncate">{{ pkg.key }}</div>
          <div v-if="pkg.value && pkg.value.includes('|')" class="text-[11px] text-amber-700 mt-1 flex items-baseline gap-1 flex-wrap">
            <span class="font-bold">${{ (pkg.value.split('|')[0] || '0') }}</span>
            <span>→</span>
            <span>{{ (Number(pkg.value.split('|')[1] || 0) + Number(pkg.value.split('|')[2] || 0)).toLocaleString() }}P</span>
            <span v-if="Number(pkg.value.split('|')[2] || 0) > 0" class="text-green-600">(+{{ Number(pkg.value.split('|')[2]).toLocaleString() }}P)</span>
          </div>
        </div>
      </div>
    </div>
    <button @click="savePointSettings" :disabled="savingPoints" class="btn-primary">
      {{ savingPoints ? '저장중...' : '패키지 저장' }}
    </button>
    <span v-if="pointMsg" class="ml-3 text-xs" :class="pointMsgOk ? 'text-green-600' : 'text-red-500'">{{ pointMsg }}</span>
  </div>

  <!-- ═══ 탭 3: 광고 가격 ═══ -->
  <div v-else-if="activeTab === 'ads'" class="space-y-5">
    <!-- 등급별 최소 입찰가 -->
    <div class="card p-4">
      <div class="font-bold text-sm text-ink mb-3 flex items-center gap-2">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="14" /></span>등급별 최소 월 입찰가 (P)
      </div>
      <div class="mb-3">
        <div class="text-xs font-bold text-ink-light mb-1 flex items-center gap-1"><AppIcon name="map-pin" :size="12" /> 좌측 사이드바</div>
        <div class="grid grid-cols-3 gap-2">
          <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-2">
            <label class="text-xs font-bold text-yellow-700 block mb-1">🥇 프리미엄</label>
            <input type="number" v-model.number="minPrices.left_premium" class="input-soft !px-1 !py-1 text-xs text-center" />
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-2">
            <label class="text-xs font-bold text-blue-700 block mb-1">🥈 스탠다드</label>
            <input type="number" v-model.number="minPrices.left_standard" class="input-soft !px-1 !py-1 text-xs text-center" />
          </div>
          <div class="bg-green-50 border border-green-200 rounded-xl p-2">
            <label class="text-xs font-bold text-green-700 block mb-1">🥉 이코노미</label>
            <input type="number" v-model.number="minPrices.left_economy" class="input-soft !px-1 !py-1 text-xs text-center" />
          </div>
        </div>
      </div>
      <div class="mb-3">
        <div class="text-xs font-bold text-ink-light mb-1 flex items-center gap-1"><AppIcon name="map-pin" :size="12" /> 우측 사이드바</div>
        <div class="grid grid-cols-2 gap-2">
          <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-2">
            <label class="text-xs font-bold text-yellow-700 block mb-1">🥇 프리미엄</label>
            <input type="number" v-model.number="minPrices.right_premium" class="input-soft !px-1 !py-1 text-xs text-center" />
          </div>
          <div class="bg-green-50 border border-green-200 rounded-xl p-2">
            <label class="text-xs font-bold text-green-700 block mb-1">🥉 이코노미</label>
            <input type="number" v-model.number="minPrices.right_economy" class="input-soft !px-1 !py-1 text-xs text-center" />
          </div>
        </div>
      </div>
      <div>
        <div class="text-xs font-bold text-ink-light mb-1 flex items-center gap-1"><AppIcon name="globe" :size="12" /> 지역별 추가금</div>
        <div class="grid grid-cols-2 gap-2">
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-2">
            <label class="text-xs font-bold text-blue-700 block mb-1">주 (카운티 대비 +)</label>
            <input type="number" v-model.number="geoMarkup.state" class="input-soft !px-1 !py-1 text-xs text-center" />
          </div>
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-2">
            <label class="text-xs font-bold text-amber-700 block mb-1">전국 (주 대비 +)</label>
            <input type="number" v-model.number="geoMarkup.national" class="input-soft !px-1 !py-1 text-xs text-center" />
          </div>
        </div>
      </div>
      <button @click="saveAdPrices" :disabled="savingAds" class="btn-primary !px-4 !py-1.5 text-xs mt-3">
        {{ savingAds ? '저장중...' : '가격 저장' }}
      </button>
      <span v-if="adPriceMsg" class="ml-3 text-xs text-green-600">{{ adPriceMsg }}</span>
    </div>

    <!-- 페이지별 슬롯 수 -->
    <div class="card p-4">
      <div class="font-bold text-sm text-ink mb-1 flex items-center gap-2">
        <span class="icon-chip w-7 h-7 bg-blue-50 text-blue-600"><AppIcon name="list" :size="14" /></span>페이지별 광고 슬롯 수
      </div>
      <p class="text-xs text-ink-muted mb-3">관리자 메뉴에서 활성화된 페이지가 자동으로 나열됩니다. 좌/우 둘 다 0 이면 해당 페이지 광고 자체가 꺼집니다.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div v-for="menu in adEligibleMenus" :key="menu.key" class="bg-gray-50 rounded-xl border border-gray-100 p-3 flex items-center gap-3"
          :class="isPageOff(menu.key) ? 'opacity-60 border-dashed' : ''">
          <div class="w-20 flex-shrink-0">
            <div class="text-xs font-bold text-ink flex items-center gap-1">
              <span>{{ menu.icon }}</span>{{ menu.label }}
              <span v-if="isPageOff(menu.key)" class="text-[11px] text-ink-faint font-normal">(꺼짐)</span>
            </div>
            <div class="text-[11px] text-ink-faint">{{ menu.path }}</div>
          </div>
          <div class="flex-1 grid grid-cols-2 gap-2">
            <div>
              <label class="text-xs font-bold text-blue-600 block mb-0.5">좌</label>
              <div class="flex items-center gap-1">
                <input type="range" :value="slotOf(menu.key).left_slots" @input="setSlot(menu, 'left_slots', $event.target.value)" min="0" max="5" class="flex-1 accent-amber-400" />
                <span class="text-xs font-bold text-blue-700 w-4 text-center">{{ slotOf(menu.key).left_slots }}</span>
              </div>
            </div>
            <div>
              <label class="text-xs font-bold text-orange-600 block mb-0.5">우</label>
              <div class="flex items-center gap-1">
                <input type="range" :value="slotOf(menu.key).right_slots" @input="setSlot(menu, 'right_slots', $event.target.value)" min="0" max="5" class="flex-1 accent-amber-400" />
                <span class="text-xs font-bold text-orange-700 w-4 text-center">{{ slotOf(menu.key).right_slots }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!adEligibleMenus.length" class="text-center py-6 text-sm text-ink-muted">활성화된 메뉴가 없습니다</div>
      <button @click="savePageConfig" :disabled="savingPageConfig" class="btn-primary !px-4 !py-1.5 text-xs mt-3">
        {{ savingPageConfig ? '저장중...' : '슬롯 수 저장' }}
      </button>
      <span v-if="pageConfigMsg" class="ml-3 text-xs text-green-600">{{ pageConfigMsg }}</span>
    </div>
  </div>

  <!-- ═══ 탭 4: 할인 이벤트 ═══ -->
  <div v-else-if="activeTab === 'promotions'">
    <div class="card overflow-hidden mb-4">
      <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
        <div>
          <div class="font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="tag" :size="15" /> 할인 이벤트</div>
          <div class="text-[11px] text-ink-muted mt-0.5">기간 + 할인% 설정 — 광고/패키지 가격이 자동 할인됨</div>
        </div>
        <button @click="openPromoForm()" class="btn-primary !px-3 !py-1 text-xs"><AppIcon name="plus" :size="13" /> 새 이벤트</button>
      </div>

      <!-- 폼 -->
      <div v-if="showPromoForm" class="px-4 py-4 border-b border-gray-50 bg-amber-50 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <div>
            <label class="input-label">이벤트 제목 <span class="text-red-500">*</span></label>
            <input v-model="promoForm.title" placeholder="예: 신년 40% 할인" class="input-soft !px-3 !py-1.5 text-sm" />
          </div>
          <div>
            <label class="input-label">할인 % (0~100)</label>
            <input v-model.number="promoForm.discount_pct" type="number" min="0" max="100" class="input-soft !px-3 !py-1.5 text-sm" />
          </div>
          <div>
            <label class="input-label">시작 일시</label>
            <input v-model="promoForm.starts_at" type="datetime-local" class="input-soft !px-3 !py-1.5 text-sm" />
          </div>
          <div>
            <label class="input-label">종료 일시</label>
            <input v-model="promoForm.ends_at" type="datetime-local" class="input-soft !px-3 !py-1.5 text-sm" />
          </div>
        </div>
        <div class="flex items-center gap-4 text-sm text-ink-light">
          <span class="text-[11px] font-bold text-ink-light">적용 대상:</span>
          <label class="flex items-center gap-1.5">
            <input v-model="promoForm.applies_to_ads" type="checkbox" class="accent-amber-500 w-4 h-4" />
            <AppIcon name="megaphone" :size="14" /> 광고
          </label>
          <label class="flex items-center gap-1.5">
            <input v-model="promoForm.applies_to_packages" type="checkbox" class="accent-amber-500 w-4 h-4" />
            <AppIcon name="wallet" :size="14" /> 포인트 패키지
          </label>
          <label class="flex items-center gap-1.5 ml-4">
            <input v-model="promoForm.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
            활성화
          </label>
        </div>
        <div class="flex gap-2">
          <button @click="savePromo" :disabled="savingPromo" class="btn-primary !px-4 !py-1.5 text-xs">
            {{ savingPromo ? '저장중...' : (editPromoId ? '수정' : '등록') }}
          </button>
          <button @click="resetPromoForm" class="btn-ghost !px-3 !py-1.5 text-xs">취소</button>
        </div>
      </div>

      <!-- 목록 -->
      <div v-for="p in promotions" :key="p.id" class="px-4 py-3 border-b border-gray-50 flex items-center gap-3 hover:bg-amber-50/40 transition-colors">
        <div class="text-2xl font-black text-red-500 w-14 text-center">-{{ p.discount_pct }}%</div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-bold text-ink truncate">{{ p.title }}</div>
          <div class="text-[11px] text-ink-muted">
            {{ fmtDate(p.starts_at) }} ~ {{ fmtDate(p.ends_at) }}
            · {{ p.applies_to_ads ? '광고' : '' }}{{ p.applies_to_ads && p.applies_to_packages ? ' + ' : '' }}{{ p.applies_to_packages ? '패키지' : '' }}
          </div>
        </div>
        <span v-if="isActiveNow(p)" class="badge-green">진행중</span>
        <span v-else-if="!p.is_active" class="badge-gray">비활성</span>
        <span v-else class="badge-gray">대기/종료</span>
        <button @click="editPromo(p)" class="text-xs text-amber-600 hover:text-amber-700 transition-colors">수정</button>
        <button @click="deletePromo(p)" class="text-xs text-red-400 hover:text-red-600 transition-colors">삭제</button>
      </div>
      <div v-if="!promotions.length" class="py-16 text-center">
        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="tag" :size="28" :stroke-width="1.5" /></div>
        <p class="text-sm text-ink-muted">등록된 이벤트가 없습니다</p>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'

const siteStore = useSiteStore()

// ─── 공용 상태 ───────────────────────────────────────────────
const loading = ref(true)
const activeTab = ref('points')
const tabs = [
  { key: 'points',     icon: 'coins',     label: '포인트 설정' },
  { key: 'packages',   icon: 'wallet',    label: '포인트 패키지' },
  { key: 'ads',        icon: 'megaphone', label: '광고 가격' },
  { key: 'promotions', icon: 'tag',       label: '할인 이벤트' },
]

// ─── 포인트 설정 ────────────────────────────────────────────
const grouped = ref({})
const savingPoints = ref(false)
const pointMsg = ref('')
const pointMsgOk = ref(false)
const pointCategories = ['earn','spend','image','spam','auction']
const catStyles = {
  earn:    { icon: 'gift',   label: '포인트 적립',       bg: 'bg-green-50 text-green-800' },
  spend:   { icon: 'coins',  label: '포인트 사용(차감)',  bg: 'bg-red-50 text-red-800' },
  image:   { icon: 'image',  label: '이미지 업로드',      bg: 'bg-blue-50 text-blue-800' },
  spam:    { icon: 'shield', label: '스팸 방지',          bg: 'bg-orange-50 text-orange-800' },
  auction: { icon: 'store',  label: '업소록 옥션',        bg: 'bg-purple-50 text-purple-800' },
  package: { icon: 'wallet', label: '구매 패키지',        bg: 'bg-amber-50 text-amber-800' },
}

async function loadPoints() {
  try {
    const { data } = await axios.get('/api/admin/point-settings')
    grouped.value = data.data || {}
  } catch {}
}

async function savePointSettings() {
  savingPoints.value = true; pointMsg.value = ''
  const all = Object.values(grouped.value).flat().map(i => ({ key: i.key, value: i.value }))
  try {
    await axios.post('/api/admin/point-settings', { settings: all })
    pointMsg.value = '저장됨!'; pointMsgOk.value = true
  } catch (e) {
    pointMsg.value = e.response?.data?.message || '저장 실패'; pointMsgOk.value = false
  }
  savingPoints.value = false
  setTimeout(() => pointMsg.value = '', 2500)
}

// ─── 광고 가격 ──────────────────────────────────────────────
const pageConfig = ref({})
const minPrices = ref({ left_premium: 8000, left_standard: 7000, left_economy: 4000, right_premium: 10000, right_economy: 6000 })
const geoMarkup = ref({ state: 2000, national: 3000 })
const savingAds = ref(false)
const savingPageConfig = ref(false)
const adPriceMsg = ref('')
const pageConfigMsg = ref('')

async function loadAdSettings() {
  try {
    const { data } = await axios.get('/api/admin/ad-settings')
    pageConfig.value = data.data || {}
  } catch {}
  try {
    const { data } = await axios.get('/api/ad-settings/public')
    if (data.data?.slot_min_prices) minPrices.value = { ...minPrices.value, ...data.data.slot_min_prices }
    if (data.data?.geo_markup) geoMarkup.value = { ...geoMarkup.value, ...data.data.geo_markup }
  } catch {}
}

// 광고 슬롯 설정 대상 메뉴: 관리자에서 활성화된 모든 메뉴 (admin_only 는 제외)
const adEligibleMenus = computed(() => {
  const mc = siteStore.menuConfig
  if (!mc || !Array.isArray(mc)) return []
  return mc.filter(m => m.enabled !== false && !m.admin_only)
})

// 특정 메뉴의 슬롯 설정 (없으면 0/0 기본값)
function slotOf(key) {
  return pageConfig.value[key] || { left_slots: 0, right_slots: 0 }
}

function setSlot(menu, field, value) {
  const existing = pageConfig.value[menu.key] || { left_slots: 0, right_slots: 0, label: menu.label }
  pageConfig.value = {
    ...pageConfig.value,
    [menu.key]: { ...existing, label: menu.label, [field]: Number(value) },
  }
}

function isPageOff(key) {
  const cfg = pageConfig.value[key]
  if (!cfg) return true
  return (cfg.left_slots || 0) === 0 && (cfg.right_slots || 0) === 0
}

async function saveAdPrices() {
  savingAds.value = true; adPriceMsg.value = ''
  try {
    await axios.post('/api/admin/ad-slot-prices', { prices: minPrices.value, geo_markup: geoMarkup.value })
    adPriceMsg.value = '저장됨!'
  } catch { adPriceMsg.value = '저장 실패' }
  savingAds.value = false
  setTimeout(() => adPriceMsg.value = '', 2500)
}

async function savePageConfig() {
  savingPageConfig.value = true; pageConfigMsg.value = ''
  // 활성 메뉴 기준으로 최종 config 구성 (신규 메뉴는 0/0, 사라진 메뉴는 유지하되 덮어쓰기 가능)
  const finalConfig = { ...pageConfig.value }
  adEligibleMenus.value.forEach(menu => {
    if (!finalConfig[menu.key]) {
      finalConfig[menu.key] = { left_slots: 0, right_slots: 0, label: menu.label }
    } else if (!finalConfig[menu.key].label) {
      finalConfig[menu.key].label = menu.label
    }
  })
  try {
    await axios.post('/api/admin/ad-settings', { config: finalConfig })
    pageConfig.value = finalConfig
    pageConfigMsg.value = '저장됨!'
  } catch { pageConfigMsg.value = '저장 실패' }
  savingPageConfig.value = false
  setTimeout(() => pageConfigMsg.value = '', 2500)
}

// ─── 할인 이벤트 ────────────────────────────────────────────
const promotions = ref([])
const showPromoForm = ref(false)
const editPromoId = ref(null)
const savingPromo = ref(false)
const DEFAULT_PROMO = {
  title: '', discount_pct: 20,
  applies_to_ads: true, applies_to_packages: true,
  starts_at: '', ends_at: '', is_active: true,
}
const promoForm = ref({ ...DEFAULT_PROMO })

async function loadPromos() {
  try {
    const { data } = await axios.get('/api/admin/pricing-promotions')
    promotions.value = data.data || []
  } catch {}
}

function openPromoForm() {
  resetPromoForm()
  showPromoForm.value = true
}

function resetPromoForm() {
  editPromoId.value = null
  promoForm.value = { ...DEFAULT_PROMO }
  showPromoForm.value = false
}

function editPromo(p) {
  editPromoId.value = p.id
  promoForm.value = {
    title: p.title,
    discount_pct: p.discount_pct,
    applies_to_ads: !!p.applies_to_ads,
    applies_to_packages: !!p.applies_to_packages,
    starts_at: p.starts_at ? p.starts_at.substring(0, 16) : '',
    ends_at: p.ends_at ? p.ends_at.substring(0, 16) : '',
    is_active: !!p.is_active,
  }
  showPromoForm.value = true
}

async function savePromo() {
  if (!promoForm.value.title) { alert('제목을 입력하세요'); return }
  if (!promoForm.value.starts_at || !promoForm.value.ends_at) { alert('기간을 지정하세요'); return }
  if (!promoForm.value.applies_to_ads && !promoForm.value.applies_to_packages) {
    alert('적용 대상 하나 이상 선택하세요'); return
  }
  savingPromo.value = true
  try {
    const url = editPromoId.value ? `/api/admin/pricing-promotions/${editPromoId.value}` : '/api/admin/pricing-promotions'
    const method = editPromoId.value ? 'put' : 'post'
    await axios[method](url, promoForm.value)
    resetPromoForm()
    await loadPromos()
  } catch (err) {
    alert('저장 실패: ' + (err.response?.data?.message || err.message))
  } finally {
    savingPromo.value = false
  }
}

async function deletePromo(p) {
  if (!confirm(`'${p.title}' 삭제?`)) return
  try {
    await axios.delete(`/api/admin/pricing-promotions/${p.id}`)
    promotions.value = promotions.value.filter(x => x.id !== p.id)
  } catch {}
}

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

function isActiveNow(p) {
  if (!p.is_active) return false
  const now = Date.now()
  const s = new Date(p.starts_at).getTime()
  const e = new Date(p.ends_at).getTime()
  return now >= s && now <= e
}

// ─── 초기 로드 ──────────────────────────────────────────────
onMounted(async () => {
  siteStore.load() // 활성 메뉴 리스트 필요
  await Promise.all([loadPoints(), loadAdSettings(), loadPromos()])
  loading.value = false
})
</script>
