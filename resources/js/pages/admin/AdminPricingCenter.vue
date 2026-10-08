<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3" :class="dirtyCount ? 'pb-24' : 'pb-4'">
  <p v-if="!canEdit" class="bg-amber-50 border border-amber-200 text-amber-800 text-[14px] rounded-xl p-3">가격·포인트 설정은 관리자(admin) 이상만 바꿀 수 있어요. 지금은 보기만 가능해요.</p>

  <!-- 탭 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="tablist" aria-label="가격 메뉴">
    <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key" role="tab" :aria-selected="activeTab === t.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px] flex items-center gap-1.5"
      :class="activeTab === t.key ? 'bg-amber-500 text-white border-amber-500 font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ t.label }}</button>
  </div>

  <div v-if="loading" class="text-center text-ink-muted py-12 text-[15px]">불러오는 중...</div>

  <!-- 포인트 설정 -->
  <template v-else-if="activeTab === 'points'">
    <label class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
      <AppIcon name="search" :size="18" />
      <input v-model="pointQuery" type="search" placeholder="항목 찾기 (예: 글쓰기)" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" />
    </label>
    <div v-for="cat in pointCategories" :key="cat" v-show="catItems(cat).length" class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
      <button @click="toggleCat(cat)" :aria-expanded="catOpen(cat)" class="w-full min-h-[56px] px-4 flex items-center gap-2 text-left active:bg-amber-50" :class="catStyles[cat].bg">
        <AppIcon :name="catStyles[cat].icon" :size="18" />
        <span class="flex-1 text-[16px] font-bold">{{ catStyles[cat].label }}</span>
        <span v-if="catDirty(cat)" class="text-[12px] font-bold bg-amber-500 text-white rounded-full px-2 py-0.5">{{ catDirty(cat) }}개 변경</span>
        <span class="text-[13px] opacity-70">{{ catItems(cat).length }}개</span>
        <AppIcon :name="catOpen(cat) ? 'chevron-up' : 'chevron-down'" :size="18" />
      </button>
      <div v-if="catOpen(cat)" class="divide-y divide-gray-50">
        <div v-for="item in catItems(cat)" :key="item.key" class="px-4 py-3">
          <div class="flex items-center gap-2">
            <div class="min-w-0 flex-1"><div class="text-[15px] font-bold text-ink break-words">{{ item.label }}</div>
              <div class="text-[12px] text-ink-faint break-all">{{ item.key }}<span v-if="item.description"> · {{ item.description }}</span></div></div>
            <span v-if="isDirty(item)" class="shrink-0 text-[12px] font-bold text-amber-700 bg-amber-50 rounded-full px-2 py-0.5">변경됨</span>
          </div>
          <input v-model="item.value" :disabled="!canEdit" :aria-label="item.label" inputmode="decimal" autocomplete="off"
            class="mt-2 w-full min-h-[48px] rounded-xl border px-3 font-mono text-right tabular-nums disabled:bg-gray-50"
            :class="isDirty(item) ? 'border-amber-400 bg-amber-50/40' : 'border-gray-200'" />
        </div>
      </div>
    </div>
    <p v-if="pointQuery && !pointCategories.some(c => catItems(c).length)" class="text-center text-ink-muted py-10 text-[15px]">“{{ pointQuery }}” 에 맞는 항목이 없어요.</p>
  </template>

  <!-- 포인트 패키지 -->
  <template v-else-if="activeTab === 'packages'">
    <p class="text-[14px] text-ink-muted leading-relaxed">가격($), 기본 포인트, 보너스 포인트를 따로 입력해요. 화면 아래에서 <b>저장</b>을 누르면 바뀐 내용을 한 번 더 보여 드려요.</p>
    <div v-for="pkg in grouped.package || []" :key="pkg.key" class="bg-amber-50 border border-amber-200 rounded-2xl p-3.5">
      <div class="flex items-center justify-between gap-2">
        <div class="text-[16px] font-bold text-amber-900">{{ pkg.label }}</div>
        <span v-if="isDirty(pkg)" class="text-[12px] font-bold text-amber-700 bg-white rounded-full px-2 py-0.5">변경됨</span>
      </div>
      <div class="grid grid-cols-3 gap-2 mt-2.5">
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">가격 ($)</span>
          <input :value="part(pkg, 0)" @input="setPart(pkg, 0, $event.target.value)" :disabled="!canEdit" inputmode="decimal" class="w-full min-h-[48px] rounded-xl border border-amber-200 bg-white px-2 text-center tabular-nums" /></label>
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">포인트</span>
          <input :value="part(pkg, 1)" @input="setPart(pkg, 1, $event.target.value)" :disabled="!canEdit" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-amber-200 bg-white px-2 text-center tabular-nums" /></label>
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">보너스</span>
          <input :value="part(pkg, 2)" @input="setPart(pkg, 2, $event.target.value)" :disabled="!canEdit" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-amber-200 bg-white px-2 text-center tabular-nums" /></label>
      </div>
      <div class="text-[14px] text-amber-800 mt-2 flex items-baseline gap-1.5 flex-wrap">
        <b>${{ part(pkg, 0) || '0' }}</b><span>→</span><b>{{ (Number(part(pkg, 1) || 0) + Number(part(pkg, 2) || 0)).toLocaleString() }}P</b>
        <span v-if="Number(part(pkg, 2) || 0) > 0" class="text-green-700">(보너스 +{{ Number(part(pkg, 2)).toLocaleString() }}P)</span>
      </div>
      <div class="text-[11px] text-ink-faint mt-1 break-all">{{ pkg.key }}</div>
    </div>
  </template>

  <!-- 광고 가격 -->
  <template v-else-if="activeTab === 'ads'">
    <div class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-3">
      <div class="text-[16px] font-bold text-ink">등급별 최소 월 입찰가 (P)</div>
      <div class="text-[13px] font-bold text-ink-light">좌측 사이드바</div>
      <div class="grid grid-cols-3 gap-2">
        <label class="block"><span class="block text-[12px] font-bold text-yellow-700 mb-1">🥇 프리미엄</span><input v-model.number="minPrices.left_premium" :disabled="!canEdit" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-yellow-200 bg-yellow-50 px-2 text-center tabular-nums" /></label>
        <label class="block"><span class="block text-[12px] font-bold text-blue-700 mb-1">🥈 스탠다드</span><input v-model.number="minPrices.left_standard" :disabled="!canEdit" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-blue-200 bg-blue-50 px-2 text-center tabular-nums" /></label>
        <label class="block"><span class="block text-[12px] font-bold text-green-700 mb-1">🥉 이코노미</span><input v-model.number="minPrices.left_economy" :disabled="!canEdit" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-green-200 bg-green-50 px-2 text-center tabular-nums" /></label>
      </div>
      <div class="text-[13px] font-bold text-ink-light">우측 사이드바</div>
      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="block text-[12px] font-bold text-yellow-700 mb-1">🥇 프리미엄</span><input v-model.number="minPrices.right_premium" :disabled="!canEdit" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-yellow-200 bg-yellow-50 px-2 text-center tabular-nums" /></label>
        <label class="block"><span class="block text-[12px] font-bold text-green-700 mb-1">🥉 이코노미</span><input v-model.number="minPrices.right_economy" :disabled="!canEdit" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-green-200 bg-green-50 px-2 text-center tabular-nums" /></label>
      </div>
      <div class="text-[13px] font-bold text-ink-light">지역별 추가금</div>
      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="block text-[12px] font-bold text-blue-700 mb-1">주 (카운티 대비 +)</span><input v-model.number="geoMarkup.state" :disabled="!canEdit" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-blue-200 bg-blue-50 px-2 text-center tabular-nums" /></label>
        <label class="block"><span class="block text-[12px] font-bold text-amber-700 mb-1">전국 (주 대비 +)</span><input v-model.number="geoMarkup.national" :disabled="!canEdit" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-amber-200 bg-amber-50 px-2 text-center tabular-nums" /></label>
      </div>
      <button @click="mSaveAdPrices" :disabled="savingAds || !canEdit" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ savingAds ? '저장 중...' : '가격 저장' }}</button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-2.5">
      <div class="text-[16px] font-bold text-ink">페이지별 광고 슬롯 수</div>
      <p class="text-[13px] text-ink-muted leading-relaxed">좌/우를 둘 다 0으로 하면 그 페이지 광고가 꺼져요. 애드센스 페이지는 광고 센터에서 따로 관리돼요.</p>
      <div v-for="menu in bannerEligibleMenus" :key="menu.key" class="border border-gray-100 rounded-xl p-3" :class="isPageOff(menu.key) ? 'bg-gray-50 border-dashed' : ''">
        <div class="flex items-center gap-1.5 text-[15px] font-bold text-ink"><span>{{ menu.icon }}</span>{{ menu.label }}<span v-if="isPageOff(menu.key)" class="text-[12px] text-ink-faint font-normal">(꺼짐)</span></div>
        <div class="grid grid-cols-2 gap-3 mt-2">
          <div v-for="side in [{ f: 'left_slots', l: '좌', c: 'text-blue-700' }, { f: 'right_slots', l: '우', c: 'text-orange-700' }]" :key="side.f">
            <div class="text-[12px] font-bold mb-1" :class="side.c">{{ side.l }}</div>
            <div class="flex items-center gap-1">
              <button @click="stepSlot(menu, side.f, -1)" :disabled="!canEdit || slotOf(menu.key)[side.f] <= 0" class="w-12 h-12 rounded-xl bg-gray-100 text-[22px] font-bold disabled:opacity-30" :aria-label="side.l + ' 슬롯 줄이기'">−</button>
              <span class="flex-1 text-center text-[18px] font-black tabular-nums" :class="side.c">{{ slotOf(menu.key)[side.f] }}</span>
              <button @click="stepSlot(menu, side.f, 1)" :disabled="!canEdit || slotOf(menu.key)[side.f] >= 5" class="w-12 h-12 rounded-xl bg-gray-100 text-[22px] font-bold disabled:opacity-30" :aria-label="side.l + ' 슬롯 늘리기'">+</button>
            </div>
          </div>
        </div>
      </div>
      <div v-for="menu in adsenseEligibleMenus" :key="menu.key" class="bg-blue-50/60 border border-dashed border-blue-200 rounded-xl p-3 text-[14px] text-blue-700"><span>{{ menu.icon }}</span> {{ menu.label }} — 애드센스 (광고 센터에서 관리)</div>
      <div v-if="!adEligibleMenus.length" class="text-center py-6 text-[15px] text-ink-muted">활성화된 메뉴가 없어요</div>
      <button @click="mSavePageConfig" :disabled="savingPageConfig || !canEdit" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ savingPageConfig ? '저장 중...' : '슬롯 수 저장' }}</button>
    </div>
  </template>

  <!-- 할인 이벤트 -->
  <template v-else-if="activeTab === 'promotions'">
    <button @click="openPromoSheet()" :disabled="!canEdit" class="w-full min-h-[52px] rounded-2xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">+ 새 할인 이벤트</button>
    <button v-for="pr in promotions" :key="pr.id" @click="openPromoSheet(pr)"
      class="w-full text-left bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[80px] flex items-center gap-3 active:bg-amber-50">
      <span class="shrink-0 w-[68px] text-center text-[26px] font-black text-red-500 tabular-nums">-{{ pr.discount_pct }}%</span>
      <span class="min-w-0 flex-1">
        <span class="block text-[16px] font-bold text-ink break-words">{{ pr.title }}</span>
        <span class="block text-[13px] text-ink-muted">{{ fmtDate(pr.starts_at) }} ~ {{ fmtDate(pr.ends_at) }}</span>
        <span class="block text-[13px] text-ink-muted">{{ pr.applies_to_ads ? '광고' : '' }}{{ pr.applies_to_ads && pr.applies_to_packages ? ' + ' : '' }}{{ pr.applies_to_packages ? '패키지' : '' }}</span>
      </span>
      <span class="shrink-0 text-[12px] px-2.5 py-1 rounded-full font-bold" :class="isActiveNow(pr) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">{{ isActiveNow(pr) ? '진행중' : (!pr.is_active ? '비활성' : '대기/종료') }}</span>
    </button>
    <div v-if="!promotions.length" class="text-center text-ink-muted py-12 text-[15px]">등록된 이벤트가 없어요</div>
  </template>

  <!-- 하단 고정 저장 바 (포인트 설정·패키지에서 바뀐 게 있을 때) -->
  <div v-if="dirtyCount && (activeTab === 'points' || activeTab === 'packages')" class="fixed inset-x-0 z-30 px-3.5"
    :style="{ bottom: 'calc(70px + env(safe-area-inset-bottom, 0px))' }">
    <div class="bg-ink text-white rounded-2xl shadow-lg px-4 py-2.5 flex items-center gap-3">
      <span class="flex-1 text-[15px] font-bold">{{ dirtyCount }}개 항목이 바뀌었어요</span>
      <button @click="discardChanges" class="min-h-[44px] px-3 rounded-xl text-[14px] font-bold text-white/80">되돌리기</button>
      <button @click="askSave" :disabled="!canEdit" class="min-h-[44px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold disabled:opacity-40">저장</button>
    </div>
  </div>

  <Teleport to="body">
    <!-- 저장 전 확인 -->
    <div v-if="confirmSave" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="confirmSave = false">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">{{ dirtyCount }}개 항목을 저장할까요?</div>
        <p class="text-[13px] text-ink-muted mb-2">저장하면 바로 사이트에 적용돼요. 바뀐 내용을 확인하세요.</p>
        <div class="bg-gray-50 rounded-2xl divide-y divide-gray-100 mb-3">
          <div v-for="c in dirtyList" :key="c.key" class="px-3.5 py-2.5">
            <div class="text-[14px] font-bold text-ink break-words">{{ c.label }}</div>
            <div class="text-[14px] font-mono tabular-nums"><span class="text-ink-muted line-through break-all">{{ c.before }}</span> <span class="text-ink-muted">→</span> <b class="text-amber-700 break-all">{{ c.after }}</b></div>
          </div>
        </div>
        <button @click="doSave" :disabled="savingPoints" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-50">{{ savingPoints ? '저장 중...' : '저장하기' }}</button>
        <button @click="confirmSave = false" :disabled="savingPoints" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>

    <!-- 할인 이벤트 추가/수정 -->
    <div v-if="promoSheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closePromoSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">{{ editPromoId ? '할인 이벤트 수정' : '새 할인 이벤트' }}</div>
        <div class="space-y-3">
          <input v-model="promoForm.title" maxlength="100" placeholder="이벤트 제목 (예: 신년 40% 할인)" aria-label="이벤트 제목" class="w-full min-h-[50px] rounded-xl border border-gray-200 px-3" />
          <div>
            <div class="text-[13px] font-bold text-ink-muted mb-1.5">할인율 (%)</div>
            <div class="flex gap-2 mb-2"><button v-for="n in [10, 20, 30, 50]" :key="n" type="button" @click="promoForm.discount_pct = n" class="flex-1 min-h-[44px] rounded-xl border text-[15px] font-bold" :class="promoForm.discount_pct === n ? 'bg-ink text-white border-ink' : 'bg-white text-ink border-gray-200'">{{ n }}%</button></div>
            <input v-model.number="promoForm.discount_pct" type="number" inputmode="numeric" min="0" max="100" aria-label="할인율" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 tabular-nums" />
          </div>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1.5">시작 일시</span><input v-model="promoForm.starts_at" type="datetime-local" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1.5">종료 일시</span><input v-model="promoForm.ends_at" type="datetime-local" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
          <div class="space-y-1">
            <div class="text-[13px] font-bold text-ink-muted">적용 대상</div>
            <label class="flex items-center gap-3 min-h-[48px] text-[16px] text-ink"><input v-model="promoForm.applies_to_ads" type="checkbox" class="w-6 h-6 accent-amber-500" /> 광고</label>
            <label class="flex items-center gap-3 min-h-[48px] text-[16px] text-ink"><input v-model="promoForm.applies_to_packages" type="checkbox" class="w-6 h-6 accent-amber-500" /> 포인트 패키지</label>
            <label class="flex items-center gap-3 min-h-[48px] text-[16px] text-ink"><input v-model="promoForm.is_active" type="checkbox" class="w-6 h-6 accent-amber-500" /> 활성화</label>
          </div>
          <button @click="mSavePromo" :disabled="savingPromo" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-50">{{ savingPromo ? '저장 중...' : (editPromoId ? '수정' : '등록') }}</button>
          <button v-if="editPromoId" @click="mDeletePromo" :disabled="savingPromo" class="w-full min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">삭제</button>
          <button @click="closePromoSheet" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg"
      :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
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
      <p class="text-xs text-ink-muted mb-3">메뉴 관리에서 광고를 켠 페이지가 전부 나열됩니다. 좌/우 둘 다 0 이면 해당 페이지 광고 자체가 꺼집니다. 애드센스로 지정한 페이지는 슬롯 개념이 없어 광고 센터에서 따로 관리된다는 안내만 표시됩니다.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div v-for="menu in bannerEligibleMenus" :key="menu.key" class="bg-gray-50 rounded-xl border border-gray-100 p-3 flex items-center gap-3"
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
        <!-- 애드센스로 지정된 페이지 — 좌/우 슬롯 수 개념이 없어 슬라이더 대신
             안내만 표시. 실제 애드센스 영역 설정은 광고 센터 화면에서 관리됨. -->
        <div v-for="menu in adsenseEligibleMenus" :key="menu.key" class="bg-blue-50/50 rounded-xl border border-blue-100 border-dashed p-3 flex items-center gap-3">
          <div class="w-20 flex-shrink-0">
            <div class="text-xs font-bold text-ink flex items-center gap-1">
              <span>{{ menu.icon }}</span>{{ menu.label }}
            </div>
            <div class="text-[11px] text-ink-faint">{{ menu.path }}</div>
          </div>
          <div class="flex-1 text-[11px] text-blue-600 flex items-center gap-1.5">
            <AppIcon name="sparkles" :size="12" /> 애드센스 — 광고 센터에서 관리됩니다
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
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/AppIcon.vue'

