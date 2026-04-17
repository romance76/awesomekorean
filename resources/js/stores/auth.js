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
    try {
      const { data } = await axios.get('/api/user')
      user.value = data.data || data
      const { primary } = storages()
      primary.setItem('sk_user', JSON.stringify(user.value))
    } catch { clearAuth() }
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

  return { user, token, isLoggedIn, isAdmin, initPromise, initialize, login, register, logout, fetchUser, resolveInit, updatePoints, refreshBalance }
})
