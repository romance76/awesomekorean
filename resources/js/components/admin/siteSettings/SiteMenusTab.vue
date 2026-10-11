<template>
  <div>
  <div class="flex items-center justify-between mb-5">
    <div>
      <h2 class="text-base font-semibold text-ink">네비게이션 메뉴 관리</h2>
      <p class="text-xs text-ink-faint mt-0.5">순서, 표시여부, 로그인/관리자 권한을 한 곳에서 설정하세요</p>
    </div>
    <button @click="saveMenus" :disabled="menuSaving"
      class="btn-primary">
      {{ menuSaving ? '저장 중...' : '저장' }}
    </button>
  </div>

  <!-- 통계 -->
  <div class="grid grid-cols-3 gap-4 mb-6 ss-keep">
    <div class="bg-blue-50 rounded-xl p-4 text-center">
      <div class="text-2xl font-black text-blue-600">{{ menuList.filter(m => m.enabled).length }}</div>
      <div class="text-xs text-blue-400">활성 메뉴</div>
    </div>
    <div class="bg-gray-50 rounded-xl p-4 text-center">
      <div class="text-2xl font-black text-ink-muted">{{ menuList.filter(m => !m.enabled).length }}</div>
      <div class="text-xs text-ink-faint">비활성 메뉴</div>
    </div>
    <div class="bg-red-50 rounded-xl p-4 text-center">
      <div class="text-2xl font-black text-red-500">{{ menuList.filter(m => m.login_required).length }}</div>
      <div class="text-xs text-red-400">로그인 필요</div>
    </div>
  </div>

  <!-- 휴대폰: 메뉴 한 개를 카드 한 장으로 (PC 의 가로 표는 폰에서 깨져서) -->
  <div v-if="isMobile" class="space-y-2.5">
    <div v-for="(item, idx) in menuList" :key="item.key" class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="flex items-center gap-2">
        <span class="icon-chip w-9 h-9 flex-shrink-0" :class="menuChipColor(item.key)"><AppIcon :name="menuIcon(item.key)" :size="17" /></span>
        <input v-model="item.label" type="text" maxlength="20" placeholder="메뉴 이름" :aria-label="'메뉴 이름 ' + item.key" class="flex-1 min-w-0 rounded-xl border border-gray-200 px-3 font-semibold text-ink" />
        <span class="shrink-0 text-[11px] text-ink-faint font-mono">{{ idx + 1 }}</span>
      </div>
      <div class="flex items-center gap-2 mt-2">
        <button @click="moveMenu(idx, -1)" :disabled="idx === 0" class="w-11 h-11 rounded-xl border border-gray-200 flex items-center justify-center disabled:opacity-25" aria-label="위로"><AppIcon name="chevron-up" :size="18" /></button>
        <button @click="moveMenu(idx, 1)" :disabled="idx === menuList.length - 1" class="w-11 h-11 rounded-xl border border-gray-200 flex items-center justify-center disabled:opacity-25" aria-label="아래로"><AppIcon name="chevron-down" :size="18" /></button>
        <span class="text-[11px] text-ink-faint font-mono truncate">{{ item.key }}</span>
        <label class="ml-auto flex items-center gap-2 text-[14px] font-bold text-ink min-h-[44px]">표시
          <button type="button" role="switch" :aria-checked="!!item.enabled" @click="item.enabled = !item.enabled" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="item.enabled ? 'bg-emerald-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="item.enabled ? 'left-[26px]' : 'left-[4px]'"></span></button>
        </label>
      </div>
      <div class="grid grid-cols-2 gap-x-3 gap-y-1 mt-1 ss-keep">
        <label class="flex items-center gap-2 min-h-[44px] text-[14px] text-ink"><input type="checkbox" v-model="item.login_required" /> 로그인 필요</label>
        <label class="flex items-center gap-2 min-h-[44px] text-[14px] text-ink"><input type="checkbox" v-model="item.admin_only" /> 관리자만</label>
        <label class="flex items-center gap-2 text-[14px] text-ink"><span class="shrink-0">기본 보기</span>
          <select v-model="item.defaultView" class="flex-1 min-w-0 rounded-xl border border-gray-200 px-2 bg-white"><option value="list">☰ 목록</option><option value="card">⊞ 카드</option></select></label>
        <div class="flex items-center gap-2"><label class="flex items-center gap-2 min-h-[44px] text-[14px] text-ink"><input type="checkbox" v-model="item.ads_enabled" /> 광고</label>
          <select v-if="item.ads_enabled" v-model="item.ads_type" class="flex-1 min-w-0 rounded-xl border border-gray-200 px-2 bg-white"><option value="banner">일반</option><option value="adsense">애드센스</option></select></div>
      </div>
    </div>
  </div>

  <!-- 헤더 행 -->
  <div v-if="!isMobile" class="flex items-center gap-3 px-4 py-2 text-xs text-ink-faint font-medium border-b border-gray-100 mb-2">
    <span class="w-6 text-center">#</span>
    <span class="w-7"></span>
    <span class="flex-1">메뉴</span>
    <span class="w-14 hidden sm:inline"></span>
    <span class="w-16 text-center">순서</span>
    <span class="w-16 text-center">로그인</span>
    <span class="w-16 text-center">관리자</span>
    <span class="w-14 text-center">뷰</span>
    <span class="w-28 text-center">광고</span>
    <span class="w-12 text-center">표시</span>
  </div>

  <div v-if="!isMobile" class="space-y-2">
    <div v-for="(item, idx) in menuList" :key="item.key"
      class="flex items-center gap-3 card px-4 py-3 hover:shadow-lift transition-all">
      <span class="w-6 text-center text-xs font-bold text-ink-faint">{{ idx + 1 }}</span>
      <span class="icon-chip w-7 h-7 flex-shrink-0" :class="menuChipColor(item.key)"><AppIcon :name="menuIcon(item.key)" :size="15" /></span>
      <input v-model="item.label" type="text" maxlength="20" placeholder="메뉴 이름"
        class="flex-1 min-w-0 text-sm font-semibold text-ink bg-transparent border border-transparent hover:border-gray-200 focus:border-amber-400 focus:bg-white rounded-lg px-2 py-1 outline-none transition-colors" />
      <span class="text-xs text-ink-faint font-mono bg-gray-50 px-2 py-0.5 rounded hidden sm:inline">{{ item.key }}</span>
      <!-- Up/Down -->
      <div class="flex gap-1">
        <button @click="moveMenu(idx, -1)" :disabled="idx === 0"
          class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-ink-muted hover:bg-amber-50 hover:text-amber-600 disabled:opacity-20 transition-colors"><AppIcon name="chevron-up" :size="14" /></button>
        <button @click="moveMenu(idx, 1)" :disabled="idx === menuList.length - 1"
          class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-ink-muted hover:bg-amber-50 hover:text-amber-600 disabled:opacity-20 transition-colors"><AppIcon name="chevron-down" :size="14" /></button>
      </div>
      <!-- Login required -->
      <label class="flex items-center gap-1 text-xs text-ink-muted cursor-pointer" title="로그인 필요">
        <input type="checkbox" v-model="item.login_required" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
        <span class="hidden lg:inline">로그인</span>
      </label>
      <!-- Admin only -->
      <label class="flex items-center gap-1 text-xs text-ink-muted cursor-pointer" title="관리자만">
        <input type="checkbox" v-model="item.admin_only" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
        <span class="hidden lg:inline">관리자</span>
      </label>
      <!-- 기본 뷰 -->
      <select v-model="item.defaultView" class="text-[11px] border border-gray-200 rounded-lg px-1 py-0.5 text-ink-muted w-14" title="기본 보기">
        <option value="list">☰</option>
        <option value="card">⊞</option>
      </select>
      <!-- 광고 — 이 페이지에 광고를 띄울지, 띄운다면 일반 배너 광고인지 애드센스인지 -->
      <div class="w-28 flex items-center justify-center gap-1">
        <label class="flex items-center gap-1 text-xs text-ink-muted cursor-pointer" title="이 페이지에 광고 표시">
          <input type="checkbox" v-model="item.ads_enabled" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
        </label>
        <select v-if="item.ads_enabled" v-model="item.ads_type" class="text-[11px] border border-gray-200 rounded-lg px-1 py-0.5 text-ink-muted" title="광고 종류">
          <option value="banner">일반 광고</option>
          <option value="adsense">애드센스</option>
        </select>
      </div>
      <!-- Enable toggle -->
      <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" v-model="item.enabled" class="sr-only peer" />
        <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-400"></div>
      </label>
    </div>
  </div>

  <div class="mt-4 flex justify-between items-center">
    <button @click="resetMenuOrder"
      class="btn-secondary text-xs">
      <AppIcon name="refresh" :size="13" />기본 순서로 리셋
    </button>
    <button @click="saveMenus" :disabled="menuSaving" class="btn-primary">
      {{ menuSaving ? '저장 중...' : '메뉴 설정 저장' }}
    </button>
  </div>
  </div>
</template>

<script setup>
import AppIcon from '../../AppIcon.vue'
import { menuIcon, menuChipColor } from '../../../utils/menuIcons'

// 메뉴 관리 탭 — 목록(menuList)과 이동·초기화·저장은 SiteSettings.vue 가 맡고, 여기서는 화면만 그린다.
defineProps({
  menuList: { type: Array, required: true },
  menuSaving: { type: Boolean, default: false },
  isMobile: { type: Boolean, default: false },
})
const emit = defineEmits(['move', 'reset', 'save'])
const moveMenu = (idx, dir) => emit('move', idx, dir)
const resetMenuOrder = () => emit('reset')
const saveMenus = () => emit('save')
</script>
