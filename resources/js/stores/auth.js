import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'
import { tokenExpiresAtMs } from '../utils/jwt'

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


  // ─── 세션 만료/비활동 보안 ───────────────────────────────────────
  // 토큰이 끝났는데도 화면(특히 관리자 화면)이 그대로 남아 있던 문제 방지:
  //  · 토큰 만료 시각을 직접 확인해 만료되면 즉시 로그아웃 + 로그인 화면으로 이동
  //  · 서버가 401(토큰 무효)을 돌려주면(다른 기기 로그아웃, 비밀번호 변경, 정지 등) 즉시 로그아웃
  //  · 관리자는 30분 동안 아무 조작이 없으면 자동 로그아웃
  //  · 사용 중인 회원은 만료 10분 전부터 조용히 토큰을 갱신해 1시간마다 끊기지 않음
  const ADMIN_IDLE_MS = 30 * 60 * 1000
  const ACTIVE_WINDOW_MS = 15 * 60 * 1000
  let lastActivity = Date.now()
  let watchTimer = null
  let refreshing = false

  function expiresAtMs() { return token.value ? tokenExpiresAtMs(token.value) : null }

  function expireSession(message = '로그인 시간이 지나 자동으로 로그아웃됐어요. 다시 로그인해 주세요.') {
    if (!token.value) return
    clearAuth()
    try { window.dispatchEvent(new CustomEvent('ak:session-expired', { detail: { message } })) } catch {}
  }

  async function refreshToken() {
    if (refreshing || !token.value) return false
    refreshing = true
    try {
      const { data } = await axios.post('/api/auth/refresh')
      setAuth(data.data.token, user.value)
      return true
    } catch (e) {
      if (e.response?.status === 401) expireSession()
      return false
    } finally { refreshing = false }
  }

  async function sessionTick() {
    if (!token.value) return
    const now = Date.now()
    const exp = expiresAtMs()
    const active = now - lastActivity < ACTIVE_WINDOW_MS
    if (isAdmin.value && now - lastActivity > ADMIN_IDLE_MS) {
      return expireSession('관리자 보안을 위해 30분 동안 조작이 없어 자동으로 로그아웃됐어요.')
    }
    if (exp && exp <= now) {
      if (active && await refreshToken()) return           // 만료 직후라도 방금까지 쓰던 사람은 갱신
      return expireSession()
    }
    if (exp && exp - now < 10 * 60 * 1000 && active) await refreshToken()
  }

  function startSessionWatch() {
    if (watchTimer || typeof window === 'undefined') return
    let last = 0
    const touch = () => { const n = Date.now(); if (n - last > 3000) { last = n; lastActivity = n } }
    ;['click', 'keydown', 'touchstart', 'mousemove', 'scroll'].forEach(ev => window.addEventListener(ev, touch, { passive: true }))
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') sessionTick() })
    window.addEventListener('ak:auth-401', () => expireSession())   // 서버가 토큰을 거부함
    window.addEventListener('storage', (e) => {                     // 다른 탭에서 로그아웃하면 이 탭도 로그아웃
      if (e.key === 'sk_token' && !e.newValue && token.value) { token.value = null; user.value = null; delete axios.defaults.headers.common['Authorization']; window.dispatchEvent(new CustomEvent('ak:session-expired', { detail: { message: '다른 창에서 로그아웃했어요.' } })) }
    })
    watchTimer = setInterval(sessionTick, 20000)
    sessionTick()
  }

  /** 관리자 화면에 들어갈 때마다 서버에서 지금도 관리자인지 직접 확인 (저장된 정보만 믿지 않음) */
  async function verifyAdminNow() {
    if (!token.value) return false
    const exp = expiresAtMs()
    if (exp && exp <= Date.now() && !(await refreshToken())) { expireSession(); return false }
    try {
      const { data } = await axios.get('/api/user')
      user.value = data.data || data
      const { primary } = storages()
      primary.setItem('sk_user', JSON.stringify(user.value))
      return ['admin', 'super_admin', 'moderator'].includes(user.value?.role)
    } catch (e) {
      if (e.response?.status === 401) expireSession()
      return false
    }
  }

  return { startSessionWatch, expireSession, refreshToken, verifyAdminNow, user, token, isLoggedIn, isAdmin, needsVerification, justVerified, announceVerified, rememberVerifyReturn, takeVerifyReturn, initPromise, initialize, login, loginWithToken, register, logout, fetchUser, resolveInit, updatePoints, refreshBalance }
})
