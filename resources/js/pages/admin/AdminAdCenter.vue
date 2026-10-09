<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <!-- 바로가기 -->
  <div class="grid grid-cols-3 gap-2">
    <RouterLink to="/admin/banners" class="min-h-[52px] rounded-xl bg-white border border-gray-100 flex items-center justify-center text-center text-[14px] font-bold text-ink active:bg-amber-50 px-1">전체 광고 목록</RouterLink>
    <RouterLink to="/admin/ad-settings" class="min-h-[52px] rounded-xl bg-white border border-gray-100 flex items-center justify-center text-center text-[14px] font-bold text-ink active:bg-amber-50 px-1">슬롯·가격 설정</RouterLink>
    <RouterLink to="/admin/hero-banners" class="min-h-[52px] rounded-xl bg-white border border-gray-100 flex items-center justify-center text-center text-[14px] font-bold text-ink active:bg-amber-50 px-1">히어로 배너</RouterLink>
  </div>

  <!-- 요약 -->
  <div v-if="overview" class="flex gap-2 overflow-x-auto scrollbar-hide">
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">전체 광고</div><div class="text-[20px] font-black tabular-nums text-ink">{{ overview.total }}</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">활성</div><div class="text-[20px] font-black tabular-nums text-green-600">{{ overview.active }}</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border rounded-2xl px-3 py-2.5" :class="overview.pending > 0 ? 'border-amber-300' : 'border-gray-100'"><div class="text-[12px] text-ink-muted">대기</div><div class="text-[20px] font-black tabular-nums text-yellow-600">{{ overview.pending }}</div></div>
    <div class="shrink-0 min-w-[110px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">매출 (P)</div><div class="text-[20px] font-black tabular-nums text-purple-600">{{ Number(overview.revenue_p || 0).toLocaleString() }}</div></div>
    <div class="shrink-0 min-w-[100px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">총 노출</div><div class="text-[20px] font-black tabular-nums text-blue-600">{{ Number(overview.total_impressions || 0).toLocaleString() }}</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">총 클릭</div><div class="text-[20px] font-black tabular-nums text-red-600">{{ Number(overview.total_clicks || 0).toLocaleString() }}</div></div>
  </div>

  <!-- 페이지 / 지역 -->
  <select v-model="filter.page" @change="onPageChange" aria-label="광고 페이지" class="w-full min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink">
    <option v-for="(pg, key) in overview?.pages || {}" :key="key" :value="key">{{ pg.icon }} {{ pg.label }}<template v-if="overview?.page_counts?.[key]"> ({{ overview.page_counts[key] }})</template></option>
  </select>
  <div v-if="overview?.pages?.[filter.page]?.geo" class="space-y-2">
    <div class="grid grid-cols-4 gap-1 bg-gray-200/70 rounded-2xl p-1" role="group" aria-label="지역 범위">
      <button v-for="g in ['all','state','county','city']" :key="g" @click="filter.geo_scope = g; filter.geo_value = ''; loadSlotMap()" :aria-pressed="filter.geo_scope === g"
        class="min-h-[44px] rounded-xl text-[14px] font-bold" :class="filter.geo_scope === g ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">{{ {all:'전국',state:'주',county:'카운티',city:'시티'}[g] }}</button>
    </div>
    <input v-if="filter.geo_scope !== 'all'" v-model="filter.geo_value" @change="loadSlotMap" @keyup.enter="loadSlotMap"
      :placeholder="filter.geo_scope === 'state' ? '예: CA, NY' : filter.geo_scope === 'county' ? '예: Gwinnett' : '예: Duluth'" aria-label="지역 이름"
      class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3" />
  </div>

  <!-- 애드센스 페이지 -->
  <div v-if="isAdsensePage" class="bg-blue-50 border border-blue-100 rounded-2xl p-4 text-[14px] text-ink-light leading-relaxed">
    <b class="text-ink">{{ overview?.pages?.[filter.page]?.label }}</b> 페이지는 메뉴 관리에서 "애드센스"로 지정돼 있어요. 일반 배너 슬롯 대신 Google 애드센스 광고가 나올 자리예요.
  </div>

  <!-- 슬롯 -->
  <template v-else-if="slotMap">
    <div class="flex items-center gap-x-3 gap-y-1 flex-wrap text-[13px] px-0.5">
      <span class="text-ink-light">슬롯 <b class="text-ink">{{ slotMap.summary.total_slots }}</b>개</span>
      <span class="text-green-600">채워짐 <b>{{ slotMap.summary.filled }}</b></span>
      <span class="text-yellow-600">대기 <b>{{ slotMap.summary.pending }}</b></span>
      <span class="text-ink-muted">비어있음 <b>{{ slotMap.summary.empty }}</b></span>
    </div>
    <div v-for="side in ['left','right']" :key="side" class="space-y-2">
      <div class="text-[14px] font-bold text-ink-light px-0.5 pt-1">{{ side === 'left' ? '좌측 · 카테고리 컬럼' : '우측 · 인기글 컬럼' }}</div>
      <template v-for="tier in slotMap.slots[side]" :key="side + tier.tier">
        <button v-for="sl in tier.slots" :key="side + tier.tier + sl.index" @click="openSlotMobile(sl)"
          class="w-full text-left rounded-2xl border-2 p-3 min-h-[76px] flex items-center gap-3 active:opacity-80"
          :class="tierBox(tier.tier)">
          <span class="shrink-0 w-[84px] h-[60px] rounded-xl overflow-hidden bg-white/70 grid place-items-center">
            <img v-if="sl.ad?.image_url" :src="sl.ad.image_url" alt="" loading="lazy" class="w-full h-full object-cover" @error="e => e.target.style.display = 'none'" />
            <AppIcon v-else-if="!sl.ad" name="plus" :size="22" class="text-ink-faint" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-1.5 flex-wrap">
              <span class="text-[13px] font-bold" :class="tierText(tier.tier)">{{ tierLabel(side, tier.tier) }} #{{ sl.index }}</span>
              <span v-if="sl.ad" class="text-[12px] px-2 py-0.5 rounded-full font-bold" :class="statusBadge(sl.ad.status)">{{ statusKo(sl.ad.status) }}</span>
            </span>
            <template v-if="sl.ad">
              <span class="block text-[15px] font-bold text-ink truncate">{{ sl.ad.title }}</span>
              <span class="block text-[13px] text-ink-muted truncate">{{ sl.ad.user?.name }} · 노출 {{ sl.ad.impressions }} · 클릭 {{ sl.ad.clicks }}</span>
            </template>
            <template v-else>
              <span class="block text-[15px] text-ink-muted">비어있음</span>
              <span class="block text-[13px] text-ink-muted">{{ tier.size }} · <b class="text-amber-600">{{ tier.price.toLocaleString() }}P/월</b></span>
            </template>
          </span>
        </button>
      </template>
    </div>
    <p class="text-[13px] text-ink-faint leading-relaxed px-0.5">🥇 프리미엄·🥈 스탠다드는 한 달 독점(프A/프B, 스A/스B)이에요. 이코노미는 여러 광고가 돌아가며 나와요.</p>
  </template>
  <div v-else class="text-center text-ink-muted py-10 text-[15px]">불러오는 중...</div>

  <!-- 광고 상세 / 처리 시트 -->
  <Teleport to="body">
    <div v-if="selectedAd" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>

        <div v-if="!adDetail" class="text-center text-ink-muted py-10 text-[15px]">불러오는 중...</div>

        <!-- 거절 사유 -->
        <div v-else-if="mode === 'reject'" class="space-y-3">
          <div class="text-[17px] font-bold text-ink">광고 거절</div>
          <div class="text-[14px] text-ink-muted break-words">‘{{ adDetail.ad.title }}’ — 거절하면 광고주에게 <b class="text-ink">{{ Number(adDetail.ad.total_cost || 0).toLocaleString() }}P</b>가 환불되고 사유가 전달돼요.</div>
          <div class="flex flex-wrap gap-2">
            <button v-for="r in rejectPresets" :key="r" type="button" @click="rejectReason = r"
              class="min-h-[44px] px-3 rounded-full border text-[14px]" :class="rejectReason === r ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200'">{{ r }}</button>
          </div>
          <textarea v-model="rejectReason" rows="3" maxlength="200" placeholder="거절 사유" aria-label="거절 사유" class="w-full rounded-xl border border-gray-200 px-3 py-3"></textarea>
          <button @click="doReject" :disabled="busy || !rejectReason.trim()" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ busy ? '처리 중...' : '거절하기' }}</button>
          <button @click="mode = ''" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">돌아가기</button>
        </div>

        <!-- 삭제 확인 -->
        <div v-else-if="mode === 'delete'" class="space-y-3">
          <div class="text-[17px] font-bold text-ink">광고를 삭제할까요?</div>
          <p class="text-[15px] text-ink-light leading-relaxed break-words">‘{{ adDetail.ad.title }}’<template v-if="['pending','active','paused'].includes(adDetail.ad.status)"><br />광고주에게 <b class="text-ink">{{ Number(adDetail.ad.total_cost || 0).toLocaleString() }}P</b>가 환불돼요.</template><template v-else><br />이미 {{ statusKo(adDetail.ad.status) }} 상태라 환불은 없어요.</template></p>
          <button @click="doDelete" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '처리 중...' : '삭제하기' }}</button>
          <button @click="mode = ''" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">돌아가기</button>
        </div>

        <!-- 상세 -->
        <div v-else class="space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div class="min-w-0"><div class="text-[13px] text-ink-muted">광고 #{{ adDetail.ad.id }}</div><div class="text-[17px] font-bold text-ink break-words">{{ adDetail.ad.title }}</div></div>
            <span class="shrink-0 text-[12px] px-2.5 py-1 rounded-full font-bold" :class="statusBadge(adDetail.ad.status)">{{ statusKo(adDetail.ad.status) }}</span>
          </div>
          <a v-if="adDetail.ad.image_url" :href="adDetail.ad.image_url" target="_blank" rel="noopener noreferrer" class="block rounded-2xl overflow-hidden bg-gray-100 border border-gray-100">
            <img :src="adDetail.ad.image_url" alt="광고 이미지" class="w-full max-h-[30vh] object-contain" />
          </a>
          <div class="bg-gray-50 rounded-2xl p-3.5 flex items-center justify-between gap-3">
            <div class="min-w-0"><div class="text-[13px] text-ink-muted">광고주</div><b class="block text-[15px] text-ink truncate">{{ adDetail.ad.user?.nickname || adDetail.ad.user?.name }}</b><span class="block text-[13px] text-ink-muted truncate">{{ adDetail.ad.user?.email }}</span><span v-if="adDetail.ad.user?.city" class="block text-[13px] text-ink-muted">{{ adDetail.ad.user?.city }}, {{ adDetail.ad.user?.state }}</span></div>
            <button v-if="adDetail.ad.user?.id" @click="goMember" class="shrink-0 min-h-[44px] px-3 rounded-xl bg-white border border-gray-200 text-[14px] font-bold text-ink">회원 상세</button>
          </div>
          <div class="grid grid-cols-2 gap-2 text-[14px]">
            <div class="border border-gray-100 rounded-xl p-2.5"><div class="text-[12px] text-ink-muted">페이지</div><b class="text-ink break-words">{{ adDetail.ad.page }}<template v-if="pagesText(adDetail.ad.target_pages)"> → {{ pagesText(adDetail.ad.target_pages) }}</template></b></div>
            <div class="border border-gray-100 rounded-xl p-2.5"><div class="text-[12px] text-ink-muted">위치 / 슬롯</div><b class="text-ink">{{ adDetail.ad.position }} / S{{ adDetail.ad.slot_number }}</b></div>
            <div class="border border-gray-100 rounded-xl p-2.5"><div class="text-[12px] text-ink-muted">지역</div><b class="text-ink">{{ adDetail.ad.geo_scope }}{{ adDetail.ad.geo_value ? ' · ' + adDetail.ad.geo_value : '' }}</b></div>
            <div class="border border-gray-100 rounded-xl p-2.5"><div class="text-[12px] text-ink-muted">기간</div><b class="text-ink text-[13px]">{{ day(adDetail.ad.start_date) }} ~ {{ day(adDetail.ad.end_date) }}</b></div>
            <div class="bg-purple-50 border border-purple-100 rounded-xl p-2.5"><div class="text-[12px] text-ink-muted">비용 (총)</div><b class="text-purple-700 tabular-nums">{{ Number(adDetail.ad.total_cost || 0).toLocaleString() }}P</b></div>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-2.5"><div class="text-[12px] text-ink-muted">입찰가 (월)</div><b class="text-blue-700 tabular-nums">{{ Number(adDetail.ad.bid_amount || 0).toLocaleString() }}P</b></div>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div class="bg-blue-50 rounded-xl p-2.5 text-center"><div class="text-[12px] text-ink-muted">노출</div><b class="text-blue-700 tabular-nums">{{ Number(adDetail.ad.impressions || 0).toLocaleString() }}</b></div>
            <div class="bg-red-50 rounded-xl p-2.5 text-center"><div class="text-[12px] text-ink-muted">클릭</div><b class="text-red-700 tabular-nums">{{ Number(adDetail.ad.clicks || 0).toLocaleString() }}</b></div>
            <div class="bg-green-50 rounded-xl p-2.5 text-center"><div class="text-[12px] text-ink-muted">CTR</div><b class="text-green-700 tabular-nums">{{ ctr(adDetail.ad) }}%</b></div>
          </div>
          <a v-if="adDetail.ad.link_url" :href="adDetail.ad.link_url" target="_blank" rel="noopener noreferrer nofollow" class="flex items-center gap-2 min-h-[48px] px-3 rounded-xl bg-blue-50 text-blue-700 text-[14px] font-bold break-all">
            <AppIcon name="external-link" :size="16" /><span class="min-w-0">링크 열어 보기 · {{ adDetail.ad.link_url }}</span>
          </a>
          <div class="grid grid-cols-2 gap-2">
            <button v-if="adDetail.ad.status === 'pending'" @click="doApprove" :disabled="busy" class="min-h-[52px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold disabled:opacity-50">승인</button>
            <button v-if="adDetail.ad.status === 'pending'" @click="rejectReason = ''; mode = 'reject'" :disabled="busy" class="min-h-[52px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">거절</button>
            <button v-if="adDetail.ad.status === 'active'" @click="doPause" :disabled="busy" class="min-h-[52px] rounded-xl bg-amber-50 text-amber-700 text-[16px] font-bold disabled:opacity-50">일시정지</button>
            <button v-if="adDetail.ad.status === 'paused'" @click="doApprove" :disabled="busy" class="min-h-[52px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold disabled:opacity-50">다시 게시</button>
            <button @click="mode = 'delete'" :disabled="busy" class="min-h-[52px] rounded-xl bg-gray-100 text-red-600 text-[16px] font-bold">삭제</button>
          </div>
          <div v-if="adDetail.other_ads?.length" class="border-t border-gray-100 pt-3">
            <div class="text-[14px] font-bold text-ink-light mb-2">이 광고주의 다른 광고 ({{ adDetail.other_ads.length }})</div>
            <div v-for="o in adDetail.other_ads" :key="o.id" class="flex items-center gap-2 text-[13px] border-b border-gray-50 last:border-0 py-2">
              <span class="min-w-0 flex-1 truncate text-ink-light">{{ o.title }}</span>
              <span class="shrink-0 px-2 py-0.5 rounded-full font-bold text-[12px]" :class="statusBadge(o.status)">{{ statusKo(o.status) }}</span>
              <span class="shrink-0 font-bold text-ink tabular-nums">{{ Number(o.total_cost || 0).toLocaleString() }}P</span>
            </div>
          </div>
          <button @click="closeSheet" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">닫기</button>
        </div>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg"
      :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
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
        <select v-model="filter.page" @change="onPageChange" class="input-soft !w-auto !px-3 !py-1.5 text-sm">
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

  <!-- 애드센스로 지정된 페이지 — 일반 배너 슬롯(프리미엄/스탠다드/이코노미) 개념이
       없으므로 슬롯 맵 대신 애드센스 전용 안내만 보여줌. 실제 애드센스 스크립트
       연동은 별도 작업이고, 여기는 그 작업이 들어갈 자리를 표시만 해둠. -->
  <div v-if="isAdsensePage" class="card overflow-hidden mb-4">
    <div class="bg-blue-50 px-4 py-3 font-bold text-sm text-ink flex items-center gap-2">
      <span class="icon-chip w-7 h-7 bg-white text-blue-600"><AppIcon name="sparkles" :size="14" /></span>
      AwesomeKorean — {{ overview?.pages?.[filter.page]?.label }} (Google 애드센스)
    </div>
    <div class="p-6 text-center text-sm text-ink-muted">
      <AppIcon name="megaphone" :size="28" class="mx-auto mb-2 text-ink-faint" />
      이 페이지는 메뉴 관리에서 "애드센스"로 지정돼 있습니다.<br>
      일반 배너 광고 슬롯 대신 Google 애드센스 광고가 노출될 자리입니다.<br>
      <span class="text-xs text-ink-faint">애드센스 승인·코드 연동 후 이 영역에 실제 광고 단위가 표시되도록 연결할 예정입니다.</span>
    </div>
  </div>

  <!-- 슬롯 맵 시각화 (유저 신청 화면과 동일 레이아웃) -->
  <div v-else-if="slotMap" class="card overflow-hidden mb-4">
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
          <button v-if="adDetail.ad.status === 'active'" @click="pauseAd()" class="inline-flex items-center gap-1.5 bg-amber-500 text-white font-semibold px-3 py-2 rounded-xl text-sm transition-colors hover:bg-amber-600"><AppIcon name="clock" :size="15" /> 일시정지</button>
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
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import BannerPreviewModal from '../../components/BannerPreviewModal.vue'
import AppIcon from '../../components/AppIcon.vue'

