// Detail 컴포넌트 공통 fetch (404 → /404 리다이렉트, 그 외 에러 → toast)
// Issue #16 / #17 공통 처리
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

/**
 * 단일 리소스 상세 fetch 공통 훅
 * @param {string} apiPath - 예: '/api/events' (뒤에 /{id} 자동)
 * @param {() => (number|string)} getId - 현재 id 반환 (보통 () => route.params.id)
 * @param {object} opts
 * @param {(msg:string)=>void} opts.onError - toast 등 (선택)
 * @param {boolean} opts.autoLoad - onMounted 에서 자동 호출 (기본 true)
 */
export function useDetailFetch(apiPath, getId, opts = {}) {
  const router = useRouter()
  const item = ref(null)
  const loading = ref(true)
  const notFound = ref(false)

  async function load() {
    const id = getId()
    if (!id) { loading.value = false; return }
    loading.value = true
    notFound.value = false
    try {
      const { data } = await axios.get(`${apiPath}/${id}`)
      item.value = data.data ?? data
    } catch (err) {
      if (err.response?.status === 404) {
        notFound.value = true
        router.replace('/404')
      } else if (opts.onError) {
        opts.onError(err.response?.data?.message || '페이지를 불러올 수 없습니다')
      }
    } finally {
      loading.value = false
    }
  }

  if (opts.autoLoad !== false) {
    onMounted(load)
  }

  return { item, loading, notFound, reload: load, load }
}
