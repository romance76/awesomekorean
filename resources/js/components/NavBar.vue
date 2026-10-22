<template>
  <nav ref="navEl" class="site-hdr ev-header bg-white/95 backdrop-blur-sm border-b border-gray-100 sticky top-0 z-50">
    <!-- Row 1: 햄버거(모바일) + Logo + Search + Auth — 크기는 app.css .site-hdr-* (info/_header.blade.php 와 공용) -->
    <div class="site-hdr-row">
      <!-- 햄버거 메뉴 (모바일) -->
      <button @click="mobileMenu = !mobileMenu" class="md:hidden p-2.5 -ml-1 text-ink-light hover:text-amber-500 transition-colors flex-shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="!mobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <!-- Logo -->
      <RouterLink to="/" class="flex items-center flex-shrink-0" aria-label="AwesomeKorean">
        <img :src="siteStore.logoUrl" alt="AwesomeKorean" class="site-hdr-logo" />
      </RouterLink>

      <!-- Search (데스크톱만 — 모바일은 햄버거 메뉴 안에) -->
      <div class="flex-1 mx-2 min-w-0 hidden md:block">
        <form @submit.prevent="goSearch" class="site-hdr-search-form">
          <button type="submit" aria-label="검색" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-faint hover:text-amber-500 transition-colors">
            <AppIcon name="search" :size="16" />
          </button>
          <input v-model="searchQ" type="text" placeholder="궁금한 것을 검색해 보세요 — 영주권, 맛집, 중고차…"
            class="site-hdr-search" />
        </form>
      </div>
      <!-- 모바일: 로고 옆 빈 공간 채우기 -->
      <div class="flex-1 md:hidden"></div>

      <!-- Auth -->
      <div class="flex items-center gap-1.5 flex-shrink-0">
        <template v-if="auth.isLoggedIn">
          <EasyViewButton />
          <div class="relative notif-bell">
            <button @click="toggleNotifs" class="relative p-2 text-ink-light hover:text-amber-500 transition-colors">
              <AppIcon name="bell" :size="20" />
              <span v-if="unreadCount>0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
            </button>
            <!-- 알림 드롭다운 -->
            <Teleport to="body">
            <div v-if="showNotifs" class="notif-panel bg-white rounded-2xl shadow-lift overflow-hidden flex flex-col" :style="notifStyle">
              <div class="px-4 py-3 flex items-center justify-between border-b border-gray-50 flex-shrink-0">
                <span class="text-sm font-bold text-ink">알림</span>
                <span class="flex items-center gap-3">
                  <button v-if="notifList.some(n=>!n.read_at)" @click="markAllRead" class="text-xs text-amber-600 hover:text-amber-700 font-semibold transition-colors">전체 읽음</button>
                  <button v-if="notifList.some(n=>n.read_at)" @click="clearRead" class="text-xs text-ink-muted hover:text-red-600 font-semibold transition-colors">읽은 알림 지우기</button>
                </span>
              </div>
              <div class="overflow-y-auto flex-1 min-h-0" style="-webkit-overflow-scrolling: touch;">
                <div v-if="!notifList.length" class="px-4 py-10 text-center">
                  <div class="icon-chip w-11 h-11 bg-gray-100 text-gray-300 mx-auto mb-2"><AppIcon name="bell" :size="22" :stroke-width="1.5" /></div>
                  <p class="text-sm text-ink-muted">알림이 없습니다</p>
                </div>
                <div v-for="n in notifList" :key="n.id" @click="clickNotif(n)"
                  class="px-4 py-2.5 border-b border-gray-50 last:border-0 cursor-pointer hover:bg-amber-50/40 transition-colors"
                  :class="n.read_at ? '' : 'bg-amber-50/60'">
                  <div class="flex items-start gap-2">
                    <span v-if="!n.read_at" class="w-2 h-2 bg-amber-400 rounded-full flex-shrink-0 mt-1.5"></span>
                    <span v-else class="w-2 h-2 flex-shrink-0"></span>
                    <div class="min-w-0 flex-1" style="overflow-wrap:anywhere;">
                      <div class="text-xs font-semibold text-ink break-words line-clamp-2">{{ n.title }}</div>
                      <div class="text-xs text-ink-muted break-words line-clamp-2">{{ n.content }}</div>
                      <div class="text-[11px] text-ink-faint mt-0.5">{{ formatNotifDate(n.created_at) }}</div>
                    </div>
                    <button @click.stop="removeNotif(n)" class="flex-shrink-0 w-6 h-6 -mr-1 rounded-full text-ink-faint hover:bg-gray-100 hover:text-red-600 flex items-center justify-center text-sm leading-none" aria-label="이 알림 지우기" title="지우기">✕</button>
                  </div>
                </div>
              </div>
              <button @click="goAllNotifs" class="flex-shrink-0 w-full py-2.5 text-xs font-bold text-amber-700 bg-amber-50/70 hover:bg-amber-100/70 border-t border-gray-100">지난 알림 모두 보기 ›</button>
            </div>
            </Teleport>
          </div>
          <div class="relative">
            <button @click="showDropdown=!showDropdown" class="block -my-1 rounded-full transition-transform active:scale-95" aria-label="내 메뉴">
              <UserAvatar :user="auth.user" :size="avatarSize" />
            </button>
            <div v-if="showDropdown" class="absolute right-0 top-14 bg-white rounded-2xl shadow-lift py-2 w-52 z-50" @click="showDropdown=false">
              <div class="px-4 py-2.5 border-b border-gray-50">
                <div class="text-sm font-bold text-ink truncate">{{ auth.user?.name }}</div>
                <div class="text-xs text-ink-faint truncate">{{ auth.user?.email }}</div>
                <div class="flex items-center gap-3 mt-1">
                  <RouterLink to="/points" class="text-xs text-amber-600 font-bold flex items-center gap-1"><AppIcon name="coins" :size="13" />{{ auth.isUnlimited ? '∞' : (auth.user?.points || 0) + 'P' }}</RouterLink>
                  <RouterLink to="/entries" class="text-xs text-violet-600 font-bold flex items-center gap-1"><AppIcon name="ticket" :size="13" />{{ auth.isUnlimited ? '∞' : (auth.user?.entries || 0) }}</RouterLink>
                </div>
              </div>
              <RouterLink to="/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors"><AppIcon name="user" :size="16" class="text-ink-muted" />마이페이지</RouterLink>
              <RouterLink to="/dashboard?tab=messages" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors"><AppIcon name="mail" :size="16" class="text-ink-muted" />쪽지</RouterLink>
              <RouterLink to="/friends" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors"><AppIcon name="heart-handshake" :size="16" class="text-ink-muted" />친구</RouterLink>
              <div v-if="auth.isAdmin">
                <div class="border-t border-gray-50 my-1"></div>
                <RouterLink to="/admin" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors"><AppIcon name="shield" :size="16" />관리자</RouterLink>
              </div>
              <div class="border-t border-gray-50 my-1"></div>
              <button @click="handleLogout" class="w-full flex items-center gap-2.5 text-left px-4 py-2.5 text-sm text-ink-muted hover:bg-gray-50 transition-colors"><AppIcon name="log-out" :size="16" />로그아웃</button>
            </div>
          </div>
        </template>
        <template v-else>
          <RouterLink to="/login" class="text-[13px] font-semibold text-ink-light hover:text-ink hover:bg-surface px-3 py-1.5 rounded-full transition-colors">로그인</RouterLink>
          <RouterLink to="/register" class="text-[13px] text-white font-bold px-4 py-1.5 rounded-full transition-all shadow-btn hover:-translate-y-px" style="background-image:linear-gradient(135deg,#FF8A4D,#FC226B)">시작하기</RouterLink>
        </template>
        <button @click="togglePageLang()" translate="no" class="notranslate text-[11px] font-bold px-2.5 py-1.5 rounded-full text-ink-muted bg-surface hover:bg-line transition-colors" :title="isTranslatedEn ? '한국어로 돌아가기' : 'Translate to English'">
          <span translate="no" class="notranslate">{{ isTranslatedEn ? '한' : 'EN' }}</span>
        </button>
      </div>
    </div>

    <!-- Row 2: 데스크톱 메뉴 -->
    <div class="border-t border-gray-50 hidden md:block">
      <!-- 묶음 메뉴 (관리자 › 메뉴 묶음이 켜져 있을 때) — 묶음에 마우스를 올리면 하위 메뉴가 펼쳐짐 -->
      <div v-if="navGroups" class="site-hdr-menu">
        <div v-for="g in navGroups" :key="g.key" class="site-hdr-navitem">
          <component :is="g.external ? 'a' : 'RouterLink'" v-bind="g.external ? { href: g.path } : { to: g.path }"
            class="site-hdr-tab"
            :class="[isGroupActive(g) ? 'site-hdr-tab-on' : 'site-hdr-tab-off', g.image ? '!h-auto' : '']">
            <img v-if="g.image" :src="g.image" :alt="g.label" class="h-12 w-auto max-w-[220px] object-contain block" />
            <span v-else :style="g.color ? { color: g.color } : null">{{ g.label }}</span><span v-if="g.type !== 'single' && g.items.length" class="text-[9px] opacity-50">▼</span>
          </component>
          <div v-if="g.type !== 'single' && g.items.length"
            class="site-hdr-drop">
            <RouterLink v-if="g.type === 'hub'" :to="g.path" class="site-hdr-drop-item text-ink-light">{{ g.icon }} {{ g.label }} 홈</RouterLink>
            <template v-for="m in g.items" :key="m.key">
              <a v-if="m.external" :href="m.path" class="site-hdr-drop-item text-ink-light">{{ m.label }}</a>
              <RouterLink v-else :to="m.path" class="site-hdr-drop-item"
                :class="isActive(m.path) ? 'text-amber-700' : 'text-ink-light'">{{ m.label }}</RouterLink>
            </template>
          </div>
        </div>
      </div>
      <div v-else class="relative max-w-7xl mx-auto">
       <button v-show="navCanLeft" type="button" @click="navScroll(-1)" aria-label="왼쪽 메뉴 보기"
         class="absolute left-0 top-0 z-10 h-10 md:h-12 w-10 flex items-center justify-start pl-2 text-ink-muted hover:text-ink"
         style="background:linear-gradient(to right,#fff 55%,rgba(255,255,255,0))"><AppIcon name="chevron-left" :size="16" /></button>
       <button v-show="navCanRight" type="button" @click="navScroll(1)" aria-label="오른쪽 메뉴 보기"
         class="absolute right-0 top-0 z-10 h-10 md:h-12 w-10 flex items-center justify-end pr-2 text-ink-muted hover:text-ink"
         style="background:linear-gradient(to left,#fff 55%,rgba(255,255,255,0))"><AppIcon name="chevron-right" :size="16" /></button>
       <div ref="menuRow" @scroll.passive="updateNavArrows" @wheel="onNavWheel" class="px-4 overflow-x-auto scrollbar-hide">
        <div class="flex items-center h-10 md:h-12 w-max mx-auto">
        <template v-for="item in visibleMenus" :key="item.path">
          <a v-if="item.external" :href="item.path"
            class="text-[13px] md:text-[14px] font-semibold px-3 md:px-3.5 py-2.5 md:py-3 border-b-2 whitespace-nowrap transition-colors duration-150 border-transparent text-ink-light hover:text-ink">
            {{ item.label }}
          </a>
          <RouterLink v-else :to="item.path"
            class="text-[13px] md:text-[14px] font-semibold px-3 md:px-3.5 py-2.5 md:py-3 border-b-2 whitespace-nowrap transition-colors duration-150"
            :class="isActive(item.path) ? 'border-amber-400 text-amber-600' : 'border-transparent text-ink-light hover:text-ink'">
            {{ item.label }}
          </RouterLink>
        </template>
        </div>
       </div>
      </div>
    </div>

    <!-- 모바일 메뉴 (슬라이드) -->
    <Teleport to="body">
      <Transition name="menu-fade">
        <div v-if="mobileMenu" class="fixed inset-0 bg-black/40 z-[999]" @click="mobileMenu=false"></div>
      </Transition>
      <Transition name="menu-slide">
        <div v-if="mobileMenu" class="fixed top-0 left-0 bottom-0 bg-white z-[1000] shadow-2xl overflow-y-auto"
             style="width: 78vw; min-width: 250px; max-width: 330px; padding-top: var(--sat); padding-bottom: var(--sab)">
          <!-- 헤더 -->
          <div class="flex items-center justify-between px-4 py-2 border-b border-gray-50">
            <span class="text-sm font-bold text-ink">전체 메뉴</span>
            <button @click="mobileMenu=false" class="p-1 text-ink-faint hover:text-ink transition-colors">
              <AppIcon name="x" :size="20" />
            </button>
          </div>
          <!-- 검색 -->
          <div class="px-3 py-1.5 border-b border-gray-50">
            <form @submit.prevent="goSearch(); mobileMenu=false" class="relative">
              <button type="submit" aria-label="검색" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-faint">
                <AppIcon name="search" :size="16" />
              </button>
              <input v-model="searchQ" type="text" placeholder="검색"
                class="w-full bg-surface border-[1.5px] border-line rounded-full pl-10 pr-4 py-2 text-sm text-ink outline-none placeholder:text-ink-faint focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10 transition-all" />
            </form>
          </div>
          <!-- 메뉴 목록 -->
          <div class="py-1">
            <template v-for="row in drawerRows" :key="row.key">
            <!-- 묶음 한 줄 (큰 글씨 + 묶음 단위 별) — 눌러서 그 묶음의 첫 화면으로 -->
            <div v-if="row.kind === 'group'" class="flex items-center transition-colors"
              :class="row.active ? 'bg-amber-50/70' : 'hover:bg-gray-50'">
              <a v-if="row.external" :href="row.path" class="flex items-center gap-3 flex-1 min-w-0 py-1 px-3.5 text-[15px] font-semibold text-gray-800">
                <span class="icon-chip w-8 h-8" :class="menuChipColor(row.iconKey)"><AppIcon :name="menuIcon(row.iconKey)" :size="18" /></span>
                <img v-if="row.image" :src="row.image" :alt="row.label" class="h-12 -my-1.5 w-auto max-w-[180px] object-contain shrink-0" /><span v-else class="whitespace-nowrap" :style="row.color ? { color: row.color } : null">{{ row.label }}</span>
              </a>
              <RouterLink v-else :to="row.path" @click="mobileMenu=false" class="flex items-center gap-3 flex-1 min-w-0 py-1 px-3.5 text-[15px] font-semibold" :class="row.active ? 'text-amber-700' : 'text-gray-800'">
                <span class="icon-chip w-8 h-8" :class="menuChipColor(row.iconKey)"><AppIcon :name="menuIcon(row.iconKey)" :size="17" /></span>
                <img v-if="row.image" :src="row.image" :alt="row.label" class="h-12 -my-1.5 w-auto max-w-[180px] object-contain shrink-0" /><span v-else class="whitespace-nowrap" :style="row.color ? { color: row.color } : null">{{ row.label }}</span>
              </RouterLink>
              <button v-if="row.hasSub" @click.stop="toggleOpen(row.groupKey)"
                class="px-1.5 py-2 flex-shrink-0 text-ink-faint"
                :aria-expanded="row.open" :aria-label="(row.open ? '접기: ' : '펼치기: ') + row.label">
                <AppIcon name="chevron-down" :size="18" class="transition-transform" :class="row.open ? 'rotate-180' : ''" />
              </button>
              <button @click.stop="navFavStore.toggleGroupFavorite(row.groupKey)"
                class="pl-0.5 pr-2.5 py-1.5 flex-shrink-0 transition-colors"
                :class="navFavStore.isGroupFavorite(row.groupKey) ? 'text-amber-400' : 'text-gray-300'"
                :aria-label="(navFavStore.isGroupFavorite(row.groupKey) ? '하단바에서 빼기: ' : '하단바에 넣기: ') + row.label"
                :title="navFavStore.isGroupFavorite(row.groupKey) ? '하단바에서 제거' : '하단바에 추가'">
                <AppIcon name="star" :size="20" :filled="navFavStore.isGroupFavorite(row.groupKey)" />
              </button>
            </div>
            <!-- 묶음 안의 하위 메뉴 (작은 글씨, 별 없음) -->
            <div v-else-if="row.sub" class="flex items-center transition-colors" :class="isActive(row.item.path) ? 'bg-amber-50/70' : 'hover:bg-gray-50'">
              <a v-if="row.item.external" :href="row.item.path" class="flex items-center gap-2.5 flex-1 min-w-0 py-1 pl-14 pr-4 text-[13px] font-normal text-ink-light">
                <span class="icon-chip w-6 h-6" :class="menuChipColor(row.item.key)"><AppIcon :name="menuIcon(row.item.key)" :size="13" /></span>
                <span>{{ row.label }}</span>
              </a>
              <RouterLink v-else :to="row.item.path" @click="mobileMenu=false"
                class="flex items-center gap-2.5 flex-1 min-w-0 py-1 pl-14 pr-4 text-[13px] font-normal" :class="isActive(row.item.path) ? 'text-amber-700 font-bold' : 'text-ink-light'">
                <span class="icon-chip w-6 h-6" :class="menuChipColor(row.item.key)"><AppIcon :name="menuIcon(row.item.key)" :size="13" /></span>
                <span>{{ row.label }}</span>
              </RouterLink>
            </div>
            <!-- 묶음 메뉴가 꺼져 있을 때: 예전처럼 메뉴 한 줄씩 + 별 -->
            <div v-else
              class="flex items-center transition-colors"
              :class="isActive(row.item.path) ? 'bg-amber-50/70' : 'hover:bg-gray-50'">
              <a v-if="row.item.external" :href="row.item.path"
                class="flex items-center gap-3 flex-1 min-w-0 py-2 text-sm text-ink-light px-4">
                <span class="icon-chip w-8 h-8" :class="menuChipColor(row.item.key)"><AppIcon :name="menuIcon(row.item.key)" :size="16" /></span>
                <span>{{ row.label }}</span>
              </a>
              <RouterLink v-else :to="row.item.path" @click="mobileMenu=false"
                class="flex items-center gap-3 flex-1 min-w-0 py-2 text-sm px-4" :class="isActive(row.item.path) ? 'text-amber-700 font-bold' : 'text-ink-light'">
                <span class="icon-chip w-8 h-8" :class="menuChipColor(row.item.key)"><AppIcon :name="menuIcon(row.item.key)" :size="16" /></span>
                <span>{{ row.label }}</span>
              </RouterLink>
              <button @click.stop="navFavStore.toggleFavorite(row.item.key)"
                class="px-3 py-2.5 flex-shrink-0 transition-colors"
                :class="navFavStore.isFavorite(row.item.key) ? 'text-amber-400' : 'text-gray-200'"
                :title="navFavStore.isFavorite(row.item.key) ? '하단바에서 제거' : '하단바에 추가'">
                <AppIcon name="star" :size="18" :filled="navFavStore.isFavorite(row.item.key)" />
              </button>
            </div>
            </template>
            <!-- MY (묶음 메뉴일 때: 친구는 상단 메뉴 대신 여기) — 마이도 묶음처럼 한 줄 + 별 -->
            <template v-if="navGroups && auth.isLoggedIn">
              <div class="flex items-center transition-colors" :class="isActive('/dashboard') ? 'bg-amber-50/70' : 'hover:bg-gray-50'">
                <RouterLink to="/dashboard" @click="mobileMenu=false" class="flex items-center gap-3 flex-1 min-w-0 py-1 px-3.5 text-[15px] font-semibold" :class="isActive('/dashboard') ? 'text-amber-700' : 'text-gray-800'">
                  <span class="icon-chip w-8 h-8" :class="menuChipColor('dashboard')"><AppIcon :name="menuIcon('dashboard')" :size="17" /></span>
                  <span>마이</span>
                </RouterLink>
                <button @click.stop="toggleOpen('dashboard')"
                  class="px-1.5 py-2 flex-shrink-0 text-ink-faint"
                  :aria-expanded="openGroups.has('dashboard')" :aria-label="openGroups.has('dashboard') ? '접기: 마이' : '펼치기: 마이'">
                  <AppIcon name="chevron-down" :size="18" class="transition-transform" :class="openGroups.has('dashboard') ? 'rotate-180' : ''" />
                </button>
                <button @click.stop="navFavStore.toggleGroupFavorite('dashboard')"
                  class="pl-0.5 pr-2.5 py-1.5 flex-shrink-0 transition-colors"
                  :class="navFavStore.isGroupFavorite('dashboard') ? 'text-amber-400' : 'text-gray-300'"
                  :aria-label="(navFavStore.isGroupFavorite('dashboard') ? '하단바에서 빼기: ' : '하단바에 넣기: ') + '마이'"
                  :title="navFavStore.isGroupFavorite('dashboard') ? '하단바에서 제거' : '하단바에 추가'">
                  <AppIcon name="star" :size="20" :filled="navFavStore.isGroupFavorite('dashboard')" />
                </button>
              </div>
              <template v-if="openGroups.has('dashboard')">
                <RouterLink to="/dashboard" @click="mobileMenu=false" class="flex items-center gap-2.5 pl-14 pr-4 py-1 text-[13px] text-ink-light hover:bg-gray-50"><AppIcon name="user" :size="13" />마이페이지</RouterLink>
                <RouterLink to="/dashboard?tab=messages" @click="mobileMenu=false" class="flex items-center gap-2.5 pl-14 pr-4 py-1 text-[13px] text-ink-light hover:bg-gray-50"><AppIcon name="mail" :size="13" />쪽지</RouterLink>
                <RouterLink to="/friends" @click="mobileMenu=false" class="flex items-center gap-2.5 pl-14 pr-4 py-1 text-[13px] text-ink-light hover:bg-gray-50"><AppIcon name="heart-handshake" :size="13" />친구</RouterLink>
              </template>
            </template>
          </div>
          <!-- 즐겨찾기 안내 -->
          <div class="px-4 py-1.5 border-t border-gray-50 text-[11px] text-ink-faint flex items-center gap-1">
            <AppIcon name="star" :size="12" /> 눌러서 하단 메뉴에 추가/제거
          </div>
        </div>
      </Transition>
    </Teleport>
  </nav>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useLangStore } from '../stores/lang'
