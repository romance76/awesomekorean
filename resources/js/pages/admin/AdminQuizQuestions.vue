<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <div class="flex items-center gap-2">
    <p class="flex-1 min-w-0 text-[13px] text-ink-muted">{{ slug }} 퀴즈 문제 · 총 <b class="text-ink">{{ meta.total || 0 }}</b>건. 오답을 비우면 같은 레벨의 다른 정답에서 자동으로 섞여요.</p>
    <button @click="openNew" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">+ 새 문제</button>
  </div>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="레벨">
    <button @click="filterLevel = ''; page = 1; load()" :aria-pressed="filterLevel === ''" class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="filterLevel === '' ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200'">전체</button>
    <button v-for="l in [1,2,3,4,5]" :key="l" @click="filterLevel = l; page = 1; load()" :aria-pressed="filterLevel === l" class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="filterLevel === l ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200'">Lv.{{ l }}</button>
  </div>
  <form @submit.prevent="page = 1; load()" class="flex gap-2">
    <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
      <AppIcon name="search" :size="18" />
      <input v-model="filterSearch" type="search" placeholder="정답으로 검색" aria-label="정답으로 검색" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" />
    </label>
    <button type="submit" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
  </form>
  <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!items.length" class="text-center py-12 text-ink-muted text-[15px]">문제가 없어요.</div>
  <div v-else class="space-y-2">
    <div v-for="q in items" :key="q.id" class="bg-white border border-gray-100 rounded-2xl p-3 flex items-center gap-3" :class="!q.is_active ? 'opacity-60' : ''">
      <div class="shrink-0 w-14 h-14 bg-gray-50 rounded-xl grid place-items-center overflow-hidden">
        <img v-if="imageUrl(q)" :src="imageUrl(q)" alt="" class="w-12 h-12 object-contain" @error="(e) => e.target.style.display='none'" />
        <span v-else class="text-ink-faint">-</span>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5"><span class="text-[12px] font-bold text-violet-600 bg-violet-50 px-1.5 py-0.5 rounded">Lv.{{ q.level }}</span><span class="text-[16px] font-bold text-ink truncate">{{ q.answer }}</span></div>
        <div class="text-[12px] text-ink-muted truncate">오답: {{ q.wrong_answers || '(자동)' }}</div>
        <div v-if="q.hint || q.sound" class="text-[12px] text-ink-muted truncate"><span v-if="q.hint">💡 {{ q.hint }} </span><span v-if="q.sound">🔊 {{ q.sound }}</span></div>
      </div>
      <div class="shrink-0 flex flex-col items-end gap-1.5">
        <button @click="toggle(q)" role="switch" :aria-checked="!!q.is_active" :aria-label="`${q.answer} 활성`" class="relative w-[52px] h-[32px] rounded-full transition-colors" :class="q.is_active ? 'bg-green-500' : 'bg-gray-300'">
          <span class="absolute top-[3px] left-[3px] w-[26px] h-[26px] bg-white rounded-full shadow transition-transform" :class="q.is_active ? 'translate-x-5' : ''"></span>
        </button>
        <div class="flex gap-1">
          <button @click="edit(q)" class="min-h-[40px] px-3 rounded-lg bg-amber-50 text-amber-700 text-[13px] font-bold">수정</button>
          <button @click="mDel = q" class="min-h-[40px] px-3 rounded-lg bg-red-50 text-red-600 text-[13px] font-bold">삭제</button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="meta.last_page > 1" class="flex items-center justify-between gap-2 pt-1">
    <button @click="page--; load()" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
    <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ meta.last_page }}</span>
    <button @click="page++; load()" :disabled="page >= meta.last_page" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
  </div>

  <Teleport to="body">
    <div v-if="modalOpen" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="modalOpen = false">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" :aria-label="form.id ? '문제 수정' : '새 문제'" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">{{ form.id ? '문제 수정' : '새 문제' }}</div>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-2">
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="qq-lv">레벨</label>
              <select id="qq-lv" v-model.number="form.level" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-2 bg-white"><option v-for="l in [1,2,3,4,5]" :key="l" :value="l">Lv.{{ l }}</option></select></div>
            <div><div class="block text-[14px] font-bold text-ink mb-1">활성</div>
              <button type="button" @click="form.is_active = !form.is_active" role="switch" :aria-checked="!!form.is_active" class="w-full min-h-[48px] rounded-xl border text-[15px]" :class="form.is_active ? 'bg-green-50 border-green-200 text-green-700 font-bold' : 'bg-white border-gray-200 text-ink'">{{ form.is_active ? '켜짐' : '꺼짐' }}</button></div>
          </div>
          <div><label class="block text-[14px] font-bold text-ink mb-1" for="qq-ans">정답</label><input id="qq-ans" v-model="form.answer" placeholder="예: 강아지" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
          <div><label class="block text-[14px] font-bold text-ink mb-1" for="qq-wr">오답 (선택, ||| 로 구분)</label><input id="qq-wr" v-model="form.wrong_answers" placeholder="고양이|||토끼|||여우 (비우면 자동)" autocapitalize="none" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
          <div class="grid grid-cols-2 gap-2">
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="qq-hex">이모지 hex</label><input id="qq-hex" v-model="form.emoji_hex" placeholder="1f436" autocapitalize="none" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 font-mono" /></div>
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="qq-img">또는 이미지 주소</label><input id="qq-img" v-model="form.image_url" placeholder="https://..." inputmode="url" autocapitalize="none" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
          </div>
          <div v-if="imageUrl(form)" class="text-center"><img :src="imageUrl(form)" alt="미리보기" class="w-24 h-24 object-contain inline-block border border-gray-100 rounded-xl bg-gray-50" /></div>
          <div class="grid grid-cols-2 gap-2">
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="qq-hint">힌트</label><input id="qq-hint" v-model="form.hint" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="qq-snd">음성 표현</label><input id="qq-snd" v-model="form.sound" placeholder="멍멍!" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
          </div>
        </div>
        <button @click="save" :disabled="saving || !form.answer.trim()" class="mt-4 w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ saving ? '저장 중...' : '저장' }}</button>
        <button @click="modalOpen = false" :disabled="saving" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="mDel" class="alv-m fixed inset-0 z-[75] bg-black/45 flex items-end" @click.self="mDel = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">문제를 삭제할까요?</div>
        <p class="text-[15px] text-ink-light mb-3 break-words">정답: {{ mDel.answer }}</p>
        <button @click="mRemove" :disabled="mBusy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ mBusy ? '삭제 중...' : '삭제하기' }}</button>
        <button @click="mDel = null" :disabled="mBusy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div class="flex items-center gap-3 mb-3">
    <RouterLink :to="`/admin/games/settings/${slug}`" class="inline-flex items-center gap-1 text-xs text-ink-muted hover:text-amber-600 transition-colors"><AppIcon name="arrow-left" :size="13" /> {{ slug }} 설정</RouterLink>
  </div>

  <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
    <div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="help-circle" :size="20" /></span>
        {{ slug }} 퀴즈 문제
      </h1>
      <p class="text-xs text-ink-muted mt-1">이미지-선택지 퀴즈 문제를 추가/수정/삭제합니다. 선택지는 같은 레벨의 다른 정답에서 자동으로 섞입니다 (직접 지정 가능).</p>
    </div>
    <button @click="openNew" class="btn-primary !px-3 !py-1.5 !text-sm"><AppIcon name="plus" :size="14" /> 새 문제</button>
  </div>

  <!-- 필터 -->
  <div class="flex items-center gap-2 mb-3 flex-wrap">
    <select v-model="filterLevel" @change="load" class="input-soft !w-auto !py-1.5 !text-xs">
      <option value="">레벨 전체</option>
      <option v-for="l in [1,2,3,4,5]" :key="l" :value="l">Lv.{{ l }}</option>
    </select>
    <input v-model="filterSearch" @keyup.enter="load" placeholder="정답으로 검색" class="input-soft flex-1 !w-auto min-w-[200px] max-w-[300px] !py-1.5 !text-xs" />
    <button @click="load" class="btn-secondary !px-3 !py-1.5 !text-xs">검색</button>
    <div class="ml-auto text-xs text-ink-muted">총 <strong>{{ meta.total || 0 }}</strong>건</div>
  </div>

  <!-- 목록 -->
  <div v-if="loading" class="text-center py-10 text-ink-muted">로딩중...</div>
  <div v-else-if="!items.length" class="py-16 text-center">
    <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="help-circle" :size="28" :stroke-width="1.5" /></div>
    <p class="text-sm text-ink-muted">문제가 없습니다</p>
  </div>
  <div v-else class="card overflow-hidden">
    <table class="w-full text-xs">
      <thead class="bg-gray-50 text-ink-muted font-bold">
        <tr>
          <th class="px-2 py-2 w-12 text-left">Lv</th>
          <th class="px-2 py-2 w-14">이미지</th>
          <th class="px-2 py-2 text-left">정답</th>
          <th class="px-2 py-2 text-left hidden md:table-cell">오답(선택)</th>
          <th class="px-2 py-2 text-left hidden sm:table-cell">힌트/음성</th>
          <th class="px-2 py-2 w-20">활성</th>
          <th class="px-2 py-2 w-24"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="q in items" :key="q.id" class="border-t border-gray-50 hover:bg-amber-50/30 transition-colors">
          <td class="px-2 py-2 text-center font-bold text-violet-600">Lv.{{ q.level }}</td>
          <td class="px-2 py-2 text-center">
            <img v-if="imageUrl(q)" :src="imageUrl(q)" class="w-10 h-10 object-contain mx-auto"
              @error="(e) => e.target.style.display='none'" />
            <span v-else class="text-ink-faint">-</span>
          </td>
          <td class="px-2 py-2 font-bold text-ink">{{ q.answer }}</td>
          <td class="px-2 py-2 text-ink-muted hidden md:table-cell truncate max-w-[200px]">{{ q.wrong_answers || '(자동)' }}</td>
          <td class="px-2 py-2 text-ink-muted hidden sm:table-cell">
            <div v-if="q.hint" class="text-[11px]">💡 {{ q.hint }}</div>
            <div v-if="q.sound" class="text-[11px]">🔊 {{ q.sound }}</div>
          </td>
          <td class="px-2 py-2 text-center">
            <label class="cursor-pointer inline-flex items-center">
              <input type="checkbox" :checked="q.is_active" @change="toggle(q)" class="sr-only peer" />
              <div class="w-8 h-4 bg-gray-300 peer-checked:bg-green-500 rounded-full relative transition-colors">
                <div class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
              </div>
            </label>
          </td>
          <td class="px-2 py-2 text-right space-x-2">
            <button @click="edit(q)" class="text-amber-600 hover:underline">수정</button>
            <button @click="remove(q)" class="text-red-500 hover:underline">삭제</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 페이지네이션 -->
  <div v-if="meta.last_page > 1" class="flex justify-center gap-1 mt-4">
    <button v-for="p in meta.last_page" :key="p" @click="page = p; load()"
      :class="['text-xs w-8 h-8 rounded-lg transition-colors', page === p ? 'bg-amber-400 text-white font-bold' : 'text-ink-muted hover:bg-gray-100']">
      {{ p }}
    </button>
  </div>

  <!-- 모달 -->
  <div v-if="modalOpen" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="modalOpen = false">
    <div class="bg-white rounded-2xl w-full max-w-lg p-5 shadow-2xl">
      <h3 class="font-bold text-base text-ink mb-3">{{ form.id ? '문제 수정' : '새 문제' }}</h3>
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="input-label">레벨 *</label>
            <select v-model.number="form.level" class="input-soft">
              <option v-for="l in [1,2,3,4,5]" :key="l" :value="l">Lv.{{ l }}</option>
            </select>
          </div>
          <div>
            <label class="input-label">활성</label>
            <label class="flex items-center gap-2 pt-1.5">
              <input v-model="form.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
              <span class="text-sm">활성</span>
            </label>
          </div>
        </div>
        <div>
          <label class="input-label">정답 *</label>
          <input v-model="form.answer" class="input-soft" placeholder="예: 강아지" />
        </div>
        <div>
          <label class="input-label">오답 (선택) - <code>|||</code> 로 구분</label>
          <input v-model="form.wrong_answers" class="input-soft" placeholder="예: 고양이|||토끼|||여우 (비우면 같은 레벨 다른 정답에서 자동)" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="input-label">Noto 이모지 hex</label>
            <input v-model="form.emoji_hex" class="input-soft font-mono" placeholder="1f436" />
          </div>
          <div>
            <label class="input-label">또는 이미지 URL</label>
            <input v-model="form.image_url" class="input-soft" placeholder="https://..." />
          </div>
        </div>
        <div v-if="imageUrl(form)" class="text-center">
          <img :src="imageUrl(form)" class="w-24 h-24 object-contain inline-block border border-gray-100 rounded-xl bg-gray-50" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="input-label">힌트</label>
            <input v-model="form.hint" class="input-soft" />
          </div>
          <div>
            <label class="input-label">음성 표현</label>
            <input v-model="form.sound" class="input-soft" placeholder="예: 멍멍!" />
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button @click="modalOpen = false" class="btn-ghost !px-4 !py-1.5">취소</button>
        <button @click="save" :disabled="saving" class="btn-primary !px-4 !py-1.5">
          {{ saving ? '저장중...' : '저장' }}
        </button>
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

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const mDel = ref(null); const mBusy = ref(false)
async function mRemove() {
  if (mBusy.value || !mDel.value) return
  mBusy.value = true
  try { await axios.delete(`/api/admin/games/${slug}/questions/${mDel.value.id}`); mDel.value = null; say('삭제했어요'); await load() }
  catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
  finally { mBusy.value = false }
}
const slug = route.params.slug

