<template>
  <!-- "웹앱으로 보시겠어요?" 안내: 폰 브라우저로 들어온 사람에게만, 아직 홈 화면 앱(standalone)이 아닐 때 한 번 물어봄.
       홈 화면에 추가하면 주소창 없이 열려서 화면이 더 크게 보임.
       - Android/Chrome 등: beforeinstallprompt 로 바로 설치 창을 띄움
       - iOS Safari: 설치 이벤트가 없어서 "공유 → 홈 화면에 추가" 안내 시트를 보여줌
       - 카카오톡/인스타 등 앱 안 브라우저: 설치가 불가능하니 Safari/Chrome 으로 열라고 안내 -->
  <Transition name="iap">
    <div v-if="visible" class="fixed left-3 right-3 bottom-[72px] z-[45] md:hidden rounded-2xl bg-white shadow-lg border border-gray-200 px-3.5 py-3">
      <div class="flex items-start gap-2.5">
        <div class="text-xl leading-none mt-0.5">📱</div>
        <div class="min-w-0 flex-1">
          <div class="text-[13px] font-bold text-ink leading-snug">웹앱으로 설치하면 더 크고 빠르게 볼 수 있어요</div>
          <div class="text-xs text-ink-muted mt-0.5">주소창 없이 앱처럼 열려요. 홈 화면에 바로가기가 만들어져요.</div>
          <div class="flex flex-wrap items-center gap-1.5 mt-2">
            <button @click="onInstall" class="rounded-full text-white text-xs font-bold px-3.5 py-1.5" style="background:#FC226B">웹앱으로 보기</button>
            <button @click="later" class="rounded-full border border-gray-200 text-ink-muted text-xs font-bold px-3 py-1.5">나중에</button>
            <button @click="never" class="text-[11px] text-ink-faint underline px-1 py-1.5">다시 보지 않기</button>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- 안내 시트 (iOS / 앱 안 브라우저) -->
  <Transition name="iap">
    <div v-if="sheet" class="fixed inset-0 z-[9000] flex items-end justify-center bg-black/50" @click.self="sheet = false">
      <div class="w-full max-w-md bg-white rounded-t-3xl px-5 pt-5 pb-8" style="padding-bottom:calc(2rem + env(safe-area-inset-bottom, 0px))">
        <div class="flex items-center justify-between mb-3">
          <div class="font-black text-ink text-base">{{ inApp ? '다른 브라우저로 열어 주세요' : '홈 화면에 추가하는 방법' }}</div>
          <button @click="sheet = false" class="w-8 h-8 grid place-items-center text-ink-muted" aria-label="닫기">✕</button>
        </div>

        <div v-if="inApp" class="text-sm text-ink-light leading-relaxed">
          지금 보고 계신 화면은 앱 안의 브라우저라 설치할 수 없어요.<br>
          오른쪽 위(또는 아래) <b>메뉴(⋯)</b> 에서 <b>'{{ isIOS ? 'Safari' : 'Chrome' }}(으)로 열기'</b> 를 눌러 다시 들어와 주세요.
          <div class="mt-2 text-xs text-ink-muted">{{ isIOS ? 'Safari' : 'Chrome' }}에서 열면 "웹앱으로 보기" 안내가 다시 나와요.</div>
        </div>

        <ol v-else class="space-y-4">
          <li class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-full text-white grid place-items-center text-sm font-bold flex-shrink-0" style="background:#FC226B">1</span>
            <div class="flex-1 text-sm text-ink-light">화면 <b>아래 가운데</b>의 <b>공유 버튼</b>을 눌러요</div>
            <svg width="44" height="44" viewBox="0 0 44 44" fill="none" aria-hidden="true">
              <rect x="6" y="6" width="32" height="32" rx="8" fill="#F3F4F6"/>
              <path d="M22 28V12M22 12l-5 5M22 12l5 5" stroke="#007AFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M16 22h-1.5a1.5 1.5 0 0 0-1.5 1.5v8a1.5 1.5 0 0 0 1.5 1.5h15a1.5 1.5 0 0 0 1.5-1.5v-8a1.5 1.5 0 0 0-1.5-1.5H28" stroke="#007AFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </li>
          <li class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-full text-white grid place-items-center text-sm font-bold flex-shrink-0" style="background:#FC226B">2</span>
            <div class="flex-1 text-sm text-ink-light">메뉴를 올려서 <b>'홈 화면에 추가'</b> 를 선택해요</div>
            <svg width="44" height="44" viewBox="0 0 44 44" fill="none" aria-hidden="true">
              <rect x="6" y="8" width="32" height="28" rx="6" fill="#F3F4F6"/>
              <rect x="11" y="22" width="22" height="9" rx="3" fill="#fff" stroke="#D1D5DB"/>
              <path d="M15 26.5h3M16.5 25v3" stroke="#111" stroke-width="1.6" stroke-linecap="round"/>
              <path d="M22 26.5h8" stroke="#9CA3AF" stroke-width="1.6" stroke-linecap="round"/>
              <path d="M22 18v-5M22 13l-2.5 2.5M22 13l2.5 2.5" stroke="#FC226B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" transform="rotate(180 22 15.5)"/>
            </svg>
          </li>
          <li class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-full text-white grid place-items-center text-sm font-bold flex-shrink-0" style="background:#FC226B">3</span>
            <div class="flex-1 text-sm text-ink-light">오른쪽 위 <b>'추가'</b> 를 누르면 끝! 홈 화면의 아이콘으로 열어 보세요</div>
            <div class="rounded-full text-white text-xs font-bold px-3 py-1.5" style="background:#FC226B">추가</div>
          </li>
        </ol>

        <button @click="sheet = false" class="mt-6 w-full rounded-full text-white font-bold py-3 text-sm" style="background:#FC226B">확인</button>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { checkinVisible } from '../utils/floatPrompts'