import AppIcon from './AppIcon.vue'
import EasyViewButton from './EasyViewButton.vue'
import UserAvatar from './UserAvatar.vue'
import { menuIcon, menuChipColor } from '../utils/menuIcons'
import { resolveNotifRoute } from '../utils/notifRoute'

const auth = useAuthStore()
const langStore = useLangStore()

// Google Translate 위젯 연동: googtrans 쿠키 토글 + reload
const isTranslatedEn = ref(typeof document !== 'undefined' && document.cookie.includes('googtrans=/ko/en'))
function togglePageLang() {
  const host = location.hostname
  // www. 를 뗀 루트 도메인 (예: awesomekorean.com)
  const root = host.replace(/^www\./, '')
  const domains = ['', host, '.' + host, '.' + root]
  // 기존 googtrans 쿠키 모두 제거
  for (const d of domains) {
    document.cookie = 'googtrans=; path=/; max-age=0' + (d ? `; domain=${d}` : '')
  }
  if (!isTranslatedEn.value) {
    // 한국어 → 영어
    document.cookie = 'googtrans=/ko/en; path=/'
    document.cookie = 'googtrans=/ko/en; path=/; domain=.' + root
    langStore.setLang('en')
  } else {
    // 영어 → 한국어
    langStore.setLang('ko')
  }
  location.reload()
}
import { useNavFavoritesStore } from '../stores/navFavorites'
const navFavStore = useNavFavoritesStore()
const router = useRouter()
const route = useRoute()
const searchQ = ref('')
const showDropdown = ref(false)
const mobileMenu = ref(false)
// 휴대폰 ☰ 에서 펼쳐 둔 묶음 (처음엔 모두 접힘 — 화살표를 누르면 하위 메뉴가 보임)
const openGroups = ref(new Set())
function toggleOpen(key) {
  const next = new Set(openGroups.value)
  next.has(key) ? next.delete(key) : next.add(key)
  openGroups.value = next
}
const unreadCount = ref(0)
const showNotifs = ref(false)
// 알림 패널은 body 로 Teleport(상단바의 backdrop-blur 가 fixed 기준점을 바꾸므로) 후 벨 위치 기준으로 배치
const notifStyle = ref({})
function placeNotifs() {
  if (typeof window === 'undefined') return
  const bell = document.querySelector('.notif-bell button')
  const r = bell ? bell.getBoundingClientRect() : { bottom: 56, right: window.innerWidth - 8 }
  const top = Math.max(0, Math.round(r.bottom)) + 6
  const vw = window.innerWidth
  const base = { position: 'fixed', top: top + 'px', zIndex: 70, maxHeight: `calc(100dvh - ${top}px - 84px - env(safe-area-inset-bottom, 0px))` }
  if (vw < 1024) notifStyle.value = { ...base, left: '8px', right: '8px' }
  else notifStyle.value = { ...base, right: Math.max(8, Math.round(vw - r.right)) + 'px', width: '340px' }
}
const notifList = ref([])
import { useSiteStore } from '../stores/site'
const siteStore = useSiteStore()

