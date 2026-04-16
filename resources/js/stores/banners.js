import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useBannerStore = defineStore('banners', () => {
  // 페이지별 배너 캐시 { 'directory': { left: [...], right: [...], mobile: {...} } }
  const cache = ref({})
  const loading = ref({})
  const promises = {}

  async function loadForPage(page) {
    if (cache.value[page]) return cache.value[page]
    if (promises[page]) return promises[page]

    loading.value[page] = true
    promises[page] = _fetch(page)
    return promises[page]
  }

  async function _fetch(page) {
    try {
      const { data } = await axios.get('/api/banners/all', { params: { page } })
      cache.value[page] = data.data || { left: [], right: [], mobile: null }
    } catch {
      cache.value[page] = { left: [], right: [], mobile: null }
    }
    loading.value[page] = false
    delete promises[page]
    return cache.value[page]
  }

  function getLeft(page) { return cache.value[page]?.left || [] }
  function getRight(page) { return cache.value[page]?.right || [] }
  function getMobile(page) { return cache.value[page]?.mobile || null }

  return { cache, loading, loadForPage, getLeft, getRight, getMobile }
})