const siteStore = useSiteStore()
const auth = useAuthStore()

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
// 가격/포인트 저장은 서버에서 admin/super_admin 만 허용
const canEdit = computed(() => ['admin', 'super_admin'].includes(auth.user?.role))

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
    snapshotPoints()
  } catch {}
}

async function savePointSettings() {
  savingPoints.value = true; pointMsg.value = ''
  const all = Object.values(grouped.value).flat().map(i => ({ key: i.key, value: i.value }))
  try {
    await axios.post('/api/admin/point-settings', { settings: all })
    pointMsg.value = '저장됨!'; pointMsgOk.value = true
    snapshotPoints()
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

// 광고 슬롯 설정 대상 메뉴: 활성화된 메뉴 중 "메뉴 관리"에서 광고를 켠(ads_enabled)
// 메뉴 전부. 일반 광고/애드센스 모두 여기 목록엔 나오되, 애드센스는 좌/우 슬롯
// 수 개념 자체가 없어서(실제 설정은 광고 센터에서 따로 관리) 아래 템플릿에서
// 슬라이더 대신 안내만 보여주는 쪽으로 따로 분리해서 렌더링함.
const adEligibleMenus = computed(() => {
  const mc = siteStore.menuConfig
  if (!mc || !Array.isArray(mc)) return []
  return mc.filter(m => m.enabled !== false && !m.admin_only && m.ads_enabled)
})
const bannerEligibleMenus = computed(() => adEligibleMenus.value.filter(m => (m.ads_type || 'banner') !== 'adsense'))
const adsenseEligibleMenus = computed(() => adEligibleMenus.value.filter(m => (m.ads_type || 'banner') === 'adsense'))

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
  // 애드센스 페이지는 좌/우 슬롯 개념이 없어 제외.
  const finalConfig = { ...pageConfig.value }
  bannerEligibleMenus.value.forEach(menu => {
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

// ─── 휴대폰 화면 전용 ───────────────────────────────────────
const toast = ref(null)
let toastTimer = null
function say(text, error = false) {
  toast.value = { text, error }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 3000)
}

// 포인트 설정/패키지: 바뀐 항목만 추적해서 저장 전에 한 번 더 보여 줌
const orig = ref({})
function snapshotPoints() {
  const o = {}
  Object.values(grouped.value).flat().forEach(i => { o[i.key] = String(i.value ?? '') })
  orig.value = o
}
const allItems = computed(() => Object.values(grouped.value).flat())
const isDirty = (item) => String(item.value ?? '') !== (orig.value[item.key] ?? '')
const dirtyList = computed(() => allItems.value.filter(isDirty).map(i => ({ key: i.key, label: i.label, before: orig.value[i.key] ?? '', after: String(i.value ?? '') })))
const dirtyCount = computed(() => dirtyList.value.length)
function discardChanges() { allItems.value.forEach(i => { if (isDirty(i)) i.value = orig.value[i.key] }) }

const pointQuery = ref('')
const openCats = ref(new Set(['earn']))
const catItems = (cat) => {
  const q = pointQuery.value.trim().toLowerCase()
  const list = grouped.value[cat] || []
  return q ? list.filter(i => `${i.label} ${i.key} ${i.description || ''}`.toLowerCase().includes(q)) : list
}
const catOpen = (cat) => !!pointQuery.value.trim() || openCats.value.has(cat)
function toggleCat(cat) { const n = new Set(openCats.value); n.has(cat) ? n.delete(cat) : n.add(cat); openCats.value = n }
const catDirty = (cat) => (grouped.value[cat] || []).filter(isDirty).length

// 패키지: "가격|포인트|보너스" 문자열을 칸 3개로 나눠 편집
const part = (pkg, idx) => String(pkg.value ?? '').split('|')[idx] ?? ''
function setPart(pkg, idx, val) {
  const parts = String(pkg.value ?? '').split('|')
  while (parts.length < 3) parts.push('0')
  parts[idx] = String(val).trim()
  pkg.value = parts.slice(0, 3).join('|')
}
const PKG_RE = /^\d+(\.\d{1,2})?\|\d+\|\d+$/

const confirmSave = ref(false)
function askSave() {
  const bad = (grouped.value.package || []).find(p => isDirty(p) && !PKG_RE.test(String(p.value)))
  if (bad) { say(`‘${bad.label}’ 패키지 값을 확인해 주세요 (가격·포인트·보너스는 숫자)`, true); activeTab.value = 'packages'; return }
  confirmSave.value = true
}
async function doSave() {
  if (savingPoints.value) return
  await savePointSettings()
  if (pointMsgOk.value) { confirmSave.value = false; say('저장했어요. 바로 적용돼요') }
  else say(pointMsg.value || '저장하지 못했어요', true)
}

// 광고 가격 / 슬롯
async function mSaveAdPrices() {
  await saveAdPrices()
  say(adPriceMsg.value === '저장됨!' || !adPriceMsg.value ? '가격을 저장했어요' : adPriceMsg.value, adPriceMsg.value === '저장 실패')
}
async function mSavePageConfig() {
  await savePageConfig()
  say(pageConfigMsg.value === '저장 실패' ? '저장하지 못했어요' : '슬롯 수를 저장했어요', pageConfigMsg.value === '저장 실패')
}
function stepSlot(menu, field, d) {
  const cur = slotOf(menu.key)[field] || 0
  setSlot(menu, field, Math.max(0, Math.min(5, cur + d)))
}

// 할인 이벤트 시트
const promoSheet = ref(false)
function openPromoSheet(pr) {
  if (pr) editPromo(pr); else openPromoForm()
  showPromoForm.value = false   // PC 용 인라인 폼은 열지 않음
  promoSheet.value = true
}
function closePromoSheet() { if (!savingPromo.value) { promoSheet.value = false; resetPromoForm() } }
async function mSavePromo() {
  const f = promoForm.value
  if (!f.title.trim()) return say('제목을 입력하세요', true)
  if (!f.starts_at || !f.ends_at) return say('기간을 지정하세요', true)
  if (new Date(f.ends_at) <= new Date(f.starts_at)) return say('종료 일시가 시작보다 늦어야 해요', true)
  if (!(f.discount_pct >= 0 && f.discount_pct <= 100)) return say('할인율은 0~100 사이로 입력하세요', true)
  if (!f.applies_to_ads && !f.applies_to_packages) return say('적용 대상을 하나 이상 고르세요', true)
  savingPromo.value = true
  try {
    const url = editPromoId.value ? `/api/admin/pricing-promotions/${editPromoId.value}` : '/api/admin/pricing-promotions'
    await axios[editPromoId.value ? 'put' : 'post'](url, f)
    promoSheet.value = false; resetPromoForm()
    await loadPromos()
    say('저장했어요')
  } catch (e) { say(e.response?.data?.message || '저장하지 못했어요', true) }
  finally { savingPromo.value = false }
}
async function mDeletePromo() {
  const id = editPromoId.value
  const p = promotions.value.find(x => x.id === id)
  if (!p || !confirm(`'${p.title}' 이벤트를 삭제할까요?`)) return
  try { await axios.delete(`/api/admin/pricing-promotions/${id}`); promotions.value = promotions.value.filter(x => x.id !== id); promoSheet.value = false; resetPromoForm(); say('삭제했어요') }
  catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
}
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && (confirmSave.value || promoSheet.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

// ─── 초기 로드 ──────────────────────────────────────────────
onMounted(async () => {
  siteStore.load() // 활성 메뉴 리스트 필요
  await Promise.all([loadPoints(), loadAdSettings(), loadPromos()])
  loading.value = false
})
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