// ⚠️ Issue #18: 실제 노출 메뉴는 DB site_settings.menu_config 가 오버라이드한다.
// - 관리자 UI:    /admin/site-settings → "메뉴 구성" 탭
// - DB 조회:      SELECT value FROM site_settings WHERE key='menu_config';
// - 이 배열은 DB 값이 없을 때의 폴백일 뿐이며, 운영 상태와 다를 수 있음.
const defaultMenus = [
  { key: 'home', label: '홈', label_en: 'Home', icon: '🏠', path: '/', enabled: true },
  { key: 'community', label: '커뮤니티', label_en: 'Community', icon: '💬', path: '/community', enabled: true },
  { key: 'qa', label: 'Q&A', label_en: 'Q&A', icon: '❓', path: '/qa', enabled: true },
  { key: 'jobs', label: '구인구직', label_en: 'Jobs', icon: '💼', path: '/jobs', enabled: true },
  { key: 'market', label: '중고장터', label_en: 'Market', icon: '🛒', path: '/market', enabled: true },
  { key: 'directory', label: '업소록', label_en: 'Directory', icon: '📋', path: '/directory', enabled: true },
  { key: 'realestate', label: '부동산', label_en: 'Real Estate', icon: '🏠', path: '/realestate', enabled: true },
  { key: 'events', label: '이벤트', label_en: 'Events', icon: '🎉', path: '/events', enabled: true },
  { key: 'flyers', label: 'NEW', label_en: 'NEW', icon: '🆕', path: '/new', enabled: true },
  { key: 'news', label: '뉴스', label_en: 'News', icon: '📰', path: '/news', enabled: true },
  // 정보는 서버사이드 Blade 페이지(검색엔진용)지만, 사이트 안 이동은 InfoFrame 라우트로 — 음악이 끊기지 않음
  { key: 'info', label: '정보', label_en: 'Info', icon: '📘', path: '/info', enabled: true },
  { key: 'recipes', label: '레시피', label_en: 'Recipes', icon: '🍳', path: '/recipes', enabled: true },
  { key: 'clubs', label: '동호회', label_en: 'Clubs', icon: '👥', path: '/clubs', enabled: true },
  { key: 'games', label: '게임', label_en: 'Games', icon: '🎮', path: '/games', enabled: true },
  { key: 'shorts', label: '숏츠', label_en: 'Shorts', icon: '📱', path: '/shorts', enabled: true },
  { key: 'music', label: '음악듣기', label_en: 'Music', icon: '🎵', path: '/music', enabled: true },
  { key: 'groupbuy', label: '공동구매', label_en: 'Group Buy', icon: '🤝', path: '/groupbuy', enabled: true },
  { key: 'chat', label: '채팅', label_en: 'Chat', icon: '💭', path: '/chat', enabled: true, login_required: true },
  { key: 'friends', label: '친구', label_en: 'Friends', icon: '👫', path: '/friends', enabled: true, login_required: true },
  { key: 'elder', label: '안심서비스', label_en: 'Elder Care', icon: '💙', path: '/elder', enabled: true },
  { key: 'comms', label: '안심 커뮤', label_en: 'Comms', icon: '📞', path: '/comms', enabled: true },
  { key: 'shopping', label: '쇼핑', label_en: 'Shopping', icon: '🛍️', path: '/shopping', enabled: true },
]

