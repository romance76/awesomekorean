<template>
  <!-- 당첨자 안내: 연락처(이메일·전화번호·주소) 확인 전까지 로그인/접속 시마다 표시 -->
  <Teleport to="body">
    <Transition name="wpp">
      <div v-if="visibleClaims.length" class="wpp-backdrop" @click.self="dismissAll">
        <div class="wpp-card" role="dialog" aria-modal="true" aria-label="당첨 안내">
          <div class="wpp-confetti" aria-hidden="true">
            <i v-for="n in 14" :key="n" :style="confettiStyle(n)"></i>
          </div>
          <div class="text-center relative">
            <div class="text-4xl mb-1">🎉</div>
            <h2 class="text-xl font-extrabold text-ink">당첨을 축하드려요!</h2>
            <p class="text-sm text-ink-muted mt-2 leading-relaxed">
              상품(디지털 상품권 등)을 보내드릴 수 있도록<br />
              <b class="text-ink">이메일 · 전화번호 · 주소</b>를 정확히 확인하고 업데이트해 주세요.
            </p>
          </div>

          <div class="mt-4 space-y-3 max-h-[46vh] overflow-y-auto relative">
            <div v-for="c in visibleClaims" :key="c.id" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-3">
              <div class="text-sm font-bold text-ink">
                {{ c.event_title }} · {{ c.rank }}등 · <span class="text-amber-600">{{ c.prize_name }}</span>
              </div>
              <ul class="mt-2 space-y-1 text-[13px]">
                <li v-for="f in fields" :key="f.key" class="flex items-start gap-2">
                  <span>{{ isMissing(c, f.key) ? '⚠️' : '✅' }}</span>
                  <span class="text-ink-muted w-14 shrink-0">{{ f.label }}</span>
                  <span class="min-w-0 break-words" :class="isMissing(c, f.key) ? 'text-red-500 font-semibold' : 'text-ink'">
                    {{ isMissing(c, f.key) ? '입력 필요' : c.contact_status[f.key] }}
                  </span>
                </li>
              </ul>
              <p v-if="errors[c.id]" class="mt-2 text-xs text-red-500 font-semibold">{{ errors[c.id] }}</p>
              <button @click="confirm(c)" :disabled="busy[c.id]"
                class="mt-2 w-full rounded-full bg-white border border-amber-300 text-amber-700 text-sm font-bold py-2 hover:bg-amber-50 disabled:opacity-50">
                {{ busy[c.id] ? '확인 중…' : '확인했어요' }}
              </button>
            </div>
          </div>

          <div class="mt-4 flex gap-2 relative">
            <button @click="goProfile" class="btn-primary flex-1 !py-2.5 text-sm">내 정보 확인·수정하기</button>
            <button @click="dismissAll" class="rounded-full border border-line text-ink-muted text-sm font-bold px-4 hover:bg-gray-50">나중에</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
<script setup>
import { ref, computed, reactive, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const claims = ref([])
const hiddenIds = ref(new Set()) // 이번 화면에서 "나중에" 누른 건
const errors = reactive({})
const busy = reactive({})
let timer = null

const fields = [
  { key: 'email', label: '이메일' },
  { key: 'phone', label: '전화번호' },
  { key: 'address', label: '주소' },
]

const visibleClaims = computed(() =>
  claims.value.filter(c => !c.dismissed_recently && !hiddenIds.value.has(c.id))
)

function isMissing(c, key) {
  return (c.contact_status?.missing || []).includes(key)
}

function confettiStyle(n) {
  const colors = ['#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#a855f7', '#ec4899']
  return {
    left: `${(n * 7.3) % 100}%`,
    background: colors[n % colors.length],
    animationDelay: `${(n % 5) * 0.35}s`,
    animationDuration: `${2.6 + (n % 4) * 0.5}s`,
  }
}

async function refresh() {
  if (!auth.isLoggedIn || !auth.user?.id || document.hidden) return
  try {
    const { data } = await axios.get('/api/me/prize-claims')
    claims.value = data?.data || []
  } catch { /* 조용히 무시 */ }
}

async function confirm(c) {
  busy[c.id] = true
  errors[c.id] = ''
  try {
    await axios.post(`/api/me/prize-claims/${c.id}/confirm`)
    claims.value = claims.value.filter(x => x.id !== c.id)
  } catch (e) {
    errors[c.id] = e?.response?.data?.message || '확인에 실패했어요. 잠시 후 다시 시도해 주세요.'
    // 최신 프로필 기준으로 상태 다시 불러오기
    if (e?.response?.status === 422) refresh()
  } finally {
    busy[c.id] = false
  }
}

function goProfile() {
  // 확인 대기 상태는 유지: 프로필 수정 후 돌아와 [확인했어요]
  visibleClaims.value.forEach(c => hiddenIds.value.add(c.id))
  hiddenIds.value = new Set(hiddenIds.value)
  router.push('/dashboard?tab=profile')
}

async function dismissAll() {
  const list = [...visibleClaims.value]
  list.forEach(c => hiddenIds.value.add(c.id))
  hiddenIds.value = new Set(hiddenIds.value)
  for (const c of list) {
    try { await axios.post(`/api/me/prize-claims/${c.id}/dismiss`) } catch {}
  }
}

function onVisible() { if (!document.hidden) refresh() }

onMounted(() => {
  document.addEventListener('visibilitychange', onVisible)
  timer = setInterval(refresh, 5 * 60 * 1000)
})
onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisible)
  if (timer) clearInterval(timer)
})
watch(() => auth.user?.id, (id) => { if (id) refresh(); else claims.value = [] }, { immediate: true })
</script>
<style scoped>
.wpp-backdrop { position: fixed; inset: 0; z-index: 90; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; padding: 16px; }
.wpp-card { position: relative; overflow: hidden; width: 100%; max-width: 420px; background: #fff; border-radius: 24px; padding: 24px 20px 20px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.wpp-confetti { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.wpp-confetti i { position: absolute; top: -12px; width: 8px; height: 14px; border-radius: 2px; opacity: .85; animation: wpp-fall linear infinite; }
@keyframes wpp-fall {
  0% { transform: translateY(0) rotate(0deg); }
  100% { transform: translateY(560px) rotate(540deg); }
}
.wpp-enter-active, .wpp-leave-active { transition: opacity .25s; }
.wpp-enter-from, .wpp-leave-to { opacity: 0; }
</style>
