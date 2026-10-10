import { reactive } from 'vue'
import axios from 'axios'

/**
 * 관리자 위험 동작(키 변경·열람, 결제 설정, 회원 삭제·대행 로그인·비밀번호 초기화) 앞의 비밀번호 재확인.
 * 서버가 428(code=reauth_required)로 막으면 AdminLayout 의 axios 인터셉터가 askReauth() 로 비밀번호 창을 띄우고,
 * 맞으면(10분 유효) 막혔던 요청을 자동으로 다시 보낸다.
 */
export const reauthState = reactive({ open: false, busy: false, error: '' })
let resolver = null

export function askReauth() {
  return new Promise((resolve) => {
    // 이미 창이 떠 있으면 같은 결과를 기다린다
    if (reauthState.open && resolver) { const prev = resolver; resolver = (ok) => { prev(ok); resolve(ok) }; return }
    reauthState.open = true; reauthState.error = ''; reauthState.busy = false
    resolver = resolve
  })
}

export async function submitReauth(password) {
  if (reauthState.busy) return
  if (!password) { reauthState.error = '비밀번호를 입력해 주세요'; return }
  reauthState.busy = true; reauthState.error = ''
  try {
    await axios.post('/api/admin/reauth', { password })
    reauthState.open = false
    const r = resolver; resolver = null
    r && r(true)
  } catch (e) {
    reauthState.error = e.response?.data?.message || '확인하지 못했어요. 잠시 뒤 다시 시도해 주세요'
  }
  reauthState.busy = false
}

export function cancelReauth() {
  reauthState.open = false
  const r = resolver; resolver = null
  r && r(false)
}

// 428 응답이면 비밀번호 창 → 성공 시 같은 요청 재시도 (요청당 1번만)
export function installReauthInterceptor() {
  const id = axios.interceptors.response.use(
    (r) => r,
    async (err) => {
      const r = err.response
      if (r && r.status === 428 && r.data?.code === 'reauth_required' && err.config && !err.config.__reauthTried) {
        err.config.__reauthTried = true
        const ok = await askReauth()
        if (ok) return axios(err.config)
      }
      return Promise.reject(err)
    },
  )
  return () => axios.interceptors.response.eject(id)
}