const visibleMenus = computed(() => {
  const ko = langStore.locale === 'ko'
  const mc = siteStore.menuConfig
  if (mc && Array.isArray(mc)) {
    const defaultMap = {}
    defaultMenus.forEach(m => { defaultMap[m.key] = m })
    return mc
      .filter(m => m.enabled !== false)
      .filter(m => !m.admin_only || auth.isAdmin)
      .filter(m => !m.login_required || auth.isLoggedIn)
      .map(m => {
        const def = defaultMap[m.key] || {}
        return {
          ...def, ...m,
          path: m.path || def.path || `/${m.key}`,
          label: ko ? (m.label || def.label || m.key) : (m.label_en || def.label_en || m.label || def.label || m.key),
          icon: m.icon || def.icon || '📄',
        }
      })
      .filter(m => m.path)
  }
  // menuConfig가 아직 API에서 로드되기 전 — 이 하드코딩 defaultMenus는 운영
  // 설정(관리자 페이지에서 끈 메뉴 등)과 다를 수 있으므로, 여기서 바로 보여주면
  // 로드 완료 후 사라지는 깜빡임이 생긴다(예: /info 같은 외부 페이지를 거쳐
  // 왔다가 메뉴를 누르면 안심서비스가 잠깐 보였다 사라지는 현상).
  // 로드가 끝날 때까지는 비워 둬서 틀린 목록이 잠깐 보이는 걸 막는다.
  if (!siteStore.loaded) return []
  return defaultMenus
    .filter(m => m.enabled !== false)
    .filter(m => !m.login_required || auth.isLoggedIn)
    .map(m => ({ ...m, label: ko ? m.label : (m.label_en || m.label) }))
})

