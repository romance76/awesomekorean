import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(null)
  let _resolveInit
  const initPromise = new Promise(r => { _resolveInit = r })

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  function initialize() {
    const t = localStorage.getItem('sk_token')
    const u = localStorage.getItem('sk_user')
    if (t) {
      token.value = t
      axios.defaults.headers.common['Authorization'] = `Bearer ${t}`
      if (u) try { user.value = JSON.parse(u) } catch {}
    }
  }

  async function login(email, password) {
    const { data } = await axios.post('/api/login', { email, password })
    setAuth(data.data.token, data.data.user)
    return data
  }

  async function register(form) {
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
      localStorage.setItem('sk_user', JSON.stringify(user.value))
    } catch { clearAuth() }
    finally { _resolveInit() }
  }

  function resolveInit() { _resolveInit() }

  function setAuth(tok, usr) {
    token.value = tok; user.value = usr
    localStorage.setItem('sk_token', tok)
    localStorage.setItem('sk_user', JSON.stringify(usr))
    axios.defaults.headers.common['Authorization'] = `Bearer ${tok}`
  }

  function clearAuth() {
    token.value = null; user.value = null
    localStorage.removeItem('sk_token')
    localStorage.removeItem('sk_user')
    delete axios.defaults.headers.common['Authorization']
  }

  return { user, token, isLoggedIn, isAdmin, initPromise, initialize, login, register, logout, fetchUser, resolveInit }
})