const items = ref([])
const meta = ref({ total: 0, last_page: 1 })
const page = ref(1)
const loading = ref(false)
const filterLevel = ref('')
const filterSearch = ref('')

const modalOpen = ref(false)
watch(() => isMobile.value && (modalOpen.value || !!mDel.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })
const saving = ref(false)
const form = reactive({ id: null, level: 1, answer: '', wrong_answers: '', emoji_hex: '', image_url: '', hint: '', sound: '', is_active: true })

function imageUrl(q) {
  if (q.image_url) return q.image_url
  if (q.emoji_hex) return `https://fonts.gstatic.com/s/e/notoemoji/latest/${q.emoji_hex}/512.png`
  return ''
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (filterLevel.value) params.level = filterLevel.value
    if (filterSearch.value.trim()) params.search = filterSearch.value.trim()
    const { data } = await axios.get(`/api/admin/games/${slug}/questions`, { params })
    items.value = data.data?.data || []
    meta.value = { total: data.data?.total || 0, last_page: data.data?.last_page || 1 }
  } catch { items.value = [] }
  loading.value = false
}

function resetForm() {
  Object.assign(form, { id: null, level: 1, answer: '', wrong_answers: '', emoji_hex: '', image_url: '', hint: '', sound: '', is_active: true })
}