// menuConfig는 siteStore.load()에서 자동으로 로드됨

async function loadUnread() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/notifications', { params: { limit: 8 } })   // 종 모양에는 최근 8개만 — 예전 알림은 '지난 알림 모두 보기'
    unreadCount.value = data.unread_count || 0
    notifList.value = data.data?.data || data.data || []
  } catch {}
}

function formatNotifDate(dt) {
  if (!dt) return ''
  const d = new Date(dt), now = new Date()
  const m = d.getMonth() + 1, day = d.getDate()
  const hh = String(d.getHours()).padStart(2, '0'), mm = String(d.getMinutes()).padStart(2, '0')
  if (d.getFullYear() === now.getFullYear() && m === now.getMonth() + 1 && day === now.getDate()) return `오늘 ${hh}:${mm}`
  if (d.getFullYear() === now.getFullYear()) return `${m}/${day} ${hh}:${mm}`
  return `${d.getFullYear()}.${m}.${day} ${hh}:${mm}`
}

async function toggleNotifs() {
  showNotifs.value = !showNotifs.value
  if (showNotifs.value) { placeNotifs(); await loadUnread() }
}

async function removeNotif(n) {
  try {
    const { data } = await axios.delete(`/api/notifications/${n.id}`)
    notifList.value = notifList.value.filter(x => x.id !== n.id)
    unreadCount.value = data.unread_count ?? unreadCount.value
  } catch {}
}
async function clearRead() {
  try {
    const { data } = await axios.post('/api/notifications/clear', { scope: 'read' })
    notifList.value = notifList.value.filter(n => !n.read_at)
    unreadCount.value = data.unread_count ?? unreadCount.value
    loadUnread()
  } catch {}
}
function goAllNotifs() { showNotifs.value = false; router.push('/notifications') }

