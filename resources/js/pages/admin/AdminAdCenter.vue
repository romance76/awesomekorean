<template>
<div>
  <!-- 헤더 -->
  <div class="mb-4 flex items-start justify-between">
    <div>
      <div class="text-xs text-gray-500">관리자 › 광고 센터</div>
      <h1 class="text-2xl font-black text-gray-800 mt-1">📢 광고 센터 — 통합 관리</h1>
      <p class="text-xs text-gray-400 mt-0.5">페이지/위치별 슬롯 맵 · 광고주 · 결제 · 승인까지 한 화면에서</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <button @click="showPreview = true" class="bg-purple-500 text-white rounded-lg px-3 py-2 text-sm font-bold hover:bg-purple-600">
        🔍 샘플 배너 위치 확인
      </button>
      <RouterLink to="/admin/banners" class="bg-white border rounded-lg px-3 py-2 text-sm hover:bg-gray-50">📋 전체 광고 목록</RouterLink>
      <RouterLink to="/admin/ad-settings" class="bg-white border rounded-lg px-3 py-2 text-sm hover:bg-gray-50">⚙️ 슬롯·가격 설정</RouterLink>
      <RouterLink to="/admin/hero-banners" class="bg-white border rounded-lg px-3 py-2 text-sm hover:bg-gray-50">🎪 히어로 배너</RouterLink>
    </div>
  </div>

  <!-- 샘플 배너 위치 미리보기 모달 -->
  <BannerPreviewModal v-if="showPreview" @close="showPreview = false" />

  <!-- KPI -->
  <div v-if="overview" class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-4">
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">전체 광고</div>
      <div class="text-xl font-bold">{{ overview.total }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">활성</div>
      <div class="text-xl font-bold text-green-600">{{ overview.active }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">대기</div>
      <div class="text-xl font-bold text-yellow-600">{{ overview.pending }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">매출 (P)</div>
      <div class="text-xl font-bold text-purple-600">{{ Number(overview.revenue_p||0).toLocaleString() }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">총 노출</div>
      <div class="text-xl font-bold text-blue-600">{{ Number(overview.total_impressions||0).toLocaleString() }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">총 클릭</div>
      <div class="text-xl font-bold text-red-600">{{ Number(overview.total_clicks||0).toLocaleString() }}</div>
    </div>
  </div>

  <!-- 필터 바 -->
  <div class="bg-white rounded-lg border p-3 mb-4">
    <div class="flex flex-wrap gap-2 items-center">
      <!-- 페이지 선택 -->
      <div class="flex items-center gap-1">
        <span class="text-xs font-bold text-gray-600">📄 페이지:</span>
        <select v-model="filter.page" @change="loadSlotMap" class="border rounded-lg px-3 py-1.5 text-sm">
          <option v-for="(p, key) in overview?.pages || {}" :key="key" :value="key">
            {{ p.icon }} {{ p.label }} <template v-if="overview?.page_counts?.[key]">({{ overview.page_counts[key] }})</template>
          </option>
        </select>
      </div>

      <!-- 지역 범위 (지역 기반 페이지만) -->
      <div v-if="overview?.pages?.[filter.page]?.geo" class="flex items-center gap-1 border-l pl-3">
        <span class="text-xs font-bold text-gray-600">📍 범위:</span>
        <div class="flex gap-1">
          <button v-for="s in ['all','state','county','city']" :key="s"
            @click="filter.geo_scope = s; filter.geo_value = ''; loadSlotMap()"
            class="text-xs px-3 py-1 rounded-full border transition"
            :class="filter.geo_scope === s ? 'bg-amber-400 text-amber-900 border-amber-500 font-bold' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'">
            {{ {all:'전국',state:'주',county:'카운티',city:'시티'}[s] }}
          </button>
        </div>
      </div>
      <input v-if="filter.geo_scope !== 'all' && overview?.pages?.[filter.page]?.geo"
        v-model="filter.geo_value" @change="loadSlotMap"
        :placeholder="filter.geo_scope === 'state' ? '예: CA, NY' : filter.geo_scope === 'county' ? '예: Gwinnett' : '예: Duluth'"
        class="border rounded-lg px-3 py-1.5 text-sm w-40" />

      <!-- 요약 -->
      <div v-if="slotMap" class="ml-auto flex items-center gap-3 text-xs">
        <span>슬롯 <strong class="text-gray-800">{{ slotMap.summary.total_slots }}</strong>개</span>
        <span class="text-green-600">채워짐 <strong>{{ slotMap.summary.filled }}</strong></span>
        <span class="text-yellow-600">대기 <strong>{{ slotMap.summary.pending }}</strong></span>
        <span class="text-gray-500">비어있음 <strong>{{ slotMap.summary.empty }}</strong></span>
      </div>
    </div>
  </div>

  <!-- 슬롯 맵 시각화 (유저 신청 화면과 동일 레이아웃) -->
  <div v-if="slotMap" class="bg-gradient-to-b from-white to-amber-50/20 rounded-xl border overflow-hidden mb-4">
    <div class="bg-gradient-to-r from-amber-100 to-orange-100 px-4 py-2 font-bold text-sm">
      🎯 AwesomeKorean — {{ slotMap.page_label }}
      <span v-if="slotMap.geo_value" class="ml-2 text-xs text-gray-600">({{ slotMap.geo_scope }}: {{ slotMap.geo_value }})</span>
    </div>

    <div class="p-4 grid grid-cols-12 gap-3">
      <!-- 왼쪽 카테고리 컬럼 -->
      <div class="col-span-3 space-y-2">
        <div class="text-xs bg-white border rounded-lg px-3 py-2 text-gray-600">📂 카테고리</div>
        <div v-for="tier in slotMap.slots.left" :key="tier.tier">
          <div v-for="s in tier.slots" :key="`L${tier.tier}${s.index}`"
            class="relative border-2 rounded-lg p-2 cursor-pointer transition hover:shadow-md"
            :class="tier.tier === 'premium' ? 'border-amber-400 bg-amber-50/50' : tier.tier === 'standard' ? 'border-blue-300 bg-blue-50/50' : 'border-green-300 bg-green-50/50'"
            @click="openSlot(s, tier, 'left')">
            <div class="text-center">
              <div class="text-[10px] font-bold mb-1"
                :class="tier.tier === 'premium' ? 'text-amber-700' : tier.tier === 'standard' ? 'text-blue-700' : 'text-green-700'">
                {{ {premium:'🥇 프리미엄',standard:'🥈 스탠다드',economy:'🥉 이코노미'}[tier.tier] }}
                <span class="text-gray-400">#{{ s.index }}</span>
              </div>
              <template v-if="s.ad">
                <img v-if="s.ad.image_url" :src="s.ad.image_url" class="w-full h-16 object-cover rounded mb-1" @error="e=>e.target.style.display='none'" />
                <div class="text-xs font-bold truncate">{{ s.ad.title }}</div>
                <div class="text-[9px] text-gray-600 truncate">{{ s.ad.user.name }}</div>
                <div class="flex justify-between text-[9px] mt-1">
                  <span>👁 {{ s.ad.impressions }}</span>
                  <span>🖱 {{ s.ad.clicks }}</span>
                </div>
                <span class="absolute top-0.5 right-0.5 text-[9px] px-1 rounded" :class="statusBadge(s.ad.status)">{{ s.ad.status }}</span>
              </template>
              <div v-else class="text-gray-400 text-[10px] py-4">
                ➕ 비어있음<br>
                <span class="text-gray-500">{{ tier.size }}</span><br>
                <span class="text-amber-600 font-bold">{{ tier.price.toLocaleString() }}P/월</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 중앙 콘텐츠 -->
      <div class="col-span-6 bg-white rounded-lg border p-4 min-h-[400px] flex items-center justify-center text-gray-400 text-sm">
        메인 콘텐츠 영역
      </div>

      <!-- 오른쪽 인기글 컬럼 -->
      <div class="col-span-3 space-y-2">
        <div class="text-xs bg-white border rounded-lg px-3 py-2 text-gray-600">🔥 인기글</div>
        <div v-for="tier in slotMap.slots.right" :key="tier.tier">
          <div v-for="s in tier.slots" :key="`R${tier.tier}${s.index}`"
            class="relative border-2 rounded-lg p-2 cursor-pointer transition hover:shadow-md"
            :class="tier.tier === 'premium' ? 'border-amber-400 bg-amber-50/50' : 'border-green-300 bg-green-50/50'"
            @click="openSlot(s, tier, 'right')">
            <div class="text-center">
              <div class="text-[10px] font-bold mb-1"
                :class="tier.tier === 'premium' ? 'text-amber-700' : 'text-green-700'">
                {{ {premium:'🥇 프리미엄',economy:'🥉 이코노미'}[tier.tier] }}
                <span class="text-gray-400">#{{ s.index }}</span>
              </div>
              <template v-if="s.ad">
                <img v-if="s.ad.image_url" :src="s.ad.image_url" class="w-full h-20 object-cover rounded mb-1" @error="e=>e.target.style.display='none'" />
                <div class="text-xs font-bold truncate">{{ s.ad.title }}</div>
                <div class="text-[9px] text-gray-600 truncate">{{ s.ad.user.name }}</div>
                <div class="flex justify-between text-[9px] mt-1">
                  <span>👁 {{ s.ad.impressions }}</span>
                  <span>🖱 {{ s.ad.clicks }}</span>
                </div>
                <span class="absolute top-0.5 right-0.5 text-[9px] px-1 rounded" :class="statusBadge(s.ad.status)">{{ s.ad.status }}</span>
              </template>
              <div v-else class="text-gray-400 text-[10px] py-6">
                ➕ 비어있음<br>
                <span class="text-gray-500">{{ tier.size }}</span><br>
                <span class="text-amber-600 font-bold">{{ tier.price.toLocaleString() }}P/월</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 범례 -->
    <div class="px-4 pb-4 flex gap-2 text-[10px]">
      <div class="flex-1 bg-amber-50 border border-amber-200 rounded p-2">
        <strong class="text-amber-700">🥇 프리미엄</strong> 한 달 독점, 100% 보장
      </div>
      <div class="flex-1 bg-blue-50 border border-blue-200 rounded p-2">
        <strong class="text-blue-700">🥈 스탠다드</strong> 2명 랜덤 교대, ~50%
      </div>
      <div class="flex-1 bg-green-50 border border-green-200 rounded p-2">
        <strong class="text-green-700">🥉 이코노미</strong> 3~5명 랜덤, 부담없는 가격
      </div>
    </div>
  </div>

  <!-- 광고 상세 모달 -->
  <div v-if="selectedAd" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="selectedAd=null">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-amber-50 to-orange-50 px-5 py-3 border-b flex justify-between items-center sticky top-0 z-10">
        <div>
          <div class="text-[10px] text-gray-500">광고 #{{ selectedAd.id }}</div>
          <div class="font-black text-lg">{{ selectedAd.title }}</div>
        </div>
        <button @click="selectedAd=null" class="text-gray-400 text-2xl">✕</button>
      </div>

      <div v-if="adDetail" class="p-5">
        <!-- 이미지 -->
        <img v-if="adDetail.ad.image_url" :src="adDetail.ad.image_url" class="w-full max-h-48 object-contain rounded mb-3 bg-gray-100" />

        <!-- 광고주 -->
        <div class="bg-gray-50 rounded-lg p-3 mb-3">
          <div class="text-xs font-bold text-gray-700 mb-2">👤 광고주</div>
          <div class="flex items-center justify-between">
            <div>
              <div class="font-bold text-sm">{{ adDetail.ad.user?.nickname || adDetail.ad.user?.name }}</div>
              <div class="text-xs text-gray-500">{{ adDetail.ad.user?.email }}</div>
              <div class="text-xs text-gray-500">{{ adDetail.ad.user?.city }}, {{ adDetail.ad.user?.state }}</div>
            </div>
            <button @click="$router.push(`/admin/members?id=${adDetail.ad.user?.id}`)" class="text-xs bg-blue-500 text-white px-3 py-1 rounded">회원 상세</button>
          </div>
        </div>

        <!-- 광고 정보 -->
        <div class="grid grid-cols-2 gap-2 text-xs mb-3">
          <div class="bg-white border rounded p-2">
            <div class="text-gray-500">페이지</div>
            <div class="font-bold">{{ adDetail.ad.page }} → {{ adDetail.ad.target_pages || '-' }}</div>
          </div>
          <div class="bg-white border rounded p-2">
            <div class="text-gray-500">위치 / 슬롯</div>
            <div class="font-bold">{{ adDetail.ad.position }} / S{{ adDetail.ad.slot_number }}</div>
          </div>
          <div class="bg-white border rounded p-2">
            <div class="text-gray-500">지역</div>
            <div class="font-bold">{{ adDetail.ad.geo_scope }}{{ adDetail.ad.geo_value ? ' · ' + adDetail.ad.geo_value : '' }}</div>
          </div>
          <div class="bg-white border rounded p-2">
            <div class="text-gray-500">기간</div>
            <div class="font-bold">{{ adDetail.ad.start_date }} ~ {{ adDetail.ad.end_date }}</div>
          </div>
          <div class="bg-purple-50 border border-purple-200 rounded p-2">
            <div class="text-gray-500">비용 (총)</div>
            <div class="font-black text-purple-700">{{ Number(adDetail.ad.total_cost||0).toLocaleString() }}P</div>
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded p-2">
            <div class="text-gray-500">입찰가 (월)</div>
            <div class="font-black text-blue-700">{{ Number(adDetail.ad.bid_amount||0).toLocaleString() }}P</div>
          </div>
        </div>

        <!-- 통계 -->
        <div class="grid grid-cols-3 gap-2 mb-3">
          <div class="bg-blue-50 rounded p-2 text-center">
            <div class="text-[10px] text-gray-500">노출</div>
            <div class="font-bold text-blue-700">{{ Number(adDetail.ad.impressions||0).toLocaleString() }}</div>
          </div>
          <div class="bg-red-50 rounded p-2 text-center">
            <div class="text-[10px] text-gray-500">클릭</div>
            <div class="font-bold text-red-700">{{ Number(adDetail.ad.clicks||0).toLocaleString() }}</div>
          </div>
          <div class="bg-green-50 rounded p-2 text-center">
            <div class="text-[10px] text-gray-500">CTR</div>
            <div class="font-bold text-green-700">{{ ctr(adDetail.ad) }}%</div>
          </div>
        </div>

        <!-- 관리 액션 -->
        <div class="flex gap-2 flex-wrap mb-4">
          <button v-if="adDetail.ad.status === 'pending'" @click="approveAd()" class="bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600">✅ 승인</button>
          <button v-if="adDetail.ad.status === 'pending'" @click="rejectAd()" class="bg-red-500 text-white px-3 py-2 rounded text-sm hover:bg-red-600">❌ 거절</button>
          <button v-if="adDetail.ad.status === 'active'" @click="pauseAd()" class="bg-orange-500 text-white px-3 py-2 rounded text-sm">⏸ 일시정지</button>
          <button v-if="adDetail.ad.link_url" @click="openLink" class="bg-blue-500 text-white px-3 py-2 rounded text-sm">🔗 링크 열기</button>
          <button @click="deleteAd()" class="bg-gray-500 text-white px-3 py-2 rounded text-sm ml-auto">🗑 삭제</button>
        </div>

        <!-- 이 광고주의 다른 광고 -->
        <div v-if="adDetail.other_ads?.length" class="border-t pt-3">
          <div class="text-xs font-bold text-gray-700 mb-2">📜 이 광고주의 다른 광고 ({{ adDetail.other_ads.length }})</div>
          <div class="space-y-1">
            <div v-for="o in adDetail.other_ads" :key="o.id" class="flex justify-between text-xs border-b last:border-0 py-1">
              <span class="truncate flex-1">{{ o.title }}</span>
              <span class="text-gray-500">{{ o.page }}/{{ o.position }}</span>
              <span class="w-20 text-right" :class="statusBadge(o.status).split(' ')[1]">{{ o.status }}</span>
              <span class="w-24 text-right font-bold">{{ Number(o.total_cost||0).toLocaleString() }}P</span>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="p-8 text-center text-gray-400">로딩중...</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import BannerPreviewModal from '../../components/BannerPreviewModal.vue'

const overview = ref(null)
const slotMap = ref(null)
const selectedAd = ref(null)
const adDetail = ref(null)
const showPreview = ref(false)

const filter = ref({ page: 'community', geo_scope: 'all', geo_value: '' })

async function loadOverview() {
  try {
    const { data } = await axios.get('/api/admin/ad-center/overview')
    overview.value = data.data
  } catch (e) { console.warn(e) }
}

async function loadSlotMap() {
  try {
    const params = { page: filter.value.page }
    if (filter.value.geo_scope !== 'all') {
      params.geo_scope = filter.value.geo_scope
      if (filter.value.geo_value) params.geo_value = filter.value.geo_value
    }
    const { data } = await axios.get('/api/admin/ad-center/slot-map', { params })
    slotMap.value = data.data
  } catch (e) { console.warn(e) }
}

async function openSlot(slot, tier, side) {
  if (!slot.ad) {
    alert('비어있는 슬롯입니다. 유저가 여기에 광고를 올릴 수 있습니다.')
    return
  }
  selectedAd.value = slot.ad
  adDetail.value = null
  try {
    const { data } = await axios.get(`/api/admin/ad-center/banner/${slot.ad.id}`)
    adDetail.value = data.data
  } catch (e) { console.warn(e) }
}

async function approveAd() {
  await axios.post(`/api/admin/banners/${adDetail.value.ad.id}/approve`)
  selectedAd.value = null; loadSlotMap(); loadOverview()
}
async function rejectAd() {
  const reason = prompt('거절 사유')
  if (!reason) return
  await axios.post(`/api/admin/banners/${adDetail.value.ad.id}/reject`, { reason })
  selectedAd.value = null; loadSlotMap(); loadOverview()
}
async function pauseAd() {
  await axios.post(`/api/admin/banners/${adDetail.value.ad.id}/pause`)
  selectedAd.value = null; loadSlotMap(); loadOverview()
}
async function deleteAd() {
  if (!confirm('삭제? (활성 광고는 포인트 환불됨)')) return
  await axios.delete(`/api/admin/banners/${adDetail.value.ad.id}`)
  selectedAd.value = null; loadSlotMap(); loadOverview()
}
function openLink() { window.open(adDetail.value.ad.link_url, '_blank') }

function ctr(a) {
  if (!a.impressions) return '0.00'
  return ((a.clicks / a.impressions) * 100).toFixed(2)
}

function statusBadge(s) {
  return {
    active: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    paused: 'bg-orange-100 text-orange-700',
    rejected: 'bg-red-100 text-red-700',
    expired: 'bg-gray-100 text-gray-500',
  }[s] || 'bg-gray-100 text-gray-700'
}

onMounted(async () => {
  await loadOverview()
  loadSlotMap()
})
</script>
