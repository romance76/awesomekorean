import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

// Issue #4: localStorage(영구) vs sessionStorage(세션만)
// "로그인 유지" 체크 시 localStorage, 해제 시 sessionStorage
const PERSIST_KEY = 'sk_auth_persist'
function storages() {
  const persist = localStorage.getItem(PERSIST_KEY) !== '0'
  return { persist, primary: persist ? localStorage : sessionStorage }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(null)
  let _resolveInit
  const initPromise = new Promise(r => { _resolveInit = r })

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => ['admin', 'super_admin'].includes(user.value?.role))
  // 서버 EnsureEmailVerified 미들웨어와 같은 기준 — 이메일 미인증 일반 회원은 글쓰기 불가
  const needsVerification = computed(() =>
    !!token.value && !!user.value && !user.value.email_verified_at
    && !['admin', 'super_admin', 'moderator'].includes(user.value.role)
  )

  // 어느 스토리지에 있든 토큰 읽기 (세션 → 로컬 순)
  function readStoredAuth() {
    const t = sessionStorage.getItem('sk_token') || localStorage.getItem('sk_token')
    const u = sessionStorage.getItem('sk_user') || localStorage.getItem('sk_user')
    return { t, u }
  }

  function initialize() {
    const { t, u } = readStoredAuth()
    if (t) {
      token.value = t
      axios.defaults.headers.common['Authorization'] = `Bearer ${t}`
      if (u) try { user.value = JSON.parse(u) } catch {}
    }
  }

  async function login(email, password, remember = true) {
    // remember 설정 저장 — 이후 세션에서도 재적용
    localStorage.setItem(PERSIST_KEY, remember ? '1' : '0')
    const { data } = await axios.post('/api/login', { email, password })
    setAuth(data.data.token, data.data.user)
    return data
  }

  // 소셜 로그인 콜백(SocialCallback.vue)에서 URL로 받은 JWT로 로그인 완료
  async function loginWithToken(tok, remember = true) {
    localStorage.setItem(PERSIST_KEY, remember ? '1' : '0')
    token.value = tok
    axios.defaults.headers.common['Authorization'] = `Bearer ${tok}`
    const { data } = await axios.get('/api/user')
    setAuth(tok, data.data)
  }

  async function register(form) {
    // 회원가입 시엔 기본적으로 remember=true
    localStorage.setItem(PERSIST_KEY, '1')
    const { data } = await axios.post('/api/register', form)
    setAuth(data.data.token, data.data.user)
    return data
  }

  async function logout() {
    try { await axios.post('/api/logout') } catch {}
    clearAuth()
  }

  async function fetchUser() {
    const wasUnverified = needsVerification.value
    try {
      const { data } = await axios.get('/api/user')
      user.value = data.data || data
      const { primary } = storages()
      primary.setItem('sk_user', JSON.stringify(user.value))
      if (wasUnverified && !needsVerification.value) markVerified(true)
    } catch (e) {
      // 토큰이 실제로 무효(401)할 때만 로그아웃 처리. 새로고침 직후
      // 일시적인 네트워크 오류/서버 500 등으로 이 요청만 실패한 경우까지
      // 로그아웃시키면, 멀쩡히 로그인된 사용자가 새로고침할 때마다
      // 세션이 날아가 버리는 문제가 생김 — 이 경우엔 initialize()가
      // 이미 채워둔 캐시된 user를 그대로 유지한다.
      if (e.response?.status === 401) clearAuth()
    }
    finally { _resolveInit() }
  }

  function resolveInit() { _resolveInit() }

  function setAuth(tok, usr) {
    token.value = tok; user.value = usr
    const { primary, persist } = storages()
    // 양쪽 중복 저장 방지: 기존 값 제거 후 선택된 스토리지에만 저장
    localStorage.removeItem('sk_token'); localStorage.removeItem('sk_user')
    sessionStorage.removeItem('sk_token'); sessionStorage.removeItem('sk_user')
    primary.setItem('sk_token', tok)
    primary.setItem('sk_user', JSON.stringify(usr))
    axios.defaults.headers.common['Authorization'] = `Bearer ${tok}`
  }

  function clearAuth() {
    token.value = null; user.value = null
    localStorage.removeItem('sk_token')
    localStorage.removeItem('sk_user')
    sessionStorage.removeItem('sk_token')
    sessionStorage.removeItem('sk_user')
    delete axios.defaults.headers.common['Authorization']
  }

  // Issue #13: 포인트 값 부분 갱신 + localStorage 동기화
  function updatePoints(points, gamePoints = null) {
    if (!user.value) return
    if (points !== null && points !== undefined) user.value.points = points
    if (gamePoints !== null) user.value.game_points = gamePoints
    try {
      const { primary } = storages()
      primary.setItem('sk_user', JSON.stringify(user.value))
    } catch {}
  }

  // Issue #13: 최신 잔액 서버 조회 후 localStorage 반영 (캐싱 없이)
  async function refreshBalance() {
    try {
      const { data } = await axios.get('/api/points/balance')
      const p = data.data?.points
      const gp = data.data?.game_points
      updatePoints(p, gp)
    } catch {}
  }

  // ─── 이메일 인증 완료 실시간 반영 ───
  // 인증은 메일 링크(다른 탭/기기)에서 끝나므로, 열려 있던 탭도 새로고침 없이 반영:
  //  · 같은 브라우저 다른 탭에서 인증 → BroadcastChannel 로 즉시 알림
  //  · 휴대폰 등 다른 기기에서 인증 → 이 탭으로 돌아올 때(focus/visible) 재조회
  // 미인증 상태일 때만 조회하므로 일반 회원에게는 추가 요청이 없음.
  const justVerified = ref(false)
  let _verifiedAnnounced = false
  let _bc = null
  try { _bc = new BroadcastChannel('ak-auth') } catch {}

  async function announceVerified(msg = '이메일 인증이 완료되었습니다. 이제 글쓰기가 가능합니다.') {
    if (_verifiedAnnounced) return
    _verifiedAnnounced = true
    try {
      const { useSiteStore } = await import('./site')
      useSiteStore().toast(msg, 'success', 5000)
    } catch {}
  }

  function markVerified(broadcast) {
    justVerified.value = true
    announceVerified()
    if (broadcast) try { _bc?.postMessage('email-verified') } catch {}
  }

  let _lastRecheck = 0
  async function recheckVerification() {
    if (!needsVerification.value || Date.now() - _lastRecheck < 3000) return
    _lastRecheck = Date.now()
    await fetchUser()
  }

  if (_bc) _bc.onmessage = (e) => { if (e.data === 'email-verified') { _lastRecheck = 0; recheckVerification() } }
  if (typeof window !== 'undefined') {
    window.addEventListener('focus', recheckVerification)
    document.addEventListener('visibilitychange', () => { if (!document.hidden) recheckVerification() })
  }

  // 인증 메일 재발송 시점의 페이지 — 메일 링크 클릭 후 이 페이지로 돌려보냄 (/email-verified)
  // keepExisting: 마이페이지 재발송처럼, 앞서 막혔던 글쓰기 페이지가 저장돼 있으면 그쪽을 유지
  function rememberVerifyReturn(path, keepExisting = false) {
    try {
      if (keepExisting && localStorage.getItem('sk_verify_return')) return
      localStorage.setItem('sk_verify_return', path || (window.location.pathname + window.location.search))
    } catch {}
  }
  function takeVerifyReturn() {
    let p = null
    try { p = localStorage.getItem('sk_verify_return'); localStorage.removeItem('sk_verify_return') } catch {}
    return p && p.startsWith('/') && !p.startsWith('//') ? p : null
  }

  return { user, token, isLoggedIn, isAdmin, needsVerification, justVerified, announceVerified, rememberVerifyReturn, takeVerifyReturn, initPromise, initialize, login, loginWithToken, register, logout, fetchUser, resolveInit, updatePoints, refreshBalance }
})