async function markAllRead() {
  try {
    await axios.post('/api/notifications/read')
    notifList.value.forEach(n => n.read_at = new Date().toISOString())
    unreadCount.value = 0
  } catch {}
}

function clickNotif(n) {
  // 읽음 처리
  if (!n.read_at) {
    n.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
    axios.post(`/api/notifications/${n.id}/read`).catch(() => {})
  }
  showNotifs.value = false
  // 새 쪽지 알림: 페이지를 옮기지 않고 그 자리에서 쪽지 대화 팝업을 연다
  if (n.type === 'message' && n.data?.sender_id && window.openMessageThread) { window.openMessageThread(n.data.sender_id); return }
  const dest = resolveNotifRoute(n)
  if (dest) router.push(dest)
}

// 알림 타입별 이동 경로는 utils/notifRoute.js (관리자 알림은 data.url 의 /admin/... 처리 화면으로 바로 이동)

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

if (typeof window !== 'undefined') {
  window.addEventListener('click', (e) => {
    if (showDropdown.value && !e.target.closest('.relative')) showDropdown.value = false
    if (showNotifs.value && !e.target.closest('.notif-bell') && !e.target.closest('.notif-panel')) showNotifs.value = false
  })
  window.addEventListener('resize', () => { if (showNotifs.value) placeNotifs() })
}

