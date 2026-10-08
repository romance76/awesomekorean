<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!game" class="text-center py-12 text-red-500 text-[15px]">게임을 찾을 수 없어요.</div>
  <template v-else>
    <div class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-3">
        <div class="shrink-0 w-14 h-14 bg-gray-50 rounded-xl grid place-items-center text-[34px]" aria-hidden="true">{{ game.icon }}</div>
        <div class="min-w-0 flex-1">
          <div class="text-[18px] font-bold text-ink break-words">{{ game.name }}</div>
          <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="catBadge(game.category)">{{ catLabel(game.category) }}</span>
            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="game.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ game.is_active ? '활성' : '비활성' }}</span>
          </div>
        </div>
      </div>
      <div class="text-[12px] text-ink-faint mt-2 break-all">slug {{ game.slug }} · 경로 {{ game.path }}</div>
      <div class="grid gap-2 mt-3" :class="isQuizGame ? 'grid-cols-2' : 'grid-cols-1'">
        <a :href="game.path" target="_blank" rel="noopener" class="min-h-[48px] rounded-xl bg-amber-500 text-white text-[15px] font-bold grid place-items-center">게임 열기</a>
        <RouterLink v-if="isQuizGame" :to="`/admin/games/questions/${game.slug}`" class="min-h-[48px] rounded-xl bg-violet-50 text-violet-700 text-[15px] font-bold grid place-items-center">퀴즈 문제</RouterLink>
      </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-3">
      <div class="text-[16px] font-bold text-ink">기본 정보</div>
      <div><label class="block text-[14px] font-bold text-ink mb-1" for="gs-name">게임 이름</label><input id="gs-name" v-model="form.name" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
      <div class="grid grid-cols-3 gap-2">
        <div><label class="block text-[14px] font-bold text-ink mb-1" for="gs-icon">아이콘</label><input id="gs-icon" v-model="form.icon" maxlength="8" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-center" /></div>
        <div class="col-span-2"><label class="block text-[14px] font-bold text-ink mb-1" for="gs-cat">카테고리</label>
          <select id="gs-cat" v-model="form.category" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-2 bg-white">
            <option value="card">🃏 카드</option><option value="brain">🧠 두뇌</option><option value="arcade">👾 아케이드</option><option value="word">📝 단어/퀴즈</option><option value="education">📚 교육</option>
          </select></div>
      </div>
      <div><label class="block text-[14px] font-bold text-ink mb-1" for="gs-desc">설명 (한 줄)</label><input id="gs-desc" v-model="form.description" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
      <button type="button" @click="form.is_active = !form.is_active" role="switch" :aria-checked="!!form.is_active" class="w-full min-h-[52px] rounded-xl border px-4 flex items-center justify-between text-[15px]" :class="form.is_active ? 'bg-green-50 border-green-200 text-green-700 font-bold' : 'bg-white border-gray-200 text-ink'"><span>활성화 (유저 게임 목록에 노출)</span><span>{{ form.is_active ? '켜짐' : '꺼짐' }}</span></button>
      <button @click="mSaveGame" :disabled="savingGame" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ savingGame ? '저장 중...' : '기본 정보 저장' }}</button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-3">
      <div class="flex items-center gap-2">
        <div class="flex-1 min-w-0"><div class="text-[16px] font-bold text-ink">커스텀 설정</div><div class="text-[12px] text-ink-muted">게임별 파라미터 (예: max_players=4)</div></div>
        <button @click="addRow" class="shrink-0 min-h-[44px] px-4 rounded-xl bg-blue-50 text-blue-700 text-[14px] font-bold">+ 항목</button>
      </div>
      <div v-if="!settings.length && !newRows.length" class="text-center py-6 text-[14px] text-ink-muted bg-gray-50 rounded-xl">등록된 설정이 없어요</div>
      <div v-for="s in settings" :key="s.id || s.key" class="border border-gray-100 rounded-xl p-2.5 space-y-1.5">
        <div class="flex items-center gap-2"><div class="min-w-0 flex-1 text-[14px] font-bold font-mono text-ink break-all">{{ s.key }}</div>
          <button @click="mDel = s" class="shrink-0 min-h-[40px] px-3 rounded-lg bg-red-50 text-red-600 text-[13px] font-bold">삭제</button></div>
        <input v-model="s.value" :aria-label="`${s.key} 값`" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 font-mono" />
      </div>
      <div v-for="(r, i) in newRows" :key="'new' + i" class="border border-dashed border-amber-300 bg-amber-50/40 rounded-xl p-2.5 space-y-1.5">
        <div class="flex items-center gap-2">
          <input v-model="r.key" placeholder="key (예: max_players)" autocapitalize="none" :aria-label="`새 항목 ${i + 1} 이름`" class="min-w-0 flex-1 min-h-[48px] rounded-xl border border-gray-200 px-3 font-mono bg-white" />
          <button @click="newRows.splice(i, 1)" class="shrink-0 min-h-[48px] min-w-[48px] rounded-xl bg-gray-100 text-ink-muted" aria-label="새 항목 지우기">✕</button>
        </div>
        <input v-model="r.value" placeholder="value" :aria-label="`새 항목 ${i + 1} 값`" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 font-mono bg-white" />
      </div>
      <button @click="mSaveSettings" :disabled="savingSettings" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ savingSettings ? '저장 중...' : '설정 저장' }}</button>
    </div>
  </template>
  <Teleport to="body">
    <div v-if="mDel" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="mDel = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">설정을 삭제할까요?</div>
        <p class="text-[15px] text-ink-light mb-3 break-all font-mono">{{ mDel.key }}</p>
        <button @click="mRemove" :disabled="mBusy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ mBusy ? '삭제 중...' : '삭제하기' }}</button>
        <button @click="mDel = null" :disabled="mBusy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div class="flex items-center gap-3 mb-4">
    <RouterLink to="/admin/games" class="inline-flex items-center gap-1 text-xs text-ink-muted hover:text-amber-600 transition-colors"><AppIcon name="arrow-left" :size="13" /> 게임 관리</RouterLink>
  </div>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else-if="!game" class="text-center py-12 text-red-500">게임을 찾을 수 없습니다</div>
  <div v-else>
    <!-- 게임 헤더 -->
    <div class="card p-5 mb-4 flex items-center gap-4">
      <div class="w-16 h-16 bg-gray-50 rounded-xl flex items-center justify-center text-4xl flex-shrink-0">{{ game.icon }}</div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <h1 class="text-xl font-bold text-ink">{{ game.name }}</h1>
          <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="catBadge(game.category)">{{ catLabel(game.category) }}</span>
          <span v-if="game.is_active" class="badge-green">활성</span>
          <span v-else class="badge-gray">비활성</span>
        </div>
        <div class="text-xs text-ink-muted mt-1">{{ game.description || game.slug }}</div>
        <div class="text-[11px] text-ink-faint mt-0.5">slug: <code class="bg-gray-100 px-1">{{ game.slug }}</code> · 경로: <code class="bg-gray-100 px-1">{{ game.path }}</code></div>
      </div>
      <div class="flex flex-col gap-1 flex-shrink-0">
        <a :href="game.path" target="_blank" class="btn-primary !px-3 !py-1.5 !text-xs">
          <AppIcon name="external-link" :size="13" /> 게임 열기
        </a>
        <RouterLink v-if="isQuizGame" :to="`/admin/games/questions/${game.slug}`" class="inline-flex items-center justify-center gap-1 text-xs bg-violet-50 text-violet-700 font-semibold px-3 py-1.5 rounded-xl hover:bg-violet-100 transition-colors">
          <AppIcon name="help-circle" :size="13" /> 퀴즈 문제
        </RouterLink>
      </div>
    </div>

    <!-- 기본 정보 수정 -->
    <div class="card p-5 mb-4">
      <h2 class="flex items-center gap-2 font-bold text-sm text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="edit" :size="14" /></span>기본 정보
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="input-label">게임 이름</label>
          <input v-model="form.name" class="input-soft" />
        </div>
        <div>
          <label class="input-label">아이콘 (이모지)</label>
          <input v-model="form.icon" maxlength="8" class="input-soft" />
        </div>
        <div class="md:col-span-2">
          <label class="input-label">설명 (한 줄)</label>
          <input v-model="form.description" class="input-soft" />
        </div>
        <div>
          <label class="input-label">카테고리</label>
          <select v-model="form.category" class="input-soft">
            <option value="card">🃏 카드</option>
            <option value="brain">🧠 두뇌</option>
            <option value="arcade">👾 아케이드</option>
            <option value="word">📝 단어/퀴즈</option>
            <option value="education">📚 교육</option>
          </select>
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
            활성화 (유저 게임 목록에 노출)
          </label>
        </div>
      </div>
      <div class="flex items-center gap-3 mt-4">
        <button @click="saveGame" :disabled="savingGame" class="btn-primary !px-4 !py-1.5 !text-xs">
          {{ savingGame ? '저장중...' : '기본 정보 저장' }}
        </button>
        <span v-if="gameMsg" class="text-xs text-green-600">{{ gameMsg }}</span>
      </div>
    </div>

    <!-- 커스텀 설정 (key/value) -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="flex items-center gap-2 font-bold text-sm text-ink">
          <span class="icon-chip w-7 h-7 bg-blue-50 text-blue-600"><AppIcon name="settings" :size="14" /></span>커스텀 설정 <span class="font-normal text-xs text-ink-faint">(게임별 파라미터)</span>
        </h2>
        <button @click="addRow" class="btn-soft !px-3 !py-1 !text-xs"><AppIcon name="plus" :size="13" /> 항목 추가</button>
      </div>
      <p class="text-xs text-ink-muted mb-3">
        이 게임에서만 사용되는 설정을 key/value 로 저장합니다.
        예시: <code class="bg-gray-100 px-1">max_players=4</code>,
        <code class="bg-gray-100 px-1">time_limit=60</code>,
        <code class="bg-gray-100 px-1">difficulty=hard</code> 등.
        게임 코드에서 <code class="bg-gray-100 px-1">/api/game-settings/{slug}</code> 로 읽습니다.
      </p>

      <div v-if="!settings.length && !newRows.length" class="text-center py-8 text-sm text-ink-muted bg-gray-50 rounded-xl">
        등록된 설정이 없습니다
      </div>

      <div v-else class="space-y-2">
        <!-- 기존 설정 -->
        <div v-for="s in settings" :key="s.id || s.key" class="flex items-center gap-2">
          <input :value="s.key" disabled class="input-soft w-40 !px-2 !py-1.5 !text-xs font-mono opacity-70" />
          <input v-model="s.value" class="input-soft flex-1 !w-auto !px-2 !py-1.5 !text-xs font-mono" />
          <button @click="deleteSetting(s)" class="text-red-400 hover:text-red-600 flex-shrink-0 transition-colors"><AppIcon name="x" :size="14" /></button>
        </div>
        <!-- 신규 행 -->
        <div v-for="(r, i) in newRows" :key="'new' + i" class="flex items-center gap-2">
          <input v-model="r.key" placeholder="key (예: max_players)" class="input-soft w-40 !px-2 !py-1.5 !text-xs font-mono" />
          <input v-model="r.value" placeholder="value" class="input-soft flex-1 !w-auto !px-2 !py-1.5 !text-xs font-mono" />
          <button @click="newRows.splice(i, 1)" class="text-ink-faint hover:text-red-500 flex-shrink-0 transition-colors"><AppIcon name="x" :size="14" /></button>
        </div>
      </div>

      <div class="flex items-center gap-3 mt-4">
        <button @click="saveSettings" :disabled="savingSettings" class="btn-primary !px-4 !py-1.5 !text-xs">
          {{ savingSettings ? '저장중...' : '설정 저장' }}
        </button>
        <span v-if="settingMsg" class="text-xs text-green-600">{{ settingMsg }}</span>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, watch, inject, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()

