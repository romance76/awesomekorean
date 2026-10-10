import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * "큰 글씨로 보기" — 글씨 크기·진하기를 회원이 고르면 기억한다.
 *
 * - 그 폰(브라우저)에 저장: localStorage 'ak_easy_view' = { level, hc, ts }  → 로그아웃/재접속해도 유지, 로그인 전 화면에도 적용
 * - 회원 계정에 저장: users.easy_view_prefs ("크기|진하게|시각")  → 다른 기기에서 로그인해도 같은 크기
 *   두 값이 다르면 "가장 최근에 바꾼 쪽"을 따른다.
 * - 켜고 끄는 스위치: 사이트 설정 easy_view_enabled (관리자 > 시스템). 꺼 두면 아이콘이 사라지고 화면도 원래대로 돌아간다.
 *
 * 화면에는 <html> 의 클래스(easy-view, ev-hc)와 CSS 변수(--fs 글씨 배율, --ui 버튼 배율)로만 적용되며,
 * 관련 CSS 는 모두 html.easy-view 로 시작하므로 이 클래스가 없으면 기존 화면과 완전히 같다 (app.css 맨 아래 참고).
 */
const KEY = 'ak_easy_view'
export const EV_LEVELS = [
  { v: 'md', l: '보통', fs: 1, ui: 1 },
  { v: 'lg', l: '크게', fs: 1.25, ui: 1.2 },
  { v: 'xl', l: '아주 크게', fs: 1.5, ui: 1.4 },
]

function read() {
  try {
    const o = JSON.parse(localStorage.getItem(KEY))
    if (o && EV_LEVELS.some(x => x.v === o.level)) return { level: o.level, hc: !!o.hc, ts: Number(o.ts) || 0 }
  } catch {}
  return { level: 'md', hc: false, ts: 0 }
}

const prefs = ref(read())
const enabled = ref(true)          // 사이트 설정 (꺼 두면 false)
const hintSeen = ref((() => { try { return localStorage.getItem('ak_easy_view_hint') === '1' } catch { return true } })())

export const easyViewEnabled = computed(() => enabled.value)
export const easyViewPrefs = computed(() => prefs.value)
export const easyViewHintSeen = computed(() => hintSeen.value)

export function apply() {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  const lv = EV_LEVELS.find(x => x.v === prefs.value.level) || EV_LEVELS[0]
  const on = enabled.value && (lv.v !== 'md' || prefs.value.hc)
  root.classList.toggle('easy-view', on)
  root.classList.toggle('ev-hc', on && prefs.value.hc)
  if (on) { root.style.setProperty('--fs', String(lv.fs)); root.style.setProperty('--ui', String(lv.ui)); root.style.setProperty('--fs-h', String(+(1 + (lv.fs - 1) * 0.35).toFixed(3))) }
  else { root.style.removeProperty('--fs'); root.style.removeProperty('--ui'); root.style.removeProperty('--fs-h') }
}

function persist() {
  try { localStorage.setItem(KEY, JSON.stringify(prefs.value)) } catch {}
}

let pushTimer = null
function pushToServer() {
  clearTimeout(pushTimer)
  pushTimer = setTimeout(() => {
    if (!axios.defaults.headers.common?.Authorization) return   // 로그인 안 한 상태면 이 폰에만 저장
    axios.put('/api/user/easy-view', { level: prefs.value.level, hc: prefs.value.hc, ts: prefs.value.ts }).catch(() => {})
  }, 600)
}

export function setEasyView(patch) {
  prefs.value = { ...prefs.value, ...patch, ts: Date.now() }
  persist(); apply(); pushToServer()
  markHintSeen()
}

export function resetEasyView() { setEasyView({ level: 'md', hc: false }) }

export function setEasyViewEnabled(v) { enabled.value = !!v; apply() }

export function markHintSeen() {
  hintSeen.value = true
  try { localStorage.setItem('ak_easy_view_hint', '1') } catch {}
}

// 로그인한 회원의 서버 값과 이 폰의 값을 맞춘다 — 더 최근에 바꾼 쪽이 이긴다
export function syncEasyViewFromUser(user) {
  const raw = user?.easy_view_prefs
  if (!raw || typeof raw !== 'string') {
    // 서버에는 없고 이 폰에는 설정이 있으면 서버에 올려 둔다
    if (prefs.value.ts > 0) pushToServer()
    return
  }
  const [level, hc, ts] = raw.split('|')
  const serverTs = Number(ts) || 0
  if (!EV_LEVELS.some(x => x.v === level)) return
  if (serverTs > prefs.value.ts) {
    prefs.value = { level, hc: hc === '1', ts: serverTs }
    persist(); apply(); markHintSeen()
  } else if (prefs.value.ts > serverTs) {
    pushToServer()
  }
}
