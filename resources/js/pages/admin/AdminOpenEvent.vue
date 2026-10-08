<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3" :class="cfg ? 'pb-28' : 'pb-4'">
  <div class="px-0.5">
    <div class="flex items-center gap-2"><span class="text-[13px] font-bold px-2.5 py-1 rounded-full" :class="stateChip.cls">{{ stateChip.text }}</span><span class="text-[13px] text-ink-muted">오늘 {{ today }} (미국 동부)</span></div>
    <p class="text-[13px] text-ink-muted mt-1.5 leading-relaxed">소프트 오픈 기간에 포인트 적립·무료·할인을 한 번에 켜요. 원래 가격은 그대로 두고, 기간이 끝나면 자동으로 원래대로 돌아와요.</p>
  </div>
  <p v-if="error" class="bg-red-50 text-red-600 text-[14px] rounded-xl p-3">{{ error }}</p>
  <p v-if="notice" class="bg-emerald-50 text-emerald-700 text-[14px] rounded-xl p-3">{{ notice }}</p>
  <div v-if="loading" class="text-center text-ink-muted py-10 text-[15px]">불러오는 중...</div>

  <template v-else-if="cfg">
    <!-- 전체 켜기 / 기간 -->
    <div class="bg-white border-2 rounded-2xl p-3.5 space-y-3" :class="cfg.enabled ? 'border-rose-300' : 'border-gray-100'">
      <div class="flex items-center gap-3 min-h-[44px]"><div class="flex-1 min-w-0"><div class="text-[17px] font-bold text-ink">오픈 이벤트 사용</div><div class="text-[13px] text-ink-muted">{{ cfg.enabled ? '켜짐 — 기간 안에 자동 적용돼요' : '꺼짐 — 아무것도 바뀌지 않아요' }}</div></div>
        <button type="button" role="switch" :aria-checked="!!cfg.enabled" @click="cfg.enabled = !cfg.enabled" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="cfg.enabled ? 'bg-rose-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="cfg.enabled ? 'left-[26px]' : 'left-[4px]'"></span></button></div>
      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">시작일 (00:00부터)</span><input type="date" v-model="cfg.starts_on" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">종료일 (23:59까지)</span><input type="date" v-model="cfg.ends_on" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
      </div>
      <button @click="loadRecommended" class="w-full min-h-[48px] rounded-xl bg-blue-50 text-blue-700 text-[15px] font-bold">추천 설정 불러오기 (10/15 ~ 11/30)</button>
    </div>

    <!-- 적용 항목 -->
    <div class="text-[14px] font-bold text-ink-muted px-0.5 pt-1">적용할 항목</div>
    <div class="bg-white border rounded-2xl p-3.5" :class="cfg.earn.on ? 'border-rose-200' : 'border-gray-100'">
      <div class="flex items-center gap-3 min-h-[44px]"><div class="flex-1 min-w-0"><div class="text-[16px] font-bold text-ink">포인트 적립 배수</div><div class="text-[12px] text-ink-muted leading-snug">글쓰기·댓글·출석·가입 보너스 등 "받는 포인트"를 곱해요</div></div>
        <button type="button" role="switch" :aria-checked="!!cfg.earn.on" @click="cfg.earn.on = !cfg.earn.on" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="cfg.earn.on ? 'bg-rose-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="cfg.earn.on ? 'left-[26px]' : 'left-[4px]'"></span></button></div>
      <div v-if="cfg.earn.on" class="flex items-center gap-2 mt-2">
        <button type="button" @click="cfg.earn.multiplier = Math.max(1, (cfg.earn.multiplier || 1) - 1)" class="w-12 h-12 rounded-xl bg-gray-100 text-[22px] font-bold" aria-label="배수 줄이기">−</button>
        <span class="flex-1 text-center text-[22px] font-black tabular-nums text-rose-600">{{ cfg.earn.multiplier }}배</span>
        <button type="button" @click="cfg.earn.multiplier = Math.min(10, (cfg.earn.multiplier || 1) + 1)" class="w-12 h-12 rounded-xl bg-gray-100 text-[22px] font-bold" aria-label="배수 늘리기">+</button>
      </div>
    </div>

    <div v-for="m in meta" :key="m.key" class="bg-white border rounded-2xl p-3.5" :class="cfg.items[m.key].on ? 'border-rose-200' : 'border-gray-100'">
      <div class="flex items-center gap-3 min-h-[44px]"><div class="flex-1 min-w-0"><div class="text-[16px] font-bold text-ink">{{ m.label }}</div><div class="text-[12px] text-ink-muted leading-snug">{{ hints[m.key] }}</div></div>
        <button type="button" role="switch" :aria-checked="!!cfg.items[m.key].on" @click="cfg.items[m.key].on = !cfg.items[m.key].on" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="cfg.items[m.key].on ? 'bg-rose-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="cfg.items[m.key].on ? 'left-[26px]' : 'left-[4px]'"></span></button></div>
      <div v-if="cfg.items[m.key].on" class="mt-2 space-y-2">
        <div class="flex gap-2"><button v-for="pr in presets(m.key)" :key="pr" type="button" @click="cfg.items[m.key].pct = pr" class="flex-1 min-h-[44px] rounded-xl border text-[15px] font-bold" :class="cfg.items[m.key].pct === pr ? 'bg-rose-500 text-white border-rose-500' : 'bg-white text-ink border-gray-200'">{{ pr === 100 ? '무료' : pr + '%' }}</button></div>
        <label class="flex items-center gap-2"><input type="number" inputmode="numeric" min="0" :max="m.key === 'flyer_usd' ? 95 : 100" v-model.number="cfg.items[m.key].pct" class="flex-1 min-h-[48px] rounded-xl border border-gray-200 px-3 text-right tabular-nums" aria-label="할인율" /><span class="text-[14px] text-ink-muted">% 할인</span></label>
      </div>
    </div>

    <div class="bg-white border rounded-2xl p-3.5" :class="cfg.photos.on ? 'border-rose-200' : 'border-gray-100'">
      <div class="flex items-center gap-3 min-h-[44px]"><div class="flex-1 min-w-0"><div class="text-[16px] font-bold text-ink">장터·부동산 무료 사진 장수</div><div class="text-[12px] text-ink-muted leading-snug">기존 무료 장수보다 많을 때만 늘어나요 (등록 자체는 원래 무료)</div></div>
        <button type="button" role="switch" :aria-checked="!!cfg.photos.on" @click="cfg.photos.on = !cfg.photos.on" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="cfg.photos.on ? 'bg-rose-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="cfg.photos.on ? 'left-[26px]' : 'left-[4px]'"></span></button></div>
      <label v-if="cfg.photos.on" class="flex items-center gap-2 mt-2"><input type="number" inputmode="numeric" min="0" max="30" v-model.number="cfg.photos.count" class="flex-1 min-h-[48px] rounded-xl border border-gray-200 px-3 text-right tabular-nums" aria-label="무료 사진 장수" /><span class="text-[14px] text-ink-muted">장까지 무료</span></label>
    </div>

    <div class="bg-white border rounded-2xl p-3.5" :class="cfg.purchase_bonus.on ? 'border-rose-200' : 'border-gray-100'">
      <div class="flex items-center gap-3 min-h-[44px]"><div class="flex-1 min-w-0"><div class="text-[16px] font-bold text-ink">포인트 구매 추가 보너스</div><div class="text-[12px] text-ink-muted leading-snug">결제 금액 구간별 기존 보너스에 더해서 지급돼요</div></div>
        <button type="button" role="switch" :aria-checked="!!cfg.purchase_bonus.on" @click="cfg.purchase_bonus.on = !cfg.purchase_bonus.on" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="cfg.purchase_bonus.on ? 'bg-rose-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="cfg.purchase_bonus.on ? 'left-[26px]' : 'left-[4px]'"></span></button></div>
      <label v-if="cfg.purchase_bonus.on" class="flex items-center gap-2 mt-2"><span class="text-[14px] text-ink-muted">+</span><input type="number" inputmode="numeric" min="0" max="100" v-model.number="cfg.purchase_bonus.pct" class="flex-1 min-h-[48px] rounded-xl border border-gray-200 px-3 text-right tabular-nums" aria-label="추가 보너스 퍼센트" /><span class="text-[14px] text-ink-muted">%</span></label>
    </div>
    <p class="text-[12px] text-ink-faint px-0.5">달러 결제 할인은 최대 95%예요(0원 결제 방지). 최소 주문 금액은 이벤트 동안 50센트로 낮춰져요.</p>

    <!-- 안내 문구 -->
    <div class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-2">
      <div class="text-[16px] font-bold text-ink">사이트 상단 안내 문구</div>
      <input v-model="cfg.headline" maxlength="40" placeholder="제목" aria-label="제목" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
      <input v-model="cfg.subline" maxlength="100" placeholder="부제목" aria-label="부제목" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
      <div v-if="perks.length" class="flex flex-wrap gap-1.5 pt-1"><span v-for="pk in perks" :key="pk" class="text-[12px] font-bold px-2.5 py-1 rounded-full bg-rose-50 text-rose-600">{{ pk }}</span></div>
    </div>

    <!-- 미리보기 -->
    <div class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="text-[16px] font-bold text-ink">적용 미리보기 (원래 → 이벤트)</div>
      <p class="text-[12px] text-ink-muted mb-2">저장된 설정 기준이에요. 항목을 바꿨다면 저장 후 확인하세요.</p>
      <div v-for="(rows, g) in grouped" :key="g" class="mb-3 last:mb-0">
        <div class="text-[13px] font-bold text-ink-muted mb-1">{{ g }}</div>
        <div v-for="r in rows" :key="r.label" class="border-t border-gray-100 py-2 text-[14px]">
          <div class="text-ink">{{ r.label }}</div>
          <div class="flex items-center gap-2 text-[13px]"><span class="text-ink-muted">{{ r.original }}</span><span class="text-ink-faint">→</span><b :class="r.original !== r.event ? 'text-rose-600' : 'text-ink-muted'">{{ r.event }}</b></div>
        </div>
      </div>
    </div>

    <!-- 하단 고정 저장 -->
    <div class="fixed inset-x-0 z-30 px-3.5" :style="{ bottom: 'calc(70px + env(safe-area-inset-bottom, 0px))' }">
      <button @click="askSave" :disabled="saving" class="w-full min-h-[54px] rounded-2xl text-white text-[17px] font-bold shadow-lg disabled:opacity-50" :class="cfg.enabled ? 'bg-rose-500' : 'bg-ink'">{{ saving ? '저장 중...' : (cfg.enabled ? '적용하기' : '저장 (꺼짐 상태)') }}</button>
    </div>
  </template>

  <Teleport to="body">
    <div v-if="confirmSave" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="confirmSave = false">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-2">{{ cfg?.enabled ? '오픈 이벤트를 적용할까요?' : '이벤트를 끈 상태로 저장할까요?' }}</div>
        <div class="bg-gray-50 rounded-2xl p-3.5 space-y-1.5 text-[15px] mb-3">
          <div class="flex justify-between gap-3"><span class="text-ink-muted">기간</span><b class="text-right">{{ cfg?.starts_on }} ~ {{ cfg?.ends_on }}</b></div>
          <div class="flex justify-between gap-3"><span class="text-ink-muted">켜진 항목</span><b>{{ enabledCount }}개</b></div>
          <div v-if="cfg?.earn.on" class="flex justify-between gap-3"><span class="text-ink-muted">포인트 적립</span><b>{{ cfg.earn.multiplier }}배</b></div>
        </div>
        <p class="text-[13px] text-ink-muted mb-3">저장하면 사이트 전체에 1분 안에 반영돼요.</p>
        <button @click="doSave" :disabled="saving" class="w-full min-h-[52px] rounded-xl bg-rose-500 text-white text-[16px] font-bold disabled:opacity-50">{{ saving ? '저장 중...' : (cfg?.enabled ? '적용하기' : '저장하기') }}</button>
        <button @click="confirmSave = false" :disabled="saving" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 광고/가격 › 오픈 이벤트</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-rose-50 text-rose-600"><AppIcon name="gift" :size="20" /></span>
      오픈 이벤트
      <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="stateChip.cls">{{ stateChip.text }}</span>
    </h1>
    <p class="text-xs text-ink-faint mt-0.5">소프트 오픈 기간에 포인트 적립 · 무료 · 할인을 한 번에 켜요. 원래 가격은 그대로 두고, 기간(미국 동부 날짜)이 끝나면 자동으로 원래대로 돌아와요.</p>
  </div>

  <div v-if="error" class="mb-3 bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl p-3">{{ error }}</div>
  <div v-if="notice" class="mb-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl p-3">{{ notice }}</div>

  <div v-if="loading" class="card px-4 py-8 text-sm text-ink-muted text-center">불러오는 중...</div>

  <template v-else-if="cfg">
    <!-- 1. 전체 켜기 / 기간 -->
    <div class="card p-4 mb-3">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="cfg.enabled" class="w-5 h-5 accent-rose-500" />
          <span class="font-bold text-ink">오픈 이벤트 사용</span>
        </label>
        <button @click="loadRecommended" class="text-xs font-bold text-blue-600 hover:underline">추천 설정 불러오기 (10/15 ~ 11/30)</button>
      </div>
      <p class="text-xs text-ink-muted mt-1">꺼져 있으면 아무것도 바뀌지 않아요. 켜 두면 시작일 00:00부터 종료일 23:59까지(미국 동부 시간) 자동 적용돼요. 오늘 날짜: {{ today }}</p>
      <div class="grid grid-cols-2 gap-3 mt-3 max-w-md">
        <label class="text-xs text-ink-muted">시작일
          <input type="date" v-model="cfg.starts_on" class="input-soft w-full mt-1" />
        </label>
        <label class="text-xs text-ink-muted">종료일
          <input type="date" v-model="cfg.ends_on" class="input-soft w-full mt-1" />
        </label>
      </div>
    </div>

    <!-- 2. 적용 항목 선택 -->
    <div class="card p-4 mb-3">
      <div class="font-bold text-ink mb-2">적용할 항목 선택</div>
      <div class="divide-y divide-gray-100">
        <div class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.earn.on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">포인트 적립 · 가입 보너스 배수</div>
            <div class="text-[11px] text-ink-muted">글쓰기·댓글·출석·가입 보너스 등 "받는 포인트"를 곱해요. (하루 횟수 한도는 그대로)</div>
          </div>
          <select v-model.number="cfg.earn.multiplier" :disabled="!cfg.earn.on" class="input-soft !w-auto !px-2 !py-1 text-sm">
            <option v-for="n in 10" :key="n" :value="n">{{ n }}배</option>
          </select>
        </div>

        <div v-for="m in meta" :key="m.key" class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.items[m.key].on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">{{ m.label }}</div>
            <div class="text-[11px] text-ink-muted">{{ hints[m.key] }}</div>
          </div>
          <div class="flex items-center gap-1" :class="!cfg.items[m.key].on ? 'opacity-40' : ''">
            <button v-for="p in presets(m.key)" :key="p" type="button" @click="cfg.items[m.key].pct = p" :disabled="!cfg.items[m.key].on"
              class="text-[11px] font-bold px-2 py-1 rounded-md" :class="cfg.items[m.key].pct === p ? 'bg-rose-500 text-white' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'">{{ p === 100 ? '무료' : p + '%' }}</button>
            <input type="number" min="0" :max="m.key === 'flyer_usd' ? 95 : 100" v-model.number="cfg.items[m.key].pct" :disabled="!cfg.items[m.key].on" class="input-soft !w-16 !px-2 !py-1 text-sm text-right" />
            <span class="text-xs text-ink-muted">% 할인</span>
          </div>
        </div>

        <div class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.photos.on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">장터·부동산 무료 사진 장수</div>
            <div class="text-[11px] text-ink-muted">기존 무료 장수보다 많을 때만 늘어나요. (등록 자체는 원래 무료)</div>
          </div>
          <div class="flex items-center gap-1" :class="!cfg.photos.on ? 'opacity-40' : ''">
            <input type="number" min="0" max="30" v-model.number="cfg.photos.count" :disabled="!cfg.photos.on" class="input-soft !w-16 !px-2 !py-1 text-sm text-right" />
            <span class="text-xs text-ink-muted">장까지 무료</span>
          </div>
        </div>

        <div class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.purchase_bonus.on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">포인트 구매 추가 보너스</div>
            <div class="text-[11px] text-ink-muted">결제 금액 구간별 기존 보너스에 더해서 지급돼요.</div>
          </div>
          <div class="flex items-center gap-1" :class="!cfg.purchase_bonus.on ? 'opacity-40' : ''">
            <span class="text-xs text-ink-muted">+</span>
            <input type="number" min="0" max="100" v-model.number="cfg.purchase_bonus.pct" :disabled="!cfg.purchase_bonus.on" class="input-soft !w-16 !px-2 !py-1 text-sm text-right" />
            <span class="text-xs text-ink-muted">%</span>
          </div>
        </div>
      </div>
      <p class="text-[11px] text-ink-faint mt-2">달러 결제 할인은 최대 95%예요(0원 결제 방지). 최소 주문 금액은 이벤트 동안 50센트로 낮춰져요.</p>
    </div>

    <!-- 3. 사이트 안내 문구 -->
    <div class="card p-4 mb-3">
      <div class="font-bold text-ink mb-2">사이트 상단 안내 문구</div>
      <div class="space-y-2 max-w-xl">
        <input v-model="cfg.headline" maxlength="40" placeholder="제목" class="input-soft w-full" />
        <input v-model="cfg.subline" maxlength="100" placeholder="부제목" class="input-soft w-full" />
      </div>
      <div v-if="perks.length" class="flex flex-wrap gap-1.5 mt-3">
        <span v-for="p in perks" :key="p" class="text-[11px] font-bold px-2 py-1 rounded-full bg-rose-50 text-rose-600">{{ p }}</span>
      </div>
    </div>

    <!-- 4. 저장 -->
    <div class="flex items-center gap-2 mb-4">
      <button @click="save" :disabled="saving" class="btn-primary px-5 py-2 text-sm">{{ saving ? '저장 중...' : (cfg.enabled ? '적용하기' : '저장 (꺼짐 상태)') }}</button>
      <span class="text-xs text-ink-muted">저장하면 사이트 전체에 1분 안에 반영돼요.</span>
    </div>

    <!-- 5. 미리보기 -->
    <div class="card p-4">
      <div class="font-bold text-ink mb-1">적용 미리보기 (원래 → 이벤트)</div>
      <p class="text-[11px] text-ink-muted mb-2">저장된 설정 기준이에요. 항목을 바꿨다면 저장 후 확인하세요.</p>
      <div v-for="(rows, g) in grouped" :key="g" class="mb-3">
        <div class="text-xs font-bold text-ink-muted mb-1">{{ g }}</div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <tbody>
              <tr v-for="r in rows" :key="r.label" class="border-t border-gray-100">
                <td class="py-1.5 pr-2 text-ink">{{ r.label }}</td>
                <td class="py-1.5 pr-2 text-ink-muted whitespace-nowrap text-right">{{ r.original }}</td>
                <td class="py-1.5 px-1 text-ink-faint">→</td>
                <td class="py-1.5 font-bold whitespace-nowrap text-right" :class="r.original !== r.event ? 'text-rose-600' : 'text-ink-muted'">{{ r.event }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </template>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 스위치 카드 + 저장 전 확인 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const confirmSave = ref(false)

const cfg = ref(null)
const state = ref('off')
const today = ref('')
const meta = ref([])
const perks = ref([])
const preview = ref([])
const defaults = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const notice = ref('')

const hints = {
  chat_create: '1:1 · 그룹 · 공개 채팅방을 만들 때 드는 포인트',
  chat_entry: '공개 채팅방에 들어갈 때 드는 포인트 (기본은 선택 안 함)',
  bump: '중고장터 끌어올리기 비용 (회차가 늘수록 올라가는 금액도 함께 할인)',
  promotion: '구인구직·장터·부동산·업소록·동호회 상위노출 하루 가격',
  banner: '배너·텍스트 광고 신청 시 실제로 빠지는 포인트',
  flyer_usd: 'NEW 전단 광고를 달러로 결제할 때 (카드/PayPal 등)',
}

const stateChip = computed(() => ({
  off: { text: '꺼짐', cls: 'bg-gray-100 text-ink-muted' },
  scheduled: { text: '예정', cls: 'bg-amber-50 text-amber-700' },
  active: { text: '진행 중', cls: 'bg-emerald-50 text-emerald-700' },
  ended: { text: '종료', cls: 'bg-gray-100 text-ink-muted' },
}[state.value] || { text: '', cls: '' }))

const grouped = computed(() => {
  const g = {}
  for (const r of preview.value) (g[r.group] ||= []).push(r)
  return g
})

const presets = (key) => key === 'flyer_usd' ? [50, 90, 95] : [50, 90, 100]

function apply(d) {
  cfg.value = d.config
  state.value = d.state
  perks.value = d.perks || []
  preview.value = d.preview || []
  if (d.today) today.value = d.today
  if (d.items_meta) meta.value = d.items_meta
  if (d.defaults) defaults.value = d.defaults
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/open-event')
    apply(data.data)
    error.value = ''
  } catch (e) {
    error.value = e.response?.status === 403 ? '최고 관리자만 볼 수 있는 화면이에요.' : '불러오지 못했어요.'
  } finally { loading.value = false }
}