function isActive(path) {
  if (path === '/') return route.path === '/'
  // '/new' 가 '/news' 까지 켜 버리지 않게: 경로 마디(/) 단위로만 일치
  return route.path === path || route.path.startsWith(path.replace(/\/$/, '') + '/')
}

// 상단 메뉴 묶음 (관리자 › 사이트 설정 › 메뉴 묶음 — 켜져 있을 때만). 메뉴 하나하나의 이름·경로·숨김 규칙은 visibleMenus 그대로 따름
const navGroups = computed(() => {
  const gs = siteStore.menuGroups
  if (!gs) return null
  const byKey = Object.fromEntries(visibleMenus.value.map(m => [m.key, m]))
  const ko = langStore.locale === 'ko'
  const out = []
  for (const g of gs) {
    if (g.hidden) continue
    // item_labels: 묶음 안에서만 쓰는 이름 (예: 커뮤니티 묶음 안의 '게시판')
    const items = (g.items || []).map(k => byKey[k] && (g.item_labels?.[k] && ko ? { ...byKey[k], label: g.item_labels[k] } : byKey[k])).filter(Boolean)
    if (g.type === 'hub' ? !g.path : !items.length) continue
    const first = items[0]
    out.push({
      key: g.key, type: g.type, icon: g.icon, items, color: g.color || '', image: g.image || '',
      label: ko ? g.label : (g.label_en || g.label),
      path: g.type === 'hub' ? g.path : first.path,
      external: g.type === 'hub' ? false : !!first.external,
    })
  }
  return out.length ? out : null
})
// 휴대폰 ☰ 목록: 묶음 메뉴면 [묶음 제목 → 하위 메뉴], 아니면 예전처럼 메뉴 한 줄씩
const drawerRows = computed(() => {
  if (!navGroups.value) return visibleMenus.value.map(m => ({ kind: 'item', key: 'm:' + m.key + m.path, item: m, label: m.label }))
  const rows = []
  for (const g of navGroups.value) {
    const first = g.items[0]
    const hasSub = g.type === 'group'
    const open = openGroups.value.has(g.key)
    rows.push({
      kind: 'group', key: 'h:' + g.key, groupKey: g.key, label: g.label, path: g.path, external: g.external, color: g.color, image: g.image,
      iconKey: g.type === 'hub' ? 'money' : first.key, hasSub, open,
      active: (g.type === 'hub' && isActive(g.path)) || g.items.some(m => !m.external && isActive(m.path)),
    })
    if (hasSub && open) for (const m of g.items) rows.push({ kind: 'item', sub: true, key: g.key + ':' + m.key, item: m, label: m.label })
  }
  return rows
})
const isGroupActive = (g) => (g.type === 'hub' && isActive(g.path)) || g.items.some(m => !m.external && isActive(m.path))

