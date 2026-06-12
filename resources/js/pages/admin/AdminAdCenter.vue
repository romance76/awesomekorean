<template>
<div>
  <!-- 헤더 -->
  <div class="mb-4 flex items-start justify-between">
    <div>
      <div class="text-xs text-ink-muted">관리자 › 광고 센터</div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="megaphone" :size="20" /></span>
        광고 센터 — 통합 관리
      </h1>
      <p class="text-xs text-ink-muted mt-0.5">페이지/위치별 슬롯 맵 · 광고주 · 결제 · 승인까지 한 화면에서</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <button @click="showPreview = true" class="btn-primary !px-3 !py-2">
        <AppIcon name="search" :size="15" /> 샘플 배너 위치 확인
      </button>
      <RouterLink to="/admin/banners" class="btn-secondary !px-3 !py-2"><AppIcon name="list" :size="15" /> 전체 광고 목록</RouterLink>
      <RouterLink to="/admin/ad-settings" class="btn-secondary !px-3 !py-2"><AppIcon name="settings" :size="15" /> 슬롯·가격 설정</RouterLink>
      <RouterLink to="/admin/hero-banners" class="btn-secondary !px-3 !py-2"><AppIcon name="image" :size="15" /> 히어로 배너</RouterLink>
    </div>
  </div>

  <!-- 샘플 배너 위치 미리보기 모달 -->
  <BannerPreviewModal v-if="showPreview" @close="showPreview = false" />

  <!-- KPI -->
  <div v-if="overview" class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-4">
    <div class="card p-3">
      <div class="text-xs text-ink-muted">전체 광고</div>
      <div class="text-xl font-bold text-ink">{{ overview.total }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">활성</div>
      <div class="text-xl font-bold text-green-600">{{ overview.active }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">대기</div>
      <div class="text-xl font-bold text-yellow-600">{{ overview.pending }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">매출 (P)</div>
      <div class="text-xl font-bold text-purple-600">{{ Number(overview.revenue_p||0).toLocaleString() }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">총 노출</div>
      <div class="text-xl font-bold text-blue-600">{{ Number(overview.total_impressions||0).toLocaleString() }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">총 클릭</div>
      <div class="text-xl font-bold text-red-600">{{ Number(overview.total_clicks||0).toLocaleString() }}</div>
    </div>
  </div>

  <!-- 필터 바 -->
  <div class="card p-3 mb-4">
    <div class="flex flex-wrap gap-2 items-center">
      <!-- 페이지 선택 -->
      <div class="flex items-center gap-1.5">
        <span class="text-xs font-bold text-ink-light flex items-center gap-1"><AppIcon name="list" :size="13" /> 페이지:</span>
        <select v-model="filter.page" @change="loadSlotMap" class="input-soft !w-auto !px-3 !py-1.5 text-sm">
          <option v-for="(p, key) in overview?.pages || {}" :key="key" :value="key">
            {{ p.icon }} {{ p.label }} <template v-if="overview?.page_counts?.[key]">({{ overview.page_counts[key] }})</template>
          </option>
        </select>
      </div>

      <!-- 지역 범위 (지역 기반 페이지만) -->
      <div v-if="overview?.pages?.[filter.page]?.geo" class="flex items-center gap-1.5 border-l border-gray-100 pl-3">
        <span class="text-xs font-bold text-ink-light flex items-center gap-1"><AppIcon name="map-pin" :size="13" /> 범위:</span>
        <div class="flex gap-1">
          <button v-for="s in ['all','state','county','city']" :key="s"
            @click="filter.geo_scope = s; filter.geo_value = ''; loadSlotMap()"
            class="text-xs px-3 py-1 rounded-full border transition-colors"
            :class="filter.geo_scope === s ? 'bg-amber-400 text-white border-amber-400 font-bold' : 'bg-white text-ink-light border-gray-200 hover:bg-gray-50'">
            {{ {all:'전국',state:'주',county:'카운티',city:'시티'}[s] }}
          </button>
        </div>
      </div>
      <input v-if="filter.geo_scope !== 'all' && overview?.pages?.[filter.page]?.geo"
        v-model="filter.geo_value" @change="loadSlotMap"
        :placeholder="filter.geo_scope === 'state' ? '예: CA, NY' : filter.geo_scope === 'county' ? '예: Gwinnett' : '예: Duluth'"
        class="input-soft !w-40 !px-3 !py-1.5 text-sm" />

      <!-- 요약 -->
      <div v-if="slotMap" class="ml-auto flex items-center gap-3 text-xs">
        <span class="text-ink-light">슬롯 <strong class="text-ink">{{ slotMap.summary.total_slots }}</strong>개</span>
        <span class="text-green-600">채워짐 <strong>{{ slotMap.summary.filled }}</strong></span>
        <span class="text-yellow-600">대기 <strong>{{ slotMap.summary.pending }}</strong></span>
        <span class="text-ink-muted">비어있음 <strong>{{ slotMap.summary.empty }}</strong></span>
      </div>
    </div>
  </div>

  <!-- 슬롯 맵 시각화 (유저 신청 화면과 동일 레이아웃) -->
  <div v-if="slotMap" class="card overflow-hidden mb-4">
    <div class="bg-amber-50 px-4 py-3 font-bold text-sm text-ink flex items-center gap-2 flex-wrap">
      <span class="icon-chip w-7 h-7 bg-white text-amber-600"><AppIcon name="megaphone" :size="14" /></span>
      AwesomeKorean — {{ slotMap.page_label }}
      <span v-if="slotMap.geo_value" class="text-xs text-ink-light font-normal">({{ slotMap.geo_scope }}: {{ slotMap.geo_value }})</span>
    </div>

    <div class="p-4 grid grid-cols-12 gap-3">
      <!-- 왼쪽 카테고리 컬럼 -->
      <div class="col-span-3 space-y-2">
        <div class="text-xs bg-gray-50 rounded-lg px-3 py-2 text-ink-light flex items-center gap-1.5"><AppIcon name="list" :size="13" /> 카테고리</div>
        <div v-for="tier in slotMap.slots.left" :key="tier.tier">
          <div v-for="s in tier.slots" :key="`L${tier.tier}${s.index}`"
            class="relative border-2 rounded-xl p-2 cursor-pointer transition-shadow hover:shadow-lift"
            :class="tier.tier === 'premium' ? 'border-amber-400 bg-amber-50/50' : tier.tier === 'standard' ? 'border-blue-300 bg-blue-50/50' : 'border-green-300 bg-green-50/50'"
            @click="openSlot(s, tier, 'left')">
            <div class="text-center">
              <div class="text-[11px] font-bold mb-1"
                :class="tier.tier === 'premium' ? 'text-amber-700' : tier.tier === 'standard' ? 'text-blue-700' : 'text-green-700'">
                {{ {premium:'🥇 프A',standard:'🥈 스A',economy:'🥉 이코노미'}[tier.tier] }}
                <span class="text-ink-faint">#{{ s.index }}</span>
              </div>
              <template v-if="s.ad">
                <img v-if="s.ad.image_url" :src="s.ad.image_url" class="w-full h-16 object-cover rounded-lg mb-1" @error="e=>e.target.style.display='none'" />
                <div class="text-xs font-bold text-ink truncate">{{ s.ad.title }}</div>
                <div class="text-[11px] text-ink-light truncate">{{ s.ad.user.name }}</div>
                <div class="flex justify-between text-[11px] text-ink-muted mt-1">
                  <span class="inline-flex items-center gap-0.5"><AppIcon name="eye" :size="11" /> {{ s.ad.impressions }}</span>
                  <span class="inline-flex items-center gap-0.5"><AppIcon name="external-link" :size="11" /> {{ s.ad.clicks }}</span>
                </div>
                <span class="absolute top-0.5 right-0.5 text-[11px] px-1 rounded" :class="statusBadge(s.ad.status)">{{ s.ad.status }}</span>
              </template>
              <div v-else class="text-ink-faint text-[11px] py-4">
                <AppIcon name="plus" :size="14" class="mx-auto mb-0.5" /> 비어있음<br>
                <span class="text-ink-muted">{{ tier.size }}</span><br>
                <span class="text-amber-600 font-bold">{{ tier.price.toLocaleString() }}P/월</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 중앙 콘텐츠 -->
      <div class="col-span-6 bg-gray-50 rounded-xl p-4 min-h-[400px] flex items-center justify-center text-ink-faint text-sm">
        메인 콘텐츠 영역
      </div>

      <!-- 오른쪽 인기글 컬럼 -->
      <div class="col-span-3 space-y-2">
        <div class="text-xs bg-gray-50 rounded-lg px-3 py-2 text-ink-light flex items-center gap-1.5"><AppIcon name="flame" :size="13" /> 인기글</div>
        <div v-for="tier in slotMap.slots.right" :key="tier.tier">
          <div v-for="s in tier.slots" :key="`R${tier.tier}${s.index}`"
            class="relative border-2 rounded-xl p-2 cursor-pointer transition-shadow hover:shadow-lift"
            :class="tier.tier === 'premium' ? 'border-amber-400 bg-amber-50/50' : 'border-green-300 bg-green-50/50'"
            @click="openSlot(s, tier, 'right')">
            <div class="text-center">
              <div class="text-[11px] font-bold mb-1"
                :class="tier.tier === 'premium' ? 'text-amber-700' : tier.tier === 'standard' ? 'text-blue-700' : 'text-green-700'">
                {{ {premium:'🥇 프B',standard:'🥈 스B',economy:'🥉 이코노미'}[tier.tier] }}
                <span class="text-ink-faint">#{{ s.index }}</span>
              </div>
              <template v-if="s.ad">
                <img v-if="s.ad.image_url" :src="s.ad.image_url" class="w-full h-20 object-cover rounded-lg mb-1" @error="e=>e.target.style.display='none'" />
                <div class="text-xs font-bold text-ink truncate">{{ s.ad.title }}</div>
                <div class="text-[11px] text-ink-light truncate">{{ s.ad.user.name }}</div>
                <div class="flex justify-between text-[11px] text-ink-muted mt-1">
                  <span class="inline-flex items-center gap-0.5"><AppIcon name="eye" :size="11" /> {{ s.ad.impressions }}</span>
                  <span class="inline-flex items-center gap-0.5"><AppIcon name="external-link" :size="11" /> {{ s.ad.clicks }}</span>
                </div>
                <span class="absolute top-0.5 right-0.5 text-[11px] px-1 rounded" :class="statusBadge(s.ad.status)">{{ s.ad.status }}</span>
              </template>
              <div v-else class="text-ink-faint text-[11px] py-6">
                <AppIcon name="plus" :size="14" class="mx-auto mb-0.5" /> 비어있음<br>
                <span class="text-ink-muted">{{ tier.size }}</span><br>
                <span class="text-amber-600 font-bold">{{ tier.price.toLocaleString() }}P/월</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 범례: 프A/프B/스A/스B 고정 독점 -->
    <div class="px-4 pb-4 flex gap-2 text-xs flex-wrap">
      <div class="flex-1 min-w-[180px] bg-amber-50 border border-amber-200 rounded-lg p-2 text-ink-light">
        <strong class="text-amber-700">🥇 프A / 프B</strong> 한 달 독점, 데스크톱 100% + 모바일 35%씩
      </div>
      <div class="flex-1 min-w-[180px] bg-blue-50 border border-blue-200 rounded-lg p-2 text-ink-light">
        <strong class="text-blue-700">🥈 스A / 스B</strong> 한 달 독점, 데스크톱 100% + 모바일 15%씩
      </div>
    </div>
  </div>

  <!-- 광고 상세 모달 -->
  <div v-if="selectedAd" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="selectedAd=null">
    <div class="bg-white rounded-2xl shadow-lift w-full max-w-2xl max-h-[90vh] overflow-y-auto">
      <div class="bg-amber-50 px-5 py-3 border-b border-gray-100 flex justify-between items-center sticky top-0 z-10">
        <div>
          <div class="text-xs text-ink-muted">광고 #{{ selectedAd.id }}</div>
          <div class="font-bold text-lg text-ink">{{ selectedAd.title }}</div>
        </div>
        <button @click="selectedAd=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="22" /></button>
      </div>

      <div v-if="adDetail" class="p-5">
        <!-- 이미지 -->
        <img v-if="adDetail.ad.image_url" :src="adDetail.ad.image_url" class="w-full max-h-48 object-contain rounded-lg mb-3 bg-gray-100" />

        <!-- 광고주 -->
        <div class="bg-gray-50 rounded-xl p-3 mb-3">
          <div class="text-xs font-bold text-ink-light mb-2 flex items-center gap-1"><AppIcon name="user" :size="13" /> 광고주</div>
          <div class="flex items-center justify-between">
            <div>
              <div class="font-bold text-sm text-ink">{{ adDetail.ad.user?.nickname || adDetail.ad.user?.name }}</div>
              <div class="text-xs text-ink-muted">{{ adDetail.ad.user?.email }}</div>
              <div class="text-xs text-ink-muted">{{ adDetail.ad.user?.city }}, {{ adDetail.ad.user?.state }}</div>
            </div>
            <button @click="$router.push(`/admin/members?id=${adDetail.ad.user?.id}`)" class="btn-soft !px-3 !py-1 text-xs">회원 상세</button>
          </div>
        </div>

        <!-- 광고 정보 -->
        <div class="grid grid-cols-2 gap-2 text-xs mb-3">
          <div class="border border-gray-100 rounded-lg p-2">
            <div class="text-ink-muted">페이지</div>
            <div class="font-bold text-ink">{{ adDetail.ad.page }} → {{ adDetail.ad.target_pages || '-' }}</div>
          </div>
          <div class="border border-gray-100 rounded-lg p-2">
            <div class="text-ink-muted">위치 / 슬롯</div>
            <div class="font-bold text-ink">{{ adDetail.ad.position }} / S{{ adDetail.ad.slot_number }}</div>
          </div>
          <div class="border border-gray-100 rounded-lg p-2">
            <div class="text-ink-muted">지역</div>
            <div class="font-bold text-ink">{{ adDetail.ad.geo_scope }}{{ adDetail.ad.geo_value ? ' · ' + adDetail.ad.geo_value : '' }}</div>
          </div>
          <div class="border border-gray-100 rounded-lg p-2">
            <div class="text-ink-muted">기간</div>
            <div class="font-bold text-ink">{{ adDetail.ad.start_date }} ~ {{ adDetail.ad.end_date }}</div>
          </div>
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-2">
            <div class="text-ink-muted">비용 (총)</div>
            <div class="font-black text-purple-700">{{ Number(adDetail.ad.total_cost||0).toLocaleString() }}P</div>
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-2">
            <div class="text-ink-muted">입찰가 (월)</div>
            <div class="font-black text-blue-700">{{ Number(adDetail.ad.bid_amount||0).toLocaleString() }}P</div>
          </div>
        </div>

        <!-- 통계 -->
        <div class="grid grid-cols-3 gap-2 mb-3">
          <div class="bg-blue-50 rounded-lg p-2 text-center">
            <div class="text-xs text-ink-muted">노출</div>
            <div class="font-bold text-blue-700">{{ Number(adDetail.ad.impressions||0).toLocaleString() }}</div>
          </div>
          <div class="bg-red-50 rounded-lg p-2 text-center">
            <div class="text-xs text-ink-muted">클릭</div>
            <div class="font-bold text-red-700">{{ Number(adDetail.ad.clicks||0).toLocaleString() }}</div>
          </div>
          <div class="bg-green-50 rounded-lg p-2 text-center">
            <div class="text-xs text-ink-muted">CTR</div>
            <div class="font-bold text-green-700">{{ ctr(adDetail.ad) }}%</div>
          </div>
        </div>

        <!-- 관리 액션 -->
        <div class="flex gap-2 flex-wrap mb-4">
          <button v-if="adDetail.ad.status === 'pending'" @click="approveAd()" class="inline-flex items-center gap-1.5 bg-green-500 text-white font-semibold px-3 py-2 rounded-xl text-sm transition-colors hover:bg-green-600"><AppIcon name="check" :size="15" /> 승인</button>
          <button v-if="adDetail.ad.status === 'pending'" @click="rejectAd()" class="inline-flex items-center gap-1.5 bg-red-500 text-white font-semibold px-3 py-2 rounded-xl text-sm transition-colors hover:bg-red-600"><AppIcon name="x" :size="15" /> 거절</button>
          <button v-if="adDetail.ad.status === 'active'" @click="pauseAd()" class="inline-flex items-center gap-1.5 bg-orange-500 text-white font-semibold px-3 py-2 rounded-xl text-sm transition-colors hover:bg-orange-600"><AppIcon name="clock" :size="15" /> 일시정지</button>
          <button v-if="adDetail.ad.link_url" @click="openLink" class="inline-flex items-center gap-1.5 bg-blue-500 text-white font-semibold px-3 py-2 rounded-xl text-sm transition-colors hover:bg-blue-600"><AppIcon name="external-link" :size="15" /> 링크 열기</button>
          <button @click="deleteAd()" class="btn-secondary !px-3 !py-2 ml-auto text-red-500"><AppIcon name="trash" :size="15" /> 삭제</button>
        </div>

        <!-- 이 광고주의 다른 광고 -->
        <div v-if="adDetail.other_ads?.length" class="border-t border-gray-100 pt-3">
          <div class="text-xs font-bold text-ink-light mb-2 flex items-center gap-1"><AppIcon name="list" :size="13" /> 이 광고주의 다른 광고 ({{ adDetail.other_ads.length }})</div>
          <div class="space-y-1">
            <div v-for="o in adDetail.other_ads" :key="o.id" class="flex justify-between text-xs border-b border-gray-50 last:border-0 py-1">
              <span class="truncate flex-1 text-ink-light">{{ o.title }}</span>
              <span class="text-ink-muted">{{ o.page }}/{{ o.position }}</span>
              <span class="w-20 text-right" :class="statusBadge(o.status).split(' ')[1]">{{ o.status }}</span>
              <span class="w-24 text-right font-bold text-ink">{{ Number(o.total_cost||0).toLocaleString() }}P</span>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="p-8 text-center text-ink-muted">로딩중...</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import BannerPreviewModal from '../../components/BannerPreviewModal.vue'
import AppIcon from '../../components/AppIcon.vue'

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