function loadRecommended() {
  if (!defaults.value) return
  const d = JSON.parse(JSON.stringify(defaults.value))
  d.enabled = cfg.value.enabled
  cfg.value = d
  notice.value = '추천 설정을 불러왔어요. 아래 "적용하기"를 눌러야 저장돼요.'
}

async function save() {
  error.value = ''; notice.value = ''
  if (cfg.value.enabled && cfg.value.ends_on < cfg.value.starts_on) { error.value = '종료일이 시작일보다 빠를 수 없어요.'; return }
  saving.value = true
  try {
    const { data } = await axios.put('/api/admin/open-event', cfg.value)
    apply(data.data)
    notice.value = data.message || '저장했어요.'
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(' ') : '저장하지 못했어요.'
  } finally { saving.value = false }
}

// 휴대폰: 사이트 전체 가격이 바뀌는 설정이라 저장 전에 한 번 더 확인
const enabledCount = computed(() => {
  if (!cfg.value) return 0
  return (cfg.value.earn.on ? 1 : 0) + Object.values(cfg.value.items || {}).filter(i => i.on).length + (cfg.value.photos.on ? 1 : 0) + (cfg.value.purchase_bonus.on ? 1 : 0)
})
function askSave() {
  error.value = ''; notice.value = ''
  if (cfg.value.enabled && cfg.value.ends_on < cfg.value.starts_on) { error.value = '종료일이 시작일보다 빠를 수 없어요.'; window.scrollTo({ top: 0, behavior: 'smooth' }); return }
  confirmSave.value = true
}
async function doSave() {
  await save()
  confirmSave.value = false
  if (error.value) window.scrollTo({ top: 0, behavior: 'smooth' })
}
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && confirmSave.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = '' })

onMounted(load)
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