const overview = ref(null)
const slotMap = ref(null)
const selectedAd = ref(null)
const adDetail = ref(null)
const showPreview = ref(false)

// 관리자 휴대폰 화면이면 슬롯 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const router = useRouter()
const mode = ref('')
const rejectReason = ref('')
const rejectPresets = ['이미지가 규격에 맞지 않아요', '운영 정책에 맞지 않는 내용이에요', '링크가 열리지 않아요']
const busy = ref(false)
const toast = ref(null)
let toastTimer = null
function say(text, error = false) {
  toast.value = { text, error }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 3000)
}
const statusKo = (st) => ({ pending: '대기', active: '게시중', paused: '중지', rejected: '거절', expired: '만료' }[st] || st)
const day = (d) => String(d || '').slice(0, 10)
function pagesText(v) {
  if (!v) return ''
  if (Array.isArray(v)) return v.join(', ')
  try { const a = JSON.parse(v); return Array.isArray(a) ? a.join(', ') : String(v) } catch { return String(v) }
}
const tierBox = (t) => t === 'premium' ? 'border-amber-300 bg-amber-50/60' : t === 'standard' ? 'border-blue-200 bg-blue-50/60' : 'border-green-200 bg-green-50/60'
const tierText = (t) => t === 'premium' ? 'text-amber-700' : t === 'standard' ? 'text-blue-700' : 'text-green-700'
const tierLabel = (side, t) => ({ premium: side === 'left' ? '🥇 프A' : '🥇 프B', standard: side === 'left' ? '🥈 스A' : '🥈 스B', economy: '🥉 이코노미' }[t] || t)
function openSlotMobile(sl) {
  if (!sl.ad) { say('비어있는 슬롯이에요. 광고주가 여기에 광고를 올릴 수 있어요.'); return }
  openSlot(sl)
}
function closeSheet() { if (!busy.value) { selectedAd.value = null; adDetail.value = null; mode.value = '' } }
function goMember() { const id = adDetail.value?.ad?.user?.id; if (id) { closeSheet(); router.push(`/admin/members?user=${id}`) } }
async function runAction(fn, okText) {
  if (busy.value || !adDetail.value) return
  busy.value = true
  try { await fn(adDetail.value.ad.id); say(okText); selectedAd.value = null; adDetail.value = null; mode.value = ''; loadSlotMap(); loadOverview() }
  catch (e) { say(e.response?.data?.message || '처리하지 못했어요', true) }
  finally { busy.value = false }
}
const doApprove = () => runAction(id => axios.post(`/api/admin/banners/${id}/approve`), '게시를 시작했어요')
const doPause = () => runAction(id => axios.post(`/api/admin/banners/${id}/pause`), '일시정지했어요')
const doReject = () => runAction(id => axios.post(`/api/admin/banners/${id}/reject`, { reason: rejectReason.value.trim() }), '거절하고 환불했어요')
const doDelete = () => runAction(id => axios.delete(`/api/admin/banners/${id}`), '삭제했어요')
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && !!selectedAd.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

const filter = ref({ page: 'community', geo_scope: 'all', geo_value: '' })

// 메뉴 관리에서 이 페이지를 "애드센스"로 지정했는지 — 지정했으면 일반 배너
// 슬롯 맵(프리미엄/스탠다드/이코노미) 대신 애드센스 전용 안내를 보여줌.
const isAdsensePage = computed(() => overview.value?.pages?.[filter.value.page]?.ads_type === 'adsense')

function onPageChange() {
  if (isAdsensePage.value) { slotMap.value = null; return }
  loadSlotMap()
}

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
    paused: 'bg-amber-100 text-amber-700',
    rejected: 'bg-red-100 text-red-700',
    expired: 'bg-gray-100 text-gray-500',
  }[s] || 'bg-gray-100 text-gray-700'
}

onMounted(async () => {
  await loadOverview()
  onPageChange()
})
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