// 관리자 휴대폰 화면이면 카드 + 큰 입력칸 + 확인 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const mDel = ref(null); const mBusy = ref(false)
watch(() => isMobile.value && !!mDel.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })
const slug = route.params.slug

const loading = ref(true)
const game = ref(null)
const settings = ref([])
const newRows = ref([])
const QUIZ_GAMES = ['animals','flag','idiom','proverb','satwords','uslife','shapes','colors','wordcard']
const isQuizGame = computed(() => game.value && QUIZ_GAMES.includes(game.value.slug))
const form = reactive({ name: '', icon: '', description: '', category: 'brain', is_active: true })
const savingGame = ref(false)
const savingSettings = ref(false)
const gameMsg = ref('')
const settingMsg = ref('')

function catLabel(c) {
  return { card: '카드', brain: '두뇌', arcade: '아케이드', word: '단어', education: '교육' }[c] || c
}
function catBadge(c) {
  return {
    card: 'bg-purple-100 text-purple-700', brain: 'bg-blue-100 text-blue-700',
    arcade: 'bg-red-100 text-red-700', word: 'bg-green-100 text-green-700',
    education: 'bg-amber-100 text-amber-700',
  }[c] || 'bg-gray-100 text-ink-light'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/admin/games/${slug}/settings`)
    game.value = data.data?.game || null
    settings.value = data.data?.settings || []
    if (game.value) {
      form.name = game.value.name
      form.icon = game.value.icon
      form.description = game.value.description || ''
      form.category = game.value.category
      form.is_active = !!game.value.is_active
    }
  } catch {}
  loading.value = false
}

async function saveGame() {
  if (!game.value) return
  savingGame.value = true; gameMsg.value = ''
  try {
    await axios.put(`/api/admin/games/${game.value.id}`, form)
    gameMsg.value = '저장됨!'
    game.value = { ...game.value, ...form }
  } catch { gameMsg.value = '저장 실패' }
  savingGame.value = false
  setTimeout(() => gameMsg.value = '', 2500)
}

function addRow() { newRows.value.push({ key: '', value: '' }) }

async function saveSettings() {
  savingSettings.value = true; settingMsg.value = ''
  const payload = [
    ...settings.value.map(s => ({ key: s.key, value: s.value })),
    ...newRows.value.filter(r => r.key.trim()),
  ]
  try {
    await axios.post(`/api/admin/games/${slug}/settings`, { settings: payload })
    settingMsg.value = '저장됨!'
    newRows.value = []
    await load()
  } catch { settingMsg.value = '저장 실패' }
  savingSettings.value = false
  setTimeout(() => settingMsg.value = '', 2500)
}

async function deleteSetting(s) {
  if (!confirm(`'${s.key}' 삭제?`)) return
  try {
    await axios.delete(`/api/admin/games/${slug}/settings/${encodeURIComponent(s.key)}`)
    settings.value = settings.value.filter(x => x.key !== s.key)
  } catch { alert('삭제 실패') }
}

async function mSaveGame() {
  if (!game.value || savingGame.value) return
  savingGame.value = true
  try { await axios.put(`/api/admin/games/${game.value.id}`, form); game.value = { ...game.value, ...form }; say('저장했어요') }
  catch (e) { say(e.response?.data?.message || '저장하지 못했어요', true) }
  savingGame.value = false
}
async function mSaveSettings() {
  if (savingSettings.value) return
  savingSettings.value = true
  const payload = [...settings.value.map(s => ({ key: s.key, value: s.value })), ...newRows.value.filter(r => r.key.trim())]
  try { await axios.post(`/api/admin/games/${slug}/settings`, { settings: payload }); newRows.value = []; say('저장했어요'); await load() }
  catch (e) { say(e.response?.data?.message || '저장하지 못했어요', true) }
  savingSettings.value = false
}
async function mRemove() {
  if (mBusy.value || !mDel.value) return
  mBusy.value = true
  const s = mDel.value
  try { await axios.delete(`/api/admin/games/${slug}/settings/${encodeURIComponent(s.key)}`); settings.value = settings.value.filter(x => x.key !== s.key); mDel.value = null; say('삭제했어요') }
  catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
  finally { mBusy.value = false }
}

onMounted(load)
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