function goSearch() {
  if (searchQ.value.trim()) {
    router.push({ path: '/search', query: { q: searchQ.value.trim() } })
    searchQ.value = ''
  }
}

let pollInterval = null
let echoChannel = null

onMounted(() => {
  loadUnread()
  siteStore.load() // settings + menuConfig 한번에
  if (auth.isLoggedIn) {
    // WebSocket 실시간 알림 수신
    if (window.Echo && auth.user?.id) {
      echoChannel = window.Echo.private(`user.${auth.user.id}`)
      echoChannel.listen('.notification.new', (e) => {
        unreadCount.value = e.unread_count || unreadCount.value + 1
        loadUnread() // 목록도 새로고침
      })
    }
    // WebSocket 실패 시 fallback: 120초 폴링
    pollInterval = setInterval(() => {
      loadUnread()
      axios.post('/api/heartbeat').catch(() => {})
    }, 120000)
  }
})

function onVisibility() {
  if (!document.hidden && auth.isLoggedIn) loadUnread()
}

onMounted(() => { document.addEventListener('visibilitychange', onVisibility) })

// 데스크톱 메뉴 줄: 창이 좁아 메뉴가 넘칠 때 양끝 메뉴도 눌러 볼 수 있도록 화살표/휠 스크롤 제공
const menuRow = ref(null)
const navCanLeft = ref(false)
const navCanRight = ref(false)
function updateNavArrows() {
  const el = menuRow.value
  if (!el) return
  navCanLeft.value = el.scrollLeft > 2
  navCanRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 2
}
function navScroll(dir) { menuRow.value?.scrollBy({ left: dir * 240, behavior: 'smooth' }) }
function onNavWheel(e) {
  const el = menuRow.value
  if (el && el.scrollWidth > el.clientWidth && Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
    el.scrollLeft += e.deltaY
    e.preventDefault()
  }
}
// 현재 페이지 메뉴가 화면 밖에 있으면 보이는 위치로 스크롤
function revealActiveMenu() {
  const el = menuRow.value
  const active = el?.querySelector('.border-amber-400')
  if (!el || !active || el.scrollWidth <= el.clientWidth) return
  el.scrollLeft = active.offsetLeft - (el.clientWidth - active.offsetWidth) / 2
}
onMounted(() => { nextTick(() => { revealActiveMenu(); updateNavArrows() }); window.addEventListener('resize', updateNavArrows) })
watch(() => route.path, () => nextTick(() => { revealActiveMenu(); updateNavArrows() }))
onUnmounted(() => window.removeEventListener('resize', updateNavArrows))
watch(() => visibleMenus.value, () => nextTick(updateNavArrows), { deep: true })

// 헤더의 실제 높이를 CSS 변수(--hdr-h)로 공유 — 스크롤해도 따라오는 사이드바·머리글이 헤더 밑에 가려지지 않게 (app.css)
const navEl = ref(null)
let navObserver = null
function syncHeaderHeight() {
  const h = navEl.value?.offsetHeight
  if (h) document.documentElement.style.setProperty('--hdr-h', h + 'px')
}
onMounted(() => {
  syncHeaderHeight()
  if (typeof ResizeObserver !== 'undefined' && navEl.value) {
    navObserver = new ResizeObserver(syncHeaderHeight)
    navObserver.observe(navEl.value)
  }
})
onUnmounted(() => navObserver?.disconnect())

// 좁은 폰(≤ 430px)에서는 프로필 사진을 줄여 헤더가 화면보다 넓어지지 않게 (넓어지면 페이지가 옆으로 밀려 결제 창 등이 잘림)
const avatarSize = ref(58)
const fitHeader = () => { avatarSize.value = window.innerWidth <= 430 ? 46 : 58 }
fitHeader()
onMounted(() => window.addEventListener('resize', fitHeader))
onUnmounted(() => window.removeEventListener('resize', fitHeader))

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
  if (echoChannel) echoChannel.stopListening('.notification.new')
  document.removeEventListener('visibilitychange', onVisibility)
})
</script>

<style scoped>
.menu-fade-enter-active, .menu-fade-leave-active { transition: opacity 0.2s; }
.menu-fade-enter-from, .menu-fade-leave-to { opacity: 0; }
.menu-slide-enter-active, .menu-slide-leave-active { transition: transform 0.25s ease; }
.menu-slide-enter-from, .menu-slide-leave-to { transform: translateX(-100%); }
</style>