function openNew() { resetForm(); modalOpen.value = true }
function edit(q) { Object.assign(form, { ...q, wrong_answers: q.wrong_answers || '' }); modalOpen.value = true }

async function save() {
  if (!form.answer.trim()) return isMobile.value ? say('정답을 입력하세요', true) : alert('정답을 입력하세요')
  saving.value = true
  try {
    if (form.id) {
      await axios.put(`/api/admin/games/${slug}/questions/${form.id}`, form)
    } else {
      await axios.post(`/api/admin/games/${slug}/questions`, form)
    }
    modalOpen.value = false
    await load()
  } catch (e) {
    if (isMobile.value) say('저장하지 못했어요: ' + (e.response?.data?.message || e.message), true)
    else alert('저장 실패: ' + (e.response?.data?.message || e.message))
  }
  saving.value = false
}

async function toggle(q) {
  const prev = q.is_active
  q.is_active = !q.is_active
  try { await axios.put(`/api/admin/games/${slug}/questions/${q.id}`, { is_active: q.is_active }) }
  catch { q.is_active = prev }
}

async function remove(q) {
  if (!confirm(`'${q.answer}' 문제를 삭제할까요?`)) return
  try {
    await axios.delete(`/api/admin/games/${slug}/questions/${q.id}`)
    await load()
  } catch { alert('삭제 실패') }
}

onMounted(load)
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