const route = useRoute()
const router = useRouter()

const SNOOZE_KEY = 'ak_install_snooze_until'
const PV_KEY = 'ak_install_pv'
const DAY = 86400000

const eligible = ref(false)     // 모바일 브라우저 + standalone 아님 + 미설치/미거절
const timeReady = ref(false)    // 20초 경과 또는 2번째 페이지
const sheet = ref(false)
const isIOS = ref(false)
const inApp = ref(false)
let deferred = null
const hasDeferred = ref(false)
let timer = null
let removeGuard = null

const blockedPath = computed(() => {
  const p = route.path || ''
  return p === '/login' || p === '/register' || p.startsWith('/admin') || p.startsWith('/games') || p.startsWith('/shorts')
})
const visible = computed(() => eligible.value && timeReady.value && !blockedPath.value && !checkinVisible.value && !sheet.value)

function isStandalone() {
  try {
    if (window.matchMedia('(display-mode: standalone)').matches || window.matchMedia('(display-mode: fullscreen)').matches) return true
  } catch {}
  return window.navigator.standalone === true
}
function readSnooze() { try { return Number(localStorage.getItem(SNOOZE_KEY)) || 0 } catch { return 0 } }
function setSnooze(days) { try { localStorage.setItem(SNOOZE_KEY, String(Date.now() + days * DAY)) } catch {} }

function detect() {
  const ua = navigator.userAgent || ''
  const touchMac = /Macintosh/.test(ua) && navigator.maxTouchPoints > 1   // iPadOS 가 데스크탑 UA 로 오는 경우
  isIOS.value = /iPhone|iPad|iPod/.test(ua) || touchMac
  inApp.value = /KAKAOTALK|FBAN|FBAV|Instagram|Line\/|NAVER\(inapp|DaumApps|; wv\)/i.test(ua)
  const mobileUA = /Android|iPhone|iPad|iPod|Mobile/i.test(ua) || touchMac
  const isTouch = (navigator.maxTouchPoints || 0) > 0
  return mobileUA && isTouch && window.innerWidth < 1024
}

function onBeforeInstall(e) { e.preventDefault(); deferred = e; hasDeferred.value = true }
function onInstalled() {
  deferred = null; hasDeferred.value = false; eligible.value = false
  setSnooze(3650)
}

async function onInstall() {
  if (deferred) {
    const d = deferred
    deferred = null; hasDeferred.value = false
    try {
      d.prompt()
      const choice = await d.userChoice
      if (choice?.outcome === 'accepted') { eligible.value = false; setSnooze(3650) }
      else { setSnooze(7); eligible.value = false }
    } catch { setSnooze(7); eligible.value = false }
    return
  }
  // 설치 이벤트가 없는 환경(iOS Safari, 앱 안 브라우저 등) → 안내 시트
  sheet.value = true
}
function later() { setSnooze(7); eligible.value = false }
function never() { setSnooze(90); eligible.value = false }

function bumpPageView() {
  try {
    const n = (Number(sessionStorage.getItem(PV_KEY)) || 0) + 1
    sessionStorage.setItem(PV_KEY, String(n))
    if (n >= 2) timeReady.value = true
  } catch {}
}

onMounted(() => {
  if (typeof window === 'undefined') return
  if (isStandalone()) return
  if (!detect()) return
  if (Date.now() < readSnooze()) return
  eligible.value = true

  window.addEventListener('beforeinstallprompt', onBeforeInstall)
  window.addEventListener('appinstalled', onInstalled)

  // Chrome 설치 가능 조건을 위해 서비스워커 등록 (푸시 알림이 쓰는 것과 같은 /sw.js, 캐시는 쓰지 않음)
  try { navigator.serviceWorker?.register('/sw.js', { scope: '/' }).catch(() => {}) } catch {}

  // 처음 진입 1회 + 이후 페이지 이동마다 카운트 (2번째 페이지부터 표시)
  bumpPageView()
  removeGuard = router.afterEach(() => bumpPageView())
  timer = setTimeout(() => { timeReady.value = true }, 20000)
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  if (removeGuard) removeGuard()
  window.removeEventListener('beforeinstallprompt', onBeforeInstall)
  window.removeEventListener('appinstalled', onInstalled)
})
</script>

<style scoped>
.iap-enter-active, .iap-leave-active { transition: opacity .25s, transform .25s; }
.iap-enter-from, .iap-leave-to { opacity: 0; transform: translateY(10px); }
</style>
