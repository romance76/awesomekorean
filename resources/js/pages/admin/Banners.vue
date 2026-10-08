<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <!-- 요약 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide">
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">전체</div><div class="text-[20px] font-black tabular-nums text-ink">{{ items.length }}</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">대기</div><div class="text-[20px] font-black tabular-nums text-amber-600">{{ countStatus('pending') }}</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">게시중</div><div class="text-[20px] font-black tabular-nums text-green-600">{{ countStatus('active') }}</div></div>
    <div class="shrink-0 min-w-[110px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">총 수입</div><div class="text-[20px] font-black tabular-nums text-blue-600">{{ totalIncome.toLocaleString() }}P</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">총 클릭</div><div class="text-[20px] font-black tabular-nums text-purple-600">{{ totalClicks.toLocaleString() }}</div></div>
  </div>

  <!-- 상태 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="광고 상태">
    <button v-for="f in mStatusChips" :key="f.v" @click="mStatus = f.v" :aria-pressed="mStatus === f.v"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px] flex items-center gap-1.5"
      :class="mStatus === f.v ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
      {{ f.l }}
      <span v-if="f.v === 'pending' && countStatus('pending')" class="min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[12px] font-bold grid place-items-center">{{ countStatus('pending') }}</span>
    </button>
  </div>

  <!-- 페이지 -->
  <select v-model="activePage" aria-label="광고 페이지" class="w-full min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink">
    <option value="all">전체 페이지 ({{ items.length }})</option>
    <option v-for="pg in pageList" :key="pg.key" :value="pg.key">{{ pg.label }} ({{ pageAdCount(pg.key) }})</option>
  </select>

  <div class="text-[13px] text-ink-muted px-0.5">{{ loading ? '불러오는 중...' : `${mItems.length}건` }}</div>

  <!-- 광고 카드 -->
  <button v-for="item in mItems" :key="item.id" @click="openSheet(item)"
    class="w-full text-left bg-white border border-gray-100 rounded-2xl p-3 min-h-[88px] flex items-center gap-3 active:bg-amber-50">
    <span class="shrink-0 w-[84px] h-[60px] rounded-xl overflow-hidden bg-gray-100"><img v-if="item.image_url" :src="item.image_url" alt="" loading="lazy" class="w-full h-full object-cover" /></span>
    <span class="min-w-0 flex-1">
      <span class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[12px] px-2 py-0.5 rounded-full font-bold" :class="stCls[item.status]">{{ stLbl[item.status] || item.status }}</span>
        <span class="text-[12px] font-bold text-amber-700">{{ (item.bid_amount || 0).toLocaleString() }}P</span>
      </span>
      <span class="block text-[15px] font-bold text-ink truncate mt-0.5">{{ item.title }}</span>
      <span class="block text-[13px] text-ink-muted truncate">{{ item.user?.name }} · {{ pageLabel(item.page) }} · {{ {left:'좌',right:'우'}[item.position] }}{{ item.slot_number }}</span>
    </span>
  </button>
  <div v-if="!mItems.length && !loading" class="text-center text-ink-muted py-12 text-[15px]">해당하는 광고가 없어요</div>

  <!-- 광고 상세 / 처리 시트 -->
  <Teleport to="body">
    <div v-if="sheetItem" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>

        <!-- 거절 사유 -->
        <div v-if="mode === 'reject'" class="space-y-3">
          <div class="text-[17px] font-bold text-ink">광고 거절</div>
          <div class="text-[14px] text-ink-muted break-words">‘{{ sheetItem.title }}’ — 거절하면 광고주에게 <b class="text-ink">{{ refundAmount(sheetItem).toLocaleString() }}P</b>가 환불되고 사유가 전달돼요.</div>
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
          <p class="text-[15px] text-ink-light leading-relaxed break-words">‘{{ sheetItem.title }}’<template v-if="['pending','active','paused'].includes(sheetItem.status)"><br />광고주에게 <b class="text-ink">{{ refundAmount(sheetItem).toLocaleString() }}P</b>가 환불돼요.</template><template v-else><br />이미 {{ stLbl[sheetItem.status] }} 상태라 환불은 없어요.</template></p>
          <button @click="doDelete" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '처리 중...' : '삭제하기' }}</button>
          <button @click="mode = ''" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">돌아가기</button>
        </div>

        <!-- 상세 -->
        <div v-else class="space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div class="text-[17px] font-bold text-ink min-w-0 break-words">{{ sheetItem.title }}</div>
            <span class="shrink-0 text-[12px] px-2.5 py-1 rounded-full font-bold" :class="stCls[sheetItem.status]">{{ stLbl[sheetItem.status] || sheetItem.status }}</span>
          </div>
          <a v-if="sheetItem.image_url" :href="sheetItem.image_url" target="_blank" rel="noopener noreferrer" class="block rounded-2xl overflow-hidden bg-gray-100 border border-gray-100">
            <img :src="sheetItem.image_url" alt="광고 이미지" class="w-full max-h-[34vh] object-contain" />
          </a>
          <div class="bg-gray-50 rounded-2xl p-3.5 space-y-2 text-[15px]">
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">광고주</span><span class="text-right min-w-0"><b class="block text-ink truncate">{{ sheetItem.user?.name || '-' }}</b><span class="block text-[13px] text-ink-muted truncate">{{ sheetItem.user?.email }}</span></span></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">페이지</span><span class="text-ink text-right">{{ (sheetItem.target_pages || []).map(pageLabel).join(', ') || pageLabel(sheetItem.page) }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">위치</span><span class="text-ink text-right">{{ {left:'좌측',right:'우측'}[sheetItem.position] || sheetItem.position }} 슬롯 {{ sheetItem.slot_number }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">지역</span><span class="text-ink text-right">{{ sheetItem.geo_scope === 'all' ? '전국' : sheetItem.geo_value }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">입찰</span><b class="text-amber-700 tabular-nums">{{ (sheetItem.bid_amount || 0).toLocaleString() }}P</b></div>
            <div v-if="sheetItem.reject_reason" class="text-[14px] text-red-600 break-words">거절 사유: {{ sheetItem.reject_reason }}</div>
          </div>
          <a v-if="sheetItem.link_url" :href="sheetItem.link_url" target="_blank" rel="noopener noreferrer nofollow" class="flex items-center gap-2 min-h-[48px] px-3 rounded-xl bg-blue-50 text-blue-700 text-[14px] font-bold break-all">
            <AppIcon name="external-link" :size="16" /><span class="min-w-0">링크 열어 보기 · {{ sheetItem.link_url }}</span>
          </a>
          <div class="grid grid-cols-2 gap-2">
            <button v-if="sheetItem.status === 'pending'" @click="doApprove" :disabled="busy" class="min-h-[52px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold disabled:opacity-50">승인</button>
            <button v-if="sheetItem.status === 'pending'" @click="rejectReason = ''; mode = 'reject'" :disabled="busy" class="min-h-[52px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">거절</button>
            <button v-if="sheetItem.status === 'active'" @click="doPause" :disabled="busy" class="min-h-[52px] rounded-xl bg-orange-50 text-orange-700 text-[16px] font-bold disabled:opacity-50">게시 중지</button>
            <button v-if="sheetItem.status === 'paused'" @click="doApprove" :disabled="busy" class="min-h-[52px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold disabled:opacity-50">다시 게시</button>
            <button @click="mode = 'delete'" :disabled="busy" class="min-h-[52px] rounded-xl bg-gray-100 text-red-600 text-[16px] font-bold">삭제</button>
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
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="megaphone" :size="20" /></span>
    광고 관리
  </h1>

  <!-- 전체 요약 -->
  <div class="grid grid-cols-5 gap-2 mb-4">
    <div class="card p-3 text-center"><div class="text-xs text-ink-muted">전체</div><div class="text-lg font-black text-ink">{{ items.length }}</div></div>
    <div class="card p-3 text-center"><div class="text-xs text-ink-muted">대기</div><div class="text-lg font-black text-amber-600">{{ items.filter(i=>i.status==='pending').length }}</div></div>
    <div class="card p-3 text-center"><div class="text-xs text-ink-muted">게시중</div><div class="text-lg font-black text-green-600">{{ items.filter(i=>i.status==='active').length }}</div></div>
    <div class="card p-3 text-center"><div class="text-xs text-ink-muted">총 수입</div><div class="text-lg font-black text-blue-600">{{ items.reduce((s,i)=>s+(i.bid_amount||i.total_cost||0),0).toLocaleString() }}P</div></div>
    <div class="card p-3 text-center"><div class="text-xs text-ink-muted">총 클릭</div><div class="text-lg font-black text-purple-600">{{ items.reduce((s,i)=>s+(i.clicks||0),0).toLocaleString() }}</div></div>
  </div>

  <!-- 페이지 탭 -->
  <div class="card p-3 mb-4">
    <div class="flex flex-wrap gap-1.5">
      <button @click="activePage='all'" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors"
        :class="activePage==='all'?'bg-amber-400 text-white border-amber-400':'bg-white text-ink-light border-gray-200 hover:border-amber-300'">전체 ({{ items.length }})</button>
      <button v-for="pg in pageList" :key="pg.key" @click="activePage=pg.key"
        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-bold border transition-colors"
        :class="activePage===pg.key?'bg-amber-400 text-white border-amber-400':'bg-white text-ink-light border-gray-200 hover:border-amber-300'">
        <AppIcon :name="pg.icon" :size="12" /> {{ pg.label }} <span :class="activePage===pg.key?'text-white/80':pageAdCount(pg.key)?'text-green-600':'text-ink-faint'">({{ pageAdCount(pg.key) }})</span>
      </button>
    </div>
  </div>

  <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>

  <!-- ═══ 카테고리 상세 뷰 ═══ -->
  <div v-else-if="activePage!=='all'" class="space-y-4">
    <h2 class="text-sm font-bold text-ink flex items-center gap-1.5"><AppIcon :name="pageIcon(activePage)" :size="15" /> {{ pageLabel(activePage) }} — 슬롯별 입찰 현황</h2>

    <div v-for="side in ['left','right']" :key="side" class="card overflow-hidden">
      <div class="px-4 py-2 border-b border-gray-50 font-bold text-xs flex items-center gap-1" :class="side==='left'?'bg-blue-50 text-blue-800':'bg-orange-50 text-orange-800'">
        <AppIcon name="map-pin" :size="12" /> {{ side==='left'?'좌측':'우측' }} 사이드바
      </div>

      <div v-for="slot in (side==='left'?leftSlots:rightSlots)" :key="slot.key" class="border-b border-gray-50 last:border-0">
        <div class="px-4 py-2 bg-gray-50 flex items-center gap-2">
          <span>{{ slot.icon }}</span>
          <span class="text-xs font-bold text-ink-light">{{ slot.label }}</span>
          <span class="ml-auto text-xs font-bold" :class="slotTotalCount(activePage,side,slot.num)?'text-green-600':'text-ink-faint'">
            총 {{ slotTotalCount(activePage,side,slot.num) }}건
          </span>
        </div>

        <!-- 지역별 분류: 전국 → 주 → 카운티 -->
        <div v-for="geo in geoTypes" :key="geo.key" class="px-4">
          <div v-if="slotGeoAds(activePage,side,slot.num,geo.key).length" class="py-2">
            <div class="flex items-center gap-2 mb-1.5">
              <span class="text-xs font-bold px-2 py-0.5 rounded-full" :class="geo.cls">{{ geo.icon }} {{ geo.label }}</span>
              <span class="text-xs text-ink-faint">{{ slotGeoAds(activePage,side,slot.num,geo.key).length }}건</span>
            </div>
            <div class="space-y-1 ml-2">
              <div v-for="(ad, idx) in slotGeoAds(activePage,side,slot.num,geo.key)" :key="ad.id"
                class="flex items-center gap-2 p-2 rounded-lg text-xs"
                :class="idx===0?'bg-yellow-50 border border-yellow-200':'bg-gray-50'">
                <span class="font-black w-5 text-center" :class="idx===0?'text-yellow-600':idx===1?'text-ink-muted':'text-ink-faint'">{{ idx+1 }}</span>
                <!-- 배너 이미지 클릭 → 팝업 -->
                <div class="w-14 h-10 rounded-lg overflow-hidden bg-gray-200 flex-shrink-0 cursor-pointer transition-shadow hover:ring-2 ring-amber-400"
                  @click="previewImage(ad)">
                  <img :src="ad.image_url" class="w-full h-full object-cover" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-ink truncate">{{ ad.title }}</div>
                  <div class="text-[11px] text-ink-muted">
                    {{ ad.user?.name }}
                    <span v-if="ad.geo_value"> · {{ ad.geo_value }}</span>
                    <!-- 링크 클릭 → 팝업 -->
                    <span v-if="ad.link_url" @click="previewLink(ad.link_url)" class="ml-1 text-blue-500 underline cursor-pointer hover:text-blue-700 transition-colors inline-flex items-center gap-0.5"><AppIcon name="external-link" :size="10" />링크</span>
                  </div>
                </div>
                <div class="font-black text-amber-700">{{ (ad.bid_amount||0).toLocaleString() }}P</div>
                <span class="text-[11px] px-1.5 py-0.5 rounded-full font-bold" :class="stCls[ad.status]">{{ stLbl[ad.status] }}</span>
                <div class="flex gap-0.5 flex-shrink-0">
                  <button v-if="ad.status==='pending'" @click="approve(ad)" class="text-[11px] bg-green-500 text-white px-1.5 py-1 rounded-lg font-bold transition-colors hover:bg-green-600">승인</button>
                  <button v-if="ad.status==='pending'" @click="reject(ad)" class="text-[11px] bg-red-500 text-white px-1.5 py-1 rounded-lg font-bold transition-colors hover:bg-red-600">거절</button>
                  <button v-if="ad.status==='active'" @click="pause(ad)" class="text-[11px] bg-gray-200 text-ink-light px-1.5 py-1 rounded-lg font-bold transition-colors hover:bg-gray-300">중지</button>
                  <button @click="remove(ad)" class="text-[11px] text-red-400 px-1 transition-colors hover:text-red-600">삭제</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!slotTotalCount(activePage,side,slot.num)" class="px-4 py-3 text-xs text-ink-faint">입찰 없음</div>
      </div>
    </div>
  </div>

  <!-- ═══ 전체 목록 ═══ -->
  <div v-else-if="!items.length" class="py-16 text-center">
    <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="megaphone" :size="28" :stroke-width="1.5" /></div>
    <p class="text-sm text-ink-muted">광고 신청 없음</p>
  </div>
  <div v-else class="space-y-2">
    <div v-for="item in items" :key="item.id" class="card p-3">
      <div class="flex gap-3 items-center">
        <div class="w-20 h-14 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 cursor-pointer transition-shadow hover:ring-2 ring-amber-400" @click="previewImage(item)">
          <img :src="item.image_url" class="w-full h-full object-cover" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[11px] px-2 py-0.5 rounded-full font-bold" :class="stCls[item.status]">{{ stLbl[item.status] }}</span>
            <span class="text-[11px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">{{ pageLabel(item.page) }}</span>
            <span class="text-[11px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{{ {left:'좌',right:'우'}[item.position] }}{{ item.slot_number }}</span>
            <span class="text-[11px] px-1.5 py-0.5 rounded-full font-bold" :class="geoTypesMap[item.geo_scope]?.cls||'bg-gray-100 text-gray-600'">{{ item.geo_scope==='all'?'전국':item.geo_value }}</span>
            <span class="text-[11px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-bold">{{ (item.bid_amount||0).toLocaleString() }}P</span>
            <span v-if="item.link_url" @click="previewLink(item.link_url)" class="text-[11px] text-blue-500 cursor-pointer hover:text-blue-700 transition-colors inline-flex"><AppIcon name="external-link" :size="12" /></span>
          </div>
          <div class="text-xs font-bold text-ink truncate mt-0.5">{{ item.title }}</div>
          <div class="text-[11px] text-ink-muted">{{ item.user?.name }} · {{ (item.target_pages||[]).join(', ')||item.page }}</div>
        </div>
        <div class="flex gap-1 flex-shrink-0">
          <button v-if="item.status==='pending'" @click="approve(item)" class="text-[11px] bg-green-500 text-white px-2 py-1 rounded-lg font-bold transition-colors hover:bg-green-600">승인</button>
          <button v-if="item.status==='pending'" @click="reject(item)" class="text-[11px] bg-red-500 text-white px-2 py-1 rounded-lg font-bold transition-colors hover:bg-red-600">거절</button>
          <button v-if="item.status==='active'" @click="pause(item)" class="text-[11px] bg-gray-200 text-ink-light px-2 py-1 rounded-lg font-bold transition-colors hover:bg-gray-300">중지</button>
          <button @click="remove(item)" class="text-[11px] text-red-400 transition-colors hover:text-red-600">삭제</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ 이미지 미리보기 팝업 ═══ -->
  <Teleport to="body">
    <div v-if="previewImg" class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center" @click.self="previewImg=null">
      <div class="bg-white rounded-2xl shadow-lift max-w-lg w-full mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
          <span class="text-sm font-bold text-ink flex items-center gap-1.5"><AppIcon name="camera" :size="15" /> 배너 미리보기</span>
          <button @click="previewImg=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="20" /></button>
        </div>
        <div class="p-4">
          <img :src="previewImg.image_url" class="w-full rounded-lg border border-gray-100" />
          <div class="mt-3 text-sm font-bold text-ink">{{ previewImg.title }}</div>
          <div class="text-xs text-ink-muted mt-1">{{ previewImg.user?.name }} · {{ (previewImg.bid_amount||0).toLocaleString() }}P · {{ previewImg.geo_scope==='all'?'전국':previewImg.geo_value }}</div>
          <div v-if="previewImg.link_url" class="mt-2">
            <span class="text-xs text-ink-muted">링크: </span>
            <span @click="previewLink(previewImg.link_url)" class="text-xs text-blue-600 underline cursor-pointer hover:text-blue-700 transition-colors">{{ previewImg.link_url }}</span>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ═══ 링크 미리보기 팝업 ═══ -->
  <Teleport to="body">
    <div v-if="previewUrl" class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center" @click.self="previewUrl=null">
      <div class="bg-white rounded-2xl shadow-lift max-w-2xl w-full mx-4 overflow-hidden" style="height:70vh">
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100 bg-gray-50">
          <span class="text-xs font-bold text-ink-light truncate flex-1 flex items-center gap-1"><AppIcon name="external-link" :size="12" /> {{ previewUrl }}</span>
          <div class="flex gap-2 flex-shrink-0 items-center">
            <a :href="previewUrl" target="_blank" class="text-[11px] bg-blue-500 text-white px-3 py-1 rounded-lg font-bold transition-colors hover:bg-blue-600">새 탭</a>
            <button @click="previewUrl=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="20" /></button>
          </div>
        </div>
        <iframe :src="previewUrl" class="w-full" style="height:calc(70vh - 40px)" frameborder="0"></iframe>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)

const items = ref([])
const loading = ref(true)
const activePage = ref('all')
const previewImg = ref(null)
const previewUrl = ref(null)

// ── 휴대폰 화면 전용 ──
const mStatus = ref('pending')
const mStatusChips = [{ v: 'pending', l: '승인 대기' }, { v: 'active', l: '게시중' }, { v: 'ended', l: '중지·거절·만료' }, { v: 'all', l: '전체' }]
const sheetItem = ref(null)
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
const countStatus = (st) => items.value.filter(i => i.status === st).length
const totalIncome = computed(() => items.value.reduce((sum, i) => sum + (i.bid_amount || i.total_cost || 0), 0))
const totalClicks = computed(() => items.value.reduce((sum, i) => sum + (i.clicks || 0), 0))
const mItems = computed(() => items.value.filter(i => {
  const st = mStatus.value
  if (st === 'pending' && i.status !== 'pending') return false
  if (st === 'active' && i.status !== 'active') return false
  if (st === 'ended' && !['paused', 'rejected', 'expired'].includes(i.status)) return false
  return activePage.value === 'all' || matchPage(i, activePage.value)
}))
const refundAmount = (i) => Number(i.total_cost ?? i.bid_amount ?? 0)
function openSheet(i) { sheetItem.value = i; mode.value = '' }
function closeSheet() { if (!busy.value) { sheetItem.value = null; mode.value = '' } }
async function runAction(fn, okText) {
  if (busy.value) return
  busy.value = true
  try { await fn(); say(okText); sheetItem.value = null; mode.value = '' }
  catch (e) { say(e.response?.data?.message || '처리하지 못했어요', true) }
  finally { busy.value = false }
}
const doApprove = () => runAction(async () => { await axios.post(`/api/admin/banners/${sheetItem.value.id}/approve`); sheetItem.value.status = 'active' }, '게시를 시작했어요')
const doPause = () => runAction(async () => { await axios.post(`/api/admin/banners/${sheetItem.value.id}/pause`); sheetItem.value.status = 'paused' }, '게시를 중지했어요')
const doReject = () => runAction(async () => { await axios.post(`/api/admin/banners/${sheetItem.value.id}/reject`, { reason: rejectReason.value.trim() }); sheetItem.value.status = 'rejected' }, '거절하고 환불했어요')
const doDelete = () => runAction(async () => { const id = sheetItem.value.id; await axios.delete(`/api/admin/banners/${id}`); items.value = items.value.filter(i => i.id !== id) }, '삭제했어요')
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && !!sheetItem.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

// 페이지 탭 목록은 이 파일에 따로 하드코딩하지 않고 광고 센터(overview API)의
// availablePages()를 그대로 공유해서 가져옴 — 둘이 따로 하드코딩돼 있다 보니
// '정보'·'숏츠'처럼 나중에 추가된 페이지가 여기에만 빠져서 광고 목록 탭 개수가
// 실제 활성 메뉴 수와 안 맞던 문제가 있었음.
const pageList = ref([])
async function loadPages() {
  try {
    const { data } = await axios.get('/api/admin/ad-center/overview')
    pageList.value = Object.entries(data.data?.pages || {}).map(([key, p]) => ({ key, icon: iconFor(key), label: p.label }))
  } catch {}
}
// AppIcon에 없는 lucide 아이콘명 호환 — ad-center 쪽은 이모지만 내려주므로 매핑
function iconFor(key) {
  return {
    home: 'home', community: 'message-circle', qa: 'help-circle', jobs: 'briefcase',
    market: 'shopping-cart', realestate: 'building', directory: 'store', clubs: 'users',
    news: 'newspaper', recipes: 'utensils', groupbuy: 'heart-handshake', events: 'calendar',
    music: 'music', shorts: 'video', info: 'book-open',
  }[key] || 'file'
}

const leftSlots = [
  { key:'l1',num:1,icon:'🥇',label:'프리미엄 (고정)',max:1 },
  { key:'l2',num:2,icon:'🥈',label:'스탠다드 (2개 랜덤)',max:2 },
  { key:'l3',num:3,icon:'🥉',label:'이코노미 (5개 랜덤)',max:5 },
]
const rightSlots = [
  { key:'r1',num:1,icon:'🥇',label:'프리미엄 (고정)',max:1 },
  { key:'r2',num:2,icon:'🥉',label:'이코노미 (3개 랜덤)',max:3 },
]

const geoTypes = [
  { key:'all', icon:'🌍', label:'전국', cls:'bg-amber-100 text-amber-700' },
  { key:'state', icon:'🏛️', label:'주별', cls:'bg-blue-100 text-blue-700' },
  { key:'county', icon:'📍', label:'카운티별', cls:'bg-green-100 text-green-700' },
]
const geoTypesMap = { all:geoTypes[0], state:geoTypes[1], county:geoTypes[2] }

const stLbl = { pending:'대기',active:'게시중',rejected:'거절',expired:'만료',paused:'중지' }
const stCls = { pending:'bg-amber-100 text-amber-700',active:'bg-green-100 text-green-700',rejected:'bg-red-100 text-red-700',expired:'bg-gray-200 text-gray-500',paused:'bg-gray-200 text-gray-500' }

function pageLabel(k){ return pageList.value.find(p=>p.key===k)?.label||k }
function pageIcon(k){ return pageList.value.find(p=>p.key===k)?.icon||'📄' }
function pageAdCount(pk){ return items.value.filter(i=> i.page===pk||(i.target_pages&&i.target_pages.includes(pk))).length }

function matchPage(ad, pk) {
  return ad.page===pk || (ad.target_pages&&ad.target_pages.includes(pk)) || ad.page==='all'
}

function slotGeoAds(pk, pos, slotNum, geoScope) {
  return items.value
    .filter(i => matchPage(i,pk) && i.position===pos && (i.slot_number||1)===slotNum && i.geo_scope===geoScope)
    .sort((a,b) => (b.bid_amount||0)-(a.bid_amount||0))
}

function slotTotalCount(pk, pos, slotNum) {
  return items.value.filter(i => matchPage(i,pk) && i.position===pos && (i.slot_number||1)===slotNum).length
}

function previewImage(ad) { previewImg.value = ad }
function previewLink(url) { previewUrl.value = url }

async function load() {
  try {
    const{data}=await axios.get('/api/admin/banners')
    items.value=(data.data||[]).map(i=>({...i,target_pages:typeof i.target_pages==='string'?JSON.parse(i.target_pages):(i.target_pages||[])}))
  }catch{}
  loading.value=false
}

async function approve(item){try{await axios.post(`/api/admin/banners/${item.id}/approve`);item.status='active'}catch(e){alert(e.response?.data?.message||'실패')}}
async function reject(item){const r=prompt('거절 사유:');if(!r)return;try{await axios.post(`/api/admin/banners/${item.id}/reject`,{reason:r});item.status='rejected'}catch{}}
async function pause(item){try{await axios.post(`/api/admin/banners/${item.id}/pause`);item.status='paused'}catch{}}
async function remove(item){if(!confirm('삭제?'))return;try{await axios.delete(`/api/admin/banners/${item.id}`);items.value=items.value.filter(i=>i.id!==item.id)}catch{}}

onMounted(() => { load(); loadPages() })
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
