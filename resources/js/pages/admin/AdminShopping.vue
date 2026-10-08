<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted px-0.5">Amazon 제휴 상품 · 태그 {{ associateTag || 'awesomekorean-20' }}</p>
  <div class="grid grid-cols-3 gap-2">
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">전체</div><div class="text-[20px] font-bold text-ink">{{ stats.total ?? 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">활성</div><div class="text-[20px] font-bold text-green-600">{{ stats.active ?? 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3"><div class="text-[12px] text-ink-muted">추천</div><div class="text-[20px] font-bold text-blue-600">{{ stats.featured ?? 0 }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3 col-span-2"><div class="text-[12px] text-ink-muted">클릭 (오늘 / 전체)</div><div class="text-[20px] font-bold text-purple-600">{{ stats.clicks_today ?? 0 }} <span class="text-[14px] text-ink-muted font-medium">/ {{ stats.clicks_total ?? 0 }}</span></div></div>
  </div>
  <p class="text-[12px] text-ink-faint px-0.5">※ 클릭 수는 참고용이에요. 실제 구매·수수료는 Amazon Associates Central에서 확인하세요.</p>

  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="쇼핑 메뉴">
    <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key" :aria-pressed="activeTab === t.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="activeTab === t.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ t.label }}</button>
  </div>

  <!-- 상품 -->
  <template v-if="activeTab === 'products'">
    <div class="flex gap-2">
      <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
        <AppIcon name="search" :size="18" />
        <input v-model="search" @keyup.enter="load(1)" type="search" placeholder="상품명 검색" aria-label="상품명 검색" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" />
      </label>
      <button @click="load(1)" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
    </div>
    <select v-model="category" @change="load(1)" aria-label="카테고리" class="w-full min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink">
      <option value="">전체 카테고리</option>
      <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
    </select>
    <button @click="openCreate" class="w-full min-h-[52px] rounded-xl bg-orange-500 text-white text-[16px] font-bold">➕ 상품 등록</button>
    <div v-if="!products.length" class="text-center py-12 text-ink-muted text-[15px]">등록된 상품이 없어요.</div>
    <div v-for="product in products" :key="product.id" class="bg-white border border-gray-100 rounded-2xl p-3.5" :class="product.is_active ? '' : 'opacity-70'">
      <div class="flex gap-3">
        <img v-if="product.image_url" :src="product.image_url" alt="" class="w-16 h-16 object-cover rounded-xl border border-gray-100 shrink-0" @error="e=>e.target.style.display='none'" />
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span v-if="product.is_featured" class="text-[12px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">추천</span>
            <span class="text-[12px] font-bold px-1.5 py-0.5 rounded" :class="product.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ product.is_active ? '활성' : '비활성' }}</span>
          </div>
          <div class="text-[16px] font-bold text-ink leading-snug break-words line-clamp-2">{{ product.title }}</div>
          <div class="text-[13px] text-ink-muted mt-0.5">{{ product.category || '카테고리 없음' }} · {{ product.price ? '$' + Number(product.price).toLocaleString() : '가격 없음' }}</div>
          <div class="text-[12px] text-ink-faint break-all">ASIN {{ product.asin }} · 클릭 {{ product.clicks }} · 순서 {{ product.display_order }}</div>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-2 mt-3">
        <button @click="openEdit(product)" class="min-h-[46px] rounded-xl bg-blue-50 text-blue-700 text-[15px] font-bold">수정</button>
        <button @click="mToggle(product)" class="min-h-[46px] rounded-xl bg-amber-50 text-amber-700 text-[15px] font-bold">{{ product.is_active ? '비활성' : '활성' }}</button>
        <button @click="mDel = product" class="min-h-[46px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold">삭제</button>
      </div>
    </div>
    <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
      <button @click="load(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
      <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
      <button @click="load(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
    </div>
  </template>

  <!-- 카테고리 -->
  <template v-else-if="activeTab === 'cat'">
    <p class="text-[14px] text-ink-muted">카테고리는 {{ categories.length }}개로 고정돼 있어요. 현재 불러온 상품 기준 개수예요.</p>
    <div v-for="c in categoryStats" :key="c.name" class="bg-white border border-gray-100 rounded-2xl px-4 min-h-[52px] flex items-center justify-between">
      <span class="text-[15px] text-ink">🏷 {{ c.name }}</span><span class="text-[13px] font-bold text-ink-muted bg-gray-100 px-2.5 py-1 rounded-full">{{ c.count }}개</span>
    </div>
  </template>
  <AdminShoppingReviews v-else-if="activeTab === 'member'" />
  <AdminAssociatesGuide v-else-if="activeTab === 'guide'" />

  <Teleport to="body">
    <!-- 상품 등록·수정 (전체 화면) -->
    <div v-if="editing" class="alv-m fixed inset-0 z-[60] bg-white flex flex-col" role="dialog" aria-modal="true" :aria-label="isNew ? '상품 등록' : '상품 수정'">
      <div class="shrink-0 flex items-center gap-2 px-2 border-b border-gray-100" :style="{ paddingTop: 'env(safe-area-inset-top, 0px)' }">
        <button @click="editing = null" class="min-h-[52px] min-w-[52px] grid place-items-center text-[22px]" aria-label="닫기">✕</button>
        <div class="flex-1 min-w-0 text-[16px] font-bold text-ink truncate">{{ isNew ? '상품 등록' : '상품 수정' }}</div>
      </div>
      <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
        <div v-if="isNew">
          <label class="block text-[14px] font-bold text-ink mb-1" for="sp-input">ASIN 또는 Amazon 주소</label>
          <input id="sp-input" v-model="editing.input" inputmode="url" autocapitalize="none" placeholder="B0ABCDEFGH 또는 amazon.com/dp/..." class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
          <p class="text-[12px] text-ink-faint mt-1">상품명·이미지·가격은 자동으로 가져오지 않아요(제휴 정책상 금지). 아래에 직접 입력해 주세요.</p>
        </div>
        <div>
          <label class="block text-[14px] font-bold text-ink mb-1" for="sp-title">상품명</label>
          <input id="sp-title" v-model="editing.title" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[14px] font-bold text-ink mb-1" for="sp-cat">카테고리</label>
            <select id="sp-cat" v-model="editing.category" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-2 bg-white">
              <option value="">선택 안함</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[14px] font-bold text-ink mb-1" for="sp-price">가격 ($)</label>
            <input id="sp-price" v-model.number="editing.price" type="number" inputmode="decimal" step="0.01" min="0" placeholder="15.99" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
          </div>
        </div>
        <p class="text-[12px] text-ink-faint -mt-2">가격은 참고용이에요 (실시간 Amazon 가격이 아님).</p>

        <div>
          <div class="text-[14px] font-bold text-ink mb-1">Amazon 이미지 주소</div>
          <p class="text-[12px] text-ink-faint mb-1.5">이미지를 길게 눌러 “이미지 주소 복사” 후 붙여넣기. 서버에 저장하지 않아요.</p>
          <div v-for="(url, i) in editing.amazon_image_urls" :key="i" class="flex items-center gap-2 mb-2">
            <div class="w-12 h-12 shrink-0 border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
              <img v-if="url" :src="url" alt="" class="w-full h-full object-cover" @error="e=>e.target.style.opacity=0.2" @load="e=>e.target.style.opacity=1" />
            </div>
            <input v-model="editing.amazon_image_urls[i]" inputmode="url" autocapitalize="none" :aria-label="`Amazon 이미지 주소 ${i + 1}`" class="flex-1 min-w-0 min-h-[48px] rounded-xl border border-gray-200 px-3" />
            <button type="button" @click="editing.amazon_image_urls.splice(i, 1)" class="shrink-0 min-h-[48px] min-w-[48px] rounded-xl bg-red-50 text-red-600 text-[14px] font-bold" aria-label="이미지 주소 삭제">삭제</button>
          </div>
          <button type="button" @click="editing.amazon_image_urls.push('')" class="min-h-[44px] px-4 rounded-xl bg-amber-50 text-amber-700 text-[14px] font-bold">+ 이미지 주소 추가</button>
        </div>

        <div>
          <div class="text-[14px] font-bold text-ink mb-1">직접 올린 사진 <span class="font-normal text-ink-muted">(최대 10장)</span></div>
          <div class="flex flex-wrap gap-2">
            <div v-for="(url, i) in editing.keep_own_image_urls" :key="'k'+i" class="relative w-20 h-20 rounded-xl overflow-hidden border border-gray-200">
              <img :src="url" alt="" class="w-full h-full object-cover" />
              <button type="button" @click="editing.keep_own_image_urls.splice(i, 1)" class="absolute top-0.5 right-0.5 w-8 h-8 rounded-full bg-black/60 text-white text-[14px]" aria-label="사진 삭제">✕</button>
            </div>
            <div v-for="(f, i) in newOwnImages" :key="'n'+i" class="relative w-20 h-20 rounded-xl overflow-hidden border border-gray-200">
              <img :src="f.preview" alt="" class="w-full h-full object-cover" />
              <button type="button" @click="newOwnImages.splice(i, 1)" class="absolute top-0.5 right-0.5 w-8 h-8 rounded-full bg-black/60 text-white text-[14px]" aria-label="사진 삭제">✕</button>
            </div>
            <label v-if="(editing.keep_own_image_urls.length + newOwnImages.length) < 10" class="w-20 h-20 rounded-xl bg-gray-50 border border-dashed border-gray-300 text-ink-muted flex flex-col items-center justify-center cursor-pointer text-[12px] gap-0.5">
              <AppIcon name="camera" :size="22" :stroke-width="1.5" />사진 추가
              <input type="file" multiple accept="image/*" @change="onSelectOwnImages" class="hidden" />
            </label>
          </div>
        </div>

        <div>
          <div class="text-[14px] font-bold text-ink mb-1">추천·설명 문구</div>
          <div class="flex gap-1 overflow-x-auto scrollbar-hide mb-1.5 p-1 bg-gray-50 rounded-xl" role="toolbar" aria-label="글 서식">
            <button type="button" @click="execCmd('bold')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white font-bold text-[15px]" aria-label="굵게">B</button>
            <button type="button" @click="execCmd('italic')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white italic text-[15px]" aria-label="기울임">I</button>
            <button type="button" @click="execCmd('underline')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white underline text-[15px]" aria-label="밑줄">U</button>
            <button type="button" @click="execCmd('formatBlock', 'H2')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white font-bold text-[13px]">H2</button>
            <button type="button" @click="execCmd('formatBlock', 'H3')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white font-bold text-[13px]">H3</button>
            <button type="button" @click="execCmd('formatBlock', 'P')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white text-[13px]">본문</button>
            <button type="button" @click="execCmd('insertUnorderedList')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white text-[15px]" aria-label="글머리 기호">•</button>
            <button type="button" @click="execCmd('insertOrderedList')" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white text-[13px]" aria-label="번호 목록">1.</button>
            <button type="button" @click="descImageInputRef?.click()" class="shrink-0 min-h-[44px] min-w-[44px] rounded-lg bg-white grid place-items-center" aria-label="이미지 삽입"><AppIcon name="image" :size="18" /></button>
            <button type="button" @click="execCmd('removeFormat')" class="shrink-0 min-h-[44px] px-3 rounded-lg bg-white text-[13px] text-ink-muted">서식 지우기</button>
          </div>
          <input ref="descImageInputRef" type="file" accept="image/*" @change="onInsertDescImage" class="hidden" />
          <div ref="editorRef" contenteditable="true" @input="syncDescription" role="textbox" aria-multiline="true" aria-label="추천 설명"
            class="min-h-[160px] py-2.5 leading-relaxed border border-gray-200 rounded-xl px-3 text-[16px]"
            data-placeholder="이 상품을 추천하는 이유, 사용 후기 등을 자유롭게 작성해 주세요"></div>
        </div>

        <div class="grid grid-cols-1 gap-2">
          <div>
            <label class="block text-[14px] font-bold text-ink mb-1" for="sp-order">표시 순서 (작을수록 위)</label>
            <input id="sp-order" v-model.number="editing.display_order" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
          </div>
          <button type="button" @click="editing.is_featured = !editing.is_featured" role="switch" :aria-checked="!!editing.is_featured" class="min-h-[52px] rounded-xl border px-4 flex items-center justify-between text-[15px]" :class="editing.is_featured ? 'bg-red-50 border-red-200 text-red-700 font-bold' : 'bg-white border-gray-200 text-ink'"><span>추천 상품 (Featured)</span><span>{{ editing.is_featured ? '켜짐' : '꺼짐' }}</span></button>
          <button type="button" @click="editing.is_active = !editing.is_active" role="switch" :aria-checked="!!editing.is_active" class="min-h-[52px] rounded-xl border px-4 flex items-center justify-between text-[15px]" :class="editing.is_active ? 'bg-green-50 border-green-200 text-green-700 font-bold' : 'bg-white border-gray-200 text-ink'"><span>사이트에 공개 (활성)</span><span>{{ editing.is_active ? '켜짐' : '꺼짐' }}</span></button>
        </div>
      </div>
      <div class="shrink-0 grid grid-cols-3 gap-2 px-4 pt-3 border-t border-gray-100" :style="{ paddingBottom: 'calc(12px + env(safe-area-inset-bottom, 0px))' }">
        <button @click="editing = null" class="min-h-[52px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        <button @click="save" :disabled="saving || !editing.title?.trim() || (isNew && !editing.input?.trim())" class="col-span-2 min-h-[52px] rounded-xl bg-orange-500 text-white text-[16px] font-bold disabled:opacity-40">{{ saving ? '저장 중...' : '저장' }}</button>
      </div>
    </div>

    <!-- 삭제 확인 시트 -->
    <div v-if="mDel" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="mDel = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">상품을 삭제할까요?</div>
        <p class="text-[15px] text-ink-light mb-3 break-words">{{ mDel.title }}</p>
        <button @click="mRemove" :disabled="mBusy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ mBusy ? '삭제 중...' : '삭제하기' }}</button>
        <button @click="mDel = null" :disabled="mBusy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <!-- 헤더 — 다른 게시판 관리 화면(AdminInfo 등)과 동일한 형식 -->
  <div class="mb-4 flex items-start justify-between flex-wrap gap-2">
    <div>
      <div class="text-xs text-ink-muted">관리자 › 게시판 관리 › 쇼핑</div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600 text-lg">🛍️</span>
        쇼핑 관리 (Amazon 제휴)
      </h1>
      <p class="text-xs text-ink-faint mt-0.5">Amazon Associates 제휴 상품을 등록/관리합니다 · 태그: {{ associateTag || 'awesomekorean-20' }}</p>
    </div>
    <button @click="openCreate" class="inline-flex items-center gap-1.5 bg-orange-500 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-orange-600 transition-colors">
      ➕ 상품 등록
    </button>
  </div>

  <!-- 통계 카드 -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="card p-3">
      <div class="text-xs text-ink-muted">전체 상품</div>
      <div class="text-xl font-bold text-ink">{{ stats.total ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">활성 상품</div>
      <div class="text-xl font-bold text-green-600">{{ stats.active ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">추천 상품</div>
      <div class="text-xl font-bold text-blue-600">{{ stats.featured ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">총 클릭</div>
      <div class="text-xl font-bold text-purple-600">{{ stats.clicks_total ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">오늘 클릭</div>
      <div class="text-xl font-bold text-red-600">{{ stats.clicks_today ?? 0 }}</div>
    </div>
  </div>
  <p class="text-[11px] text-ink-faint mb-4">※ 클릭 수는 자체 집계용 참고 데이터이며, 실제 구매/커미션은 Amazon Associates Central에서 확인해야 합니다.</p>

  <!-- 탭 네비 -->
  <div class="bg-white rounded-t-2xl border border-b-0 border-gray-100">
    <div class="flex overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="flex items-center gap-1.5 px-4 py-3 text-sm whitespace-nowrap border-b-2 transition-colors"
        :class="activeTab === t.key ? 'border-amber-500 text-amber-700 font-bold bg-amber-50' : 'border-transparent text-ink-muted hover:text-ink'">
        <AppIcon :name="t.icon" :size="14" />{{ t.label }}
      </button>
    </div>
  </div>

  <div class="bg-white rounded-b-2xl border border-t-0 border-gray-100 p-4 min-h-[400px]">
    <!-- 🛍 상품 -->
    <div v-if="activeTab === 'products'">
      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="category" @change="load(1)" class="border border-line rounded-lg px-3 py-1.5 text-sm">
          <option value="">전체 카테고리</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <input v-model="search" @keyup.enter="load(1)" placeholder="상품명 검색" class="border border-line rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[160px]" />
      </div>

      <div class="overflow-x-auto border border-line rounded-xl">
        <table class="w-full text-sm">
          <thead class="bg-surface text-ink-light text-xs">
            <tr>
              <th class="text-left px-3 py-2">상품</th>
              <th class="text-left px-3 py-2">ASIN</th>
              <th class="text-left px-3 py-2">카테고리</th>
              <th class="text-left px-3 py-2">가격</th>
              <th class="text-left px-3 py-2">순서</th>
              <th class="text-left px-3 py-2">클릭</th>
              <th class="text-left px-3 py-2">상태</th>
              <th class="text-right px-3 py-2">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in products" :key="product.id" class="border-t border-line">
              <td class="px-3 py-2 max-w-xs">
                <div class="flex items-center gap-2">
                  <img v-if="product.image_url" :src="product.image_url" class="w-8 h-8 object-cover rounded-lg border border-line" @error="e=>e.target.style.display='none'" />
                  <span class="truncate">{{ product.title }}</span>
                  <span v-if="product.is_featured" class="badge-red !text-[10px]">추천</span>
                </div>
              </td>
              <td class="px-3 py-2 text-xs text-ink-light">{{ product.asin }}</td>
              <td class="px-3 py-2">{{ product.category || '-' }}</td>
              <td class="px-3 py-2">{{ product.price ? '$' + Number(product.price).toLocaleString() : '-' }}</td>
              <td class="px-3 py-2">{{ product.display_order }}</td>
              <td class="px-3 py-2">{{ product.clicks }}</td>
              <td class="px-3 py-2">
                <span :class="product.is_active ? 'text-emerald-600' : 'text-ink-faint'">{{ product.is_active ? '활성' : '비활성' }}</span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <button @click="openEdit(product)" class="text-blue-600 hover:underline mr-2">수정</button>
                <button @click="toggle(product)" class="text-amber-600 hover:underline mr-2">{{ product.is_active ? '비활성' : '활성' }}</button>
                <button @click="remove(product)" class="text-red-600 hover:underline">삭제</button>
              </td>
            </tr>
            <tr v-if="!products.length">
              <td colspan="8" class="px-3 py-8 text-center text-ink-faint text-sm">등록된 상품이 없습니다</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-center gap-2 mt-4" v-if="lastPage > 1">
        <button v-for="p in lastPage" :key="p" @click="load(p)"
          class="w-8 h-8 rounded-lg text-sm" :class="p === page ? 'bg-orange-500 text-white' : 'bg-surface text-ink-light'">{{ p }}</button>
      </div>
    </div>

    <!-- 📂 카테고리 — 고정 분류, 실제 등록 수만 참고용으로 표시 -->
    <div v-else-if="activeTab === 'guide'">
      <AdminAssociatesGuide />
    </div>

    <div v-else-if="activeTab === 'member'">
      <AdminShoppingReviews />
    </div>

    <div v-else-if="activeTab === 'cat'">
      <div class="text-sm text-ink-light mb-3">쇼핑 카테고리는 {{ categories.length }}개로 고정돼 있습니다 (상품 등록 시 하나를 배정). 실제 상품 수 기준 집계입니다.</div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
        <div v-for="c in categoryStats" :key="c.name" class="flex items-center justify-between border border-line rounded-xl px-3 py-2.5">
          <span class="flex items-center gap-1.5 text-sm text-ink"><span>🏷</span>{{ c.name }}</span>
          <span class="badge-gray !text-xs">{{ c.count }}개</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 등록/수정 모달 -->
  <div v-if="editing && !isMobile" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="editing=null">
    <div class="bg-white rounded-2xl p-5 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
      <h2 class="font-bold mb-3">{{ isNew ? '상품 등록' : '상품 수정' }}</h2>
      <div class="space-y-3 text-sm">
        <div v-if="isNew">
          <label class="block text-xs text-ink-light mb-1">ASIN 또는 Amazon 상품 URL</label>
          <input v-model="editing.input" placeholder="예: B0ABCDEFGH 또는 https://www.amazon.com/dp/B0ABCDEFGH" class="w-full border border-line rounded-lg px-3 py-2" />
          <p class="text-[11px] text-ink-faint mt-1">상품명/이미지/가격은 Amazon에서 자동으로 가져오지 않습니다(제휴 정책상 scraping 금지) — 아래에 직접 입력해주세요.</p>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">상품명</label>
          <input v-model="editing.title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div class="flex gap-3">
          <div class="flex-1">
            <label class="block text-xs text-ink-light mb-1">카테고리</label>
            <select v-model="editing.category" class="w-full border border-line rounded-lg px-3 py-2">
              <option value="">선택 안함</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-xs text-ink-light mb-1">가격 ($, 참고용 — 실시간 Amazon 가격 아님)</label>
            <input v-model.number="editing.price" type="number" step="0.01" min="0" placeholder="예: 15.99" class="w-full border border-line rounded-lg px-3 py-2" />
          </div>
        </div>

        <!-- Amazon 이미지 URL (여러 장) -->
        <div>
          <label class="block text-xs text-ink-light mb-1">Amazon 상품 이미지 URL (우클릭 → "이미지 주소 복사" 후 붙여넣기 — 서버에 저장하지 않음, 여러 장 가능)</label>
          <div v-for="(url, i) in editing.amazon_image_urls" :key="i" class="flex items-center gap-2 mb-1.5">
            <div class="w-10 h-10 flex-shrink-0 border border-line rounded-lg overflow-hidden bg-gray-50">
              <img v-if="url" :src="url" class="w-full h-full object-cover" @error="e=>e.target.style.opacity=0.2" @load="e=>e.target.style.opacity=1" />
            </div>
            <input v-model="editing.amazon_image_urls[i]" class="flex-1 border border-line rounded-lg px-3 py-2" />
            <button type="button" @click="editing.amazon_image_urls.splice(i,1)" class="text-red-500 text-xs px-2">삭제</button>
          </div>
          <button type="button" @click="editing.amazon_image_urls.push('')" class="text-xs text-amber-600 hover:underline">+ Amazon 이미지 추가</button>
        </div>

        <!-- 직접 업로드 이미지 (여러 장) -->
        <div>
          <label class="block text-xs text-ink-light mb-1">직접 촬영/제작한 이미지 (서버에 저장됨 — Amazon 이미지가 아닌 것만, 여러 장 가능)</label>
          <div class="flex flex-wrap gap-2">
            <div v-for="(url, i) in editing.keep_own_image_urls" :key="'k'+i" class="relative w-16 h-16 rounded-lg overflow-hidden border border-line group">
              <img :src="url" class="w-full h-full object-cover" />
              <button type="button" @click="editing.keep_own_image_urls.splice(i,1)"
                class="absolute inset-0 bg-black/40 text-white text-xs opacity-0 group-hover:opacity-100 flex items-center justify-center transition">삭제</button>
            </div>
            <div v-for="(f, i) in newOwnImages" :key="'n'+i" class="relative w-16 h-16 rounded-lg overflow-hidden border border-line group">
              <img :src="f.preview" class="w-full h-full object-cover" />
              <button type="button" @click="newOwnImages.splice(i,1)"
                class="absolute inset-0 bg-black/40 text-white text-xs opacity-0 group-hover:opacity-100 flex items-center justify-center transition">삭제</button>
            </div>
            <label v-if="(editing.keep_own_image_urls.length + newOwnImages.length) < 10"
              class="w-16 h-16 rounded-lg bg-surface text-ink-muted flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 transition-colors text-xs">
              <AppIcon name="camera" :size="18" :stroke-width="1.5" />
              <input type="file" multiple accept="image/*" @change="onSelectOwnImages" class="hidden" />
            </label>
          </div>
        </div>

        <!-- 추천/설명 문구 — 서식 편집 에디터 -->
        <div>
          <label class="block text-xs text-ink-light mb-1">Awesome Korean 추천/설명 문구</label>
          <div class="flex flex-wrap items-center gap-1 mb-1.5 p-1.5 bg-surface rounded-lg">
            <button type="button" @click="execCmd('bold')" title="굵게" class="w-7 h-7 rounded-md hover:bg-gray-200 font-bold text-ink-light text-xs">B</button>
            <button type="button" @click="execCmd('italic')" title="기울임" class="w-7 h-7 rounded-md hover:bg-gray-200 italic text-ink-light text-xs">I</button>
            <button type="button" @click="execCmd('underline')" title="밑줄" class="w-7 h-7 rounded-md hover:bg-gray-200 underline text-ink-light text-xs">U</button>
            <span class="w-px h-4 bg-gray-300 mx-1"></span>
            <button type="button" @click="execCmd('formatBlock', 'H2')" title="제목 H2" class="px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] font-bold text-ink-light">H2</button>
            <button type="button" @click="execCmd('formatBlock', 'H3')" title="제목 H3" class="px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] font-bold text-ink-light">H3</button>
            <button type="button" @click="execCmd('formatBlock', 'P')" title="단락" class="px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] text-ink-light">P</button>
            <span class="w-px h-4 bg-gray-300 mx-1"></span>
            <button type="button" @click="execCmd('insertUnorderedList')" title="글머리 기호" class="w-7 h-7 rounded-md hover:bg-gray-200 text-ink-light text-xs">•</button>
            <button type="button" @click="execCmd('insertOrderedList')" title="번호 목록" class="w-7 h-7 rounded-md hover:bg-gray-200 text-[11px] text-ink-light">1.</button>
            <span class="w-px h-4 bg-gray-300 mx-1"></span>
            <button type="button" @click="descImageInputRef?.click()" title="이미지 삽입" class="w-7 h-7 rounded-md hover:bg-gray-200 text-ink-light inline-flex items-center justify-center"><AppIcon name="image" :size="14" /></button>
            <input ref="descImageInputRef" type="file" accept="image/*" @change="onInsertDescImage" class="hidden" />
            <button type="button" @click="execCmd('removeFormat')" title="서식 지우기" class="ml-auto px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] text-ink-muted">지우기</button>
          </div>
          <div ref="editorRef" contenteditable="true" @input="syncDescription"
            class="input-soft min-h-[140px] py-2.5 leading-relaxed prose prose-sm max-w-none border border-line rounded-lg px-3"
            data-placeholder="이 상품을 추천하는 이유, 사용 후기 등을 자유롭게 작성해주세요"></div>
        </div>

        <div class="flex gap-3">
          <div class="flex-1">
            <label class="block text-xs text-ink-light mb-1">표시 순서</label>
            <input v-model.number="editing.display_order" type="number" class="w-full border border-line rounded-lg px-3 py-2" />
          </div>
          <label class="flex items-center gap-1.5 text-xs mt-6"><input type="checkbox" v-model="editing.is_featured" /> 추천 상품(Featured)</label>
          <label class="flex items-center gap-1.5 text-xs mt-6"><input type="checkbox" v-model="editing.is_active" /> 활성</label>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="editing=null" class="px-4 py-2 rounded-lg bg-surface text-sm">취소</button>
        <button @click="save" :disabled="saving" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-semibold disabled:opacity-50">{{ saving ? '저장 중...' : '저장' }}</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, inject, nextTick, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import AdminShoppingReviews from './AdminShoppingReviews.vue'
import AdminAssociatesGuide from './AdminAssociatesGuide.vue'

// 관리자 휴대폰 화면이면 카드 + 전체 화면 폼 + 확인 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const mDel = ref(null); const mBusy = ref(false)
async function mToggle(product) {
  try { await axios.put(`/api/admin/amazon-products/${product.id}`, { is_active: !product.is_active }); say(product.is_active ? '비활성으로 바꿨어요' : '활성으로 바꿨어요'); load(page.value); loadStats() }
  catch (e) { say(e.response?.data?.message || '처리하지 못했어요', true) }
}
async function mRemove() {
  if (mBusy.value || !mDel.value) return
  mBusy.value = true
  try { await axios.delete(`/api/admin/amazon-products/${mDel.value.id}`); mDel.value = null; say('삭제했어요'); load(page.value); loadStats() }
  catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
  finally { mBusy.value = false }
}
const activeTab = ref('products')
const tabs = [
  { key: 'products', icon: 'shopping-bag', label: '상품' },
  { key: 'cat',       icon: 'tag',         label: '카테고리' },
  { key: 'member',    icon: 'users',       label: '회원 리뷰' },
  { key: 'guide',     icon: 'book-open',   label: '가입 안내' },
]

const categories = [
  '인기상품', '오늘의 딜', '주방용품', '한국요리 관련 제품', '식품', '생활용품',
  '가전/전자제품', '육아/교육', 'Beauty/K-Beauty', 'Home', '계절상품', '선물', 'Awesome Korean 추천',
]

const stats = ref({})
const categoryStats = ref([])
const associateTag = ref('')

const products = ref([])
const page = ref(1)
const lastPage = ref(1)
const category = ref('')
const search = ref('')
const editing = ref(null)
const isNew = ref(false)
const saving = ref(false)
const newOwnImages = ref([]) // [{file, preview}]
const editorRef = ref(null)
const descImageInputRef = ref(null)
watch(() => isMobile.value && (!!editing.value || !!mDel.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

async function loadStats() {
  try {
    const { data } = await axios.get('/api/admin/amazon-products/stats')
    stats.value = data.data
  } catch (e) { console.warn('stats load failed', e) }
}

function buildCategoryStats() {
  const counts = {}
  categories.forEach(c => counts[c] = 0)
  products.value.forEach(p => { if (p.category && counts[p.category] !== undefined) counts[p.category]++ })
  categoryStats.value = categories.map(c => ({ name: c, count: counts[c] }))
}

async function load(p = 1) {
  page.value = p
  const { data } = await axios.get('/api/admin/amazon-products', { params: { page: p, category: category.value || undefined, search: search.value || undefined } })
  products.value = data.data.data
  lastPage.value = data.data.last_page
  buildCategoryStats()
}

function openCreate() {
  isNew.value = true
  newOwnImages.value = []
  editing.value = {
    input: '', title: '', category: '', price: null,
    amazon_image_urls: [''], keep_own_image_urls: [],
    our_description: '', display_order: 0, is_featured: false, is_active: true,
  }
  nextTick(() => { if (editorRef.value) editorRef.value.innerHTML = '' })
}

function openEdit(product) {
  isNew.value = false
  newOwnImages.value = []
  editing.value = {
    ...product,
    price: product.price ?? null,
    amazon_image_urls: product.amazon_image_urls?.length ? [...product.amazon_image_urls] : [''],
    keep_own_image_urls: product.own_image_urls ? [...product.own_image_urls] : [],
  }
  nextTick(() => { if (editorRef.value) editorRef.value.innerHTML = product.our_description || '' })
}

function execCmd(cmd, value = null) {
  editorRef.value?.focus()
  document.execCommand(cmd, false, value)
  syncDescription()
}
function syncDescription() {
  if (editorRef.value) editing.value.our_description = editorRef.value.innerHTML
}
function onInsertDescImage(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) { alert('이미지는 5MB 이하여야 합니다.'); e.target.value = ''; return }
  const reader = new FileReader()
  reader.onload = () => { execCmd('insertImage', reader.result); e.target.value = '' }
  reader.readAsDataURL(file)
}

function onSelectOwnImages(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if ((editing.value.keep_own_image_urls.length + newOwnImages.value.length) >= 10) break
    newOwnImages.value.push({ file, preview: URL.createObjectURL(file) })
  }
  e.target.value = ''
}

async function save() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('title', editing.value.title)
    fd.append('category', editing.value.category || '')
    if (editing.value.price !== null && editing.value.price !== '') fd.append('price', editing.value.price)
    fd.append('our_description', editing.value.our_description || '')
    fd.append('display_order', editing.value.display_order || 0)
    fd.append('is_featured', editing.value.is_featured ? 'true' : 'false')
    fd.append('is_active', editing.value.is_active ? 'true' : 'false')
    editing.value.amazon_image_urls.filter(u => u).forEach(u => fd.append('amazon_image_urls[]', u))
    newOwnImages.value.forEach(f => fd.append('own_images[]', f.file))

    if (isNew.value) {
      fd.append('input', editing.value.input)
      await axios.post('/api/admin/amazon-products', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    } else {
      fd.append('_method', 'PUT')
      editing.value.keep_own_image_urls.forEach(u => fd.append('keep_own_image_urls[]', u))
      await axios.post(`/api/admin/amazon-products/${editing.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    editing.value = null
    if (isMobile.value) say('저장했어요')
    load(page.value)
    loadStats()
  } catch (e) { if (isMobile.value) say(e.response?.data?.message || '저장 실패', true); else alert(e.response?.data?.message || '저장 실패') }
  saving.value = false
}

async function toggle(product) {
  await axios.put(`/api/admin/amazon-products/${product.id}`, { is_active: !product.is_active })
  load(page.value)
  loadStats()
}

async function remove(product) {
  if (!confirm(`"${product.title}" 상품을 삭제할까요?`)) return
  await axios.delete(`/api/admin/amazon-products/${product.id}`)
  load(page.value)
  loadStats()
}

onMounted(() => {
  load(1)
  loadStats()
})
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
<style scoped>
[contenteditable="true"]:empty::before {
  content: attr(data-placeholder);
  color: #9ca3af;
  pointer-events: none;
}
[contenteditable="true"] img {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 8px 0;
}
[contenteditable="true"] h2 { font-size: 1.15rem; font-weight: 700; margin: 0.5rem 0; }
[contenteditable="true"] h3 { font-size: 1.05rem; font-weight: 600; margin: 0.5rem 0; }
[contenteditable="true"] ul { list-style: disc; padding-left: 1.5rem; }
[contenteditable="true"] ol { list-style: decimal; padding-left: 1.5rem; }
</style>
