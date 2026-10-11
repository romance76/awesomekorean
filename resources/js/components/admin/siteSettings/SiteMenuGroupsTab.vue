<template>
<div>
  <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
    <div>
      <h2 class="text-base font-semibold text-ink">상단 메뉴 묶음 (메뉴 트리)</h2>
      <p class="text-xs text-ink-faint mt-0.5">메뉴를 묶음으로 모아 상단에 보여 줘요. 끌어서(또는 ▲▼) 순서·묶음을 바꾸고, 묶음 이름은 바로 고쳐요. 메뉴 이름·켜기/끄기·로그인 필요는 "메뉴 관리" 탭에서.</p>
    </div>
    <div class="flex gap-2">
      <button @click="resetDefaults" :disabled="loading || saving" class="btn-secondary text-xs">기본 묶음으로</button>
      <button @click="save" :disabled="loading || saving" class="btn-primary">{{ saving ? '저장 중...' : '저장' }}</button>
    </div>
  </div>

  <p v-if="loadError" class="bg-red-50 text-red-600 text-sm rounded-xl p-3 mb-3">{{ loadError }}</p>

  <!-- 켜기 스위치 -->
  <div class="flex items-center gap-3 rounded-2xl border px-4 py-3 mb-4" :class="enabled ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-gray-50'">
    <button type="button" role="switch" :aria-checked="enabled" @click="enabled = !enabled"
      class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="enabled ? 'bg-emerald-500' : 'bg-gray-300'">
      <span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="enabled ? 'left-[26px]' : 'left-[4px]'"></span>
    </button>
    <div class="text-sm">
      <b class="text-ink">{{ enabled ? '묶음 메뉴 사용 중' : '묶음 메뉴 꺼짐 (지금은 예전 메뉴)' }}</b>
      <div class="text-xs text-ink-muted">켜고 "저장"하면 사이트 상단·휴대폰 메뉴가 아래 묶음대로 바뀌어요. 문제가 있으면 끄고 저장하면 바로 예전 메뉴로 돌아가요.</div>
    </div>
  </div>

  <div v-if="loading" class="text-center text-sm text-ink-muted py-10">불러오는 중...</div>
  <div v-else class="grid gap-4" :class="isMobile ? '' : 'grid-cols-[1fr_300px]'">
    <!-- 트리 -->
    <div class="space-y-3">
      <div v-for="(g, gi) in groups" :key="g._id"
        class="rounded-2xl border-2 bg-white transition-colors"
        :class="[dragOver === gi ? 'border-pink-300 bg-pink-50/40' : 'border-gray-100', g.hidden ? 'opacity-60' : '']"
        :draggable="!isMobile" @dragstart="onDragStart($event, 'g', gi)" @dragend="onDragEnd"
        @dragover.prevent="dragOver = gi" @dragleave="dragOver = null" @drop.prevent="onDrop(gi)">
        <div class="flex flex-wrap items-center gap-2 px-3 py-2.5 bg-gray-50 rounded-t-2xl">
          <span v-if="!isMobile" class="cursor-grab text-ink-faint select-none" title="끌어서 순서 바꾸기">⠿</span>
          <input v-model="g.icon" maxlength="4" class="w-10 text-center rounded-lg border border-gray-200 bg-white py-1" aria-label="아이콘" />
          <input v-model="g.label" maxlength="30" class="flex-1 min-w-[120px] rounded-lg border border-gray-200 bg-white px-2 py-1 font-bold text-ink" aria-label="묶음 이름" />
          <input v-model="g.label_en" maxlength="40" placeholder="영어 이름" class="w-28 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs" aria-label="영어 이름" />
          <select v-model="g.type" class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs" aria-label="종류">
            <option value="single">혼자 메뉴</option>
            <option value="group">묶음 (하위 탭)</option>
            <option value="hub">허브 (첫 화면)</option>
          </select>
          <input v-if="g.type === 'hub'" v-model="g.path" placeholder="/money" class="w-24 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs" aria-label="허브 주소" />
          <button @click="g.hidden = !g.hidden" class="text-xs font-bold rounded-lg border border-gray-200 bg-white px-2 py-1">{{ g.hidden ? '보이기' : '숨김' }}</button>
          <button @click="moveGroup(gi, -1)" :disabled="gi === 0" class="w-7 h-7 rounded-lg border border-gray-200 bg-white disabled:opacity-25" aria-label="묶음 위로">▲</button>
          <button @click="moveGroup(gi, 1)" :disabled="gi === groups.length - 1" class="w-7 h-7 rounded-lg border border-gray-200 bg-white disabled:opacity-25" aria-label="묶음 아래로">▼</button>
          <button v-if="!g.items.length" @click="groups.splice(gi, 1)" class="text-xs text-red-500 px-1">삭제</button>
        </div>
        <div class="p-2.5 pl-8 space-y-1.5 min-h-[44px]">
          <div v-for="(k, ii) in g.items" :key="k"
            class="flex items-center gap-2 rounded-xl border border-gray-100 px-2.5 py-1.5 text-sm bg-white"
            :draggable="!isMobile" @dragstart.stop="onDragStart($event, 'i', gi, ii)" @dragend="onDragEnd">
            <span v-if="!isMobile" class="cursor-grab text-ink-faint select-none">⠿</span>
            <span class="font-semibold text-ink">{{ menuLabel(k) }}</span>
            <span class="text-[11px] text-ink-faint font-mono">{{ k }}</span>
            <input v-if="g.type !== 'single'" :value="g.item_labels?.[k] || ''" @input="setItemLabel(g, k, $event.target.value)" maxlength="30"
              placeholder="묶음 안 이름(선택)" class="w-32 rounded-lg border border-gray-200 px-2 py-1 text-xs" :aria-label="menuLabel(k) + ' 묶음 안 이름'" />
            <span v-if="menuOff(k)" class="text-[11px] font-bold text-amber-700 bg-amber-50 rounded-full px-2">메뉴 관리에서 꺼짐</span>
            <span class="flex-1"></span>
            <button @click="moveItem(gi, ii, -1)" :disabled="ii === 0" class="w-7 h-7 rounded-lg border border-gray-200 disabled:opacity-25" aria-label="위로">▲</button>
            <button @click="moveItem(gi, ii, 1)" :disabled="ii === g.items.length - 1" class="w-7 h-7 rounded-lg border border-gray-200 disabled:opacity-25" aria-label="아래로">▼</button>
            <select @change="moveTo(gi, ii, $event.target.value); $event.target.value = ''" class="rounded-lg border border-gray-200 px-1 py-1 text-xs" aria-label="다른 묶음으로">
              <option value="">옮기기…</option>
              <option v-for="(o, oi) in groups" :key="o._id" v-show="oi !== gi" :value="oi">{{ o.label }}</option>
              <option value="out">묶음에서 빼기</option>
            </select>
          </div>
          <p v-if="!g.items.length" class="text-xs text-ink-faint py-1">여기로 메뉴를 끌어 놓거나, 아래 "묶음에 없는 메뉴"에서 넣어 주세요.</p>
          <p v-if="warn(g)" class="text-xs font-bold text-red-500">{{ warn(g) }}</p>
        </div>
      </div>
      <button @click="addGroup" class="w-full rounded-2xl border-2 border-dashed border-gray-200 py-3 text-sm font-bold text-ink-muted">+ 묶음 추가</button>

      <!-- 묶음에 없는 메뉴 -->
      <div class="rounded-2xl border border-gray-100 bg-white p-3">
        <div class="text-sm font-bold text-ink mb-1">묶음에 없는 메뉴 <span class="text-xs font-normal text-ink-faint">— 상단 메뉴에 안 보여요 (주소로는 열려요)</span></div>
        <div class="flex flex-wrap gap-2">
          <span v-for="m in unassigned" :key="m.key" class="inline-flex items-center gap-1 rounded-full border border-gray-200 pl-3 pr-1 py-1 text-sm"
            :draggable="!isMobile" @dragstart="onDragStart($event, 'u', -1, m.key)" @dragend="onDragEnd">
            {{ m.label }} <span class="text-[11px] text-ink-faint font-mono">{{ m.key }}</span>
            <select @change="putIn(m.key, $event.target.value); $event.target.value = ''" class="rounded-full border border-gray-200 px-1 py-0.5 text-xs" aria-label="묶음에 넣기">
              <option value="">넣기…</option>
              <option v-for="(o, oi) in groups" :key="o._id" :value="oi">{{ o.label }}</option>
            </select>
          </span>
          <span v-if="!unassigned.length" class="text-xs text-ink-faint">모든 메뉴가 묶음에 들어 있어요.</span>
        </div>
      </div>
    </div>

    <!-- 미리보기 -->
    <div class="rounded-2xl border border-gray-100 bg-white p-4 h-fit">
      <div class="text-sm font-bold text-ink mb-2">미리보기 — 상단 메뉴</div>
      <div v-for="g in groups.filter(x => !x.hidden)" :key="g._id" class="py-2 border-b border-gray-50 last:border-0">
        <div class="text-sm font-bold text-ink">{{ g.icon }} {{ g.label }} <span class="text-[11px] font-normal text-ink-faint">{{ typeLabel(g.type) }}</span></div>
        <div v-if="g.type !== 'single' && g.items.length" class="text-xs text-ink-muted mt-0.5">└ {{ g.items.map(k => g.item_labels?.[k] || menuLabel(k)).join(' · ') }}</div>
      </div>
      <div v-if="groups.some(x => x.hidden)" class="text-xs text-ink-faint mt-2">숨김: {{ groups.filter(x => x.hidden).map(x => x.label).join(', ') }}</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../../stores/site'

defineProps({ isMobile: { type: Boolean, default: false } })
const site = useSiteStore()
const say = (m, t = 'success') => site.toast?.(m, t)

const loading = ref(true); const saving = ref(false); const loadError = ref('')
const enabled = ref(false)
const groups = ref([])
const menus = ref([])
const defaults = ref([])
let uid = 0
const withId = (arr) => arr.map(g => ({ hidden: false, path: '', label_en: '', icon: '', ...g, items: [...(g.items || [])], _id: ++uid }))

async function load() {
  loading.value = true; loadError.value = ''
  try {
    const { data } = await axios.get('/api/admin/settings/menu-groups')
    enabled.value = !!data.data.enabled
    menus.value = data.data.menus || []
    defaults.value = data.data.defaults || []
    groups.value = withId(data.data.groups || [])
  } catch (e) { loadError.value = e.response?.data?.message || '메뉴 묶음을 불러오지 못했어요' }
  loading.value = false
}
onMounted(load)

const menuMap = computed(() => Object.fromEntries(menus.value.map(m => [m.key, m])))
const menuLabel = (k) => menuMap.value[k]?.label || k
const menuOff = (k) => menuMap.value[k] && menuMap.value[k].enabled === false
const typeLabel = (t) => ({ single: '혼자', group: '묶음', hub: '허브' }[t] || t)
const unassigned = computed(() => {
  const used = new Set(groups.value.flatMap(g => g.items))
  return menus.value.filter(m => m.key && !used.has(m.key))
})
function warn(g) {
  if (!g.label?.trim()) return '묶음 이름을 적어 주세요.'
  if (g.type === 'single' && g.items.length !== 1) return '혼자 메뉴는 메뉴가 정확히 1개여야 해요. (여러 개면 종류를 "묶음"으로)'
  if (g.type === 'group' && !g.items.length) return '묶음에 메뉴가 없어요.'
  if (g.type === 'hub' && !g.path) return '허브는 첫 화면 주소가 필요해요.'
  return ''
}

function setItemLabel(g, k, v) {
  const labels = { ...(g.item_labels || {}) }
  if (v.trim()) labels[k] = v; else delete labels[k]
  g.item_labels = labels
}
function moveGroup(i, d) { const a = groups.value; const j = i + d; if (j < 0 || j >= a.length) return; [a[i], a[j]] = [a[j], a[i]] }
function moveItem(gi, ii, d) { const a = groups.value[gi].items; const j = ii + d; if (j < 0 || j >= a.length) return; [a[ii], a[j]] = [a[j], a[ii]] }
function moveTo(gi, ii, target) {
  if (target === '') return
  const k = groups.value[gi].items.splice(ii, 1)[0]
  if (target !== 'out') groups.value[Number(target)].items.push(k)
}
function putIn(key, target) { if (target === '') return; groups.value[Number(target)].items.push(key) }
function addGroup() { groups.value.push(withId([{ key: 'g' + Date.now().toString(36), label: '새 묶음', icon: '📁', type: 'group', items: [] }])[0]) }
function resetDefaults() { if (confirm('기본 묶음으로 되돌릴까요? (저장해야 반영돼요)')) groups.value = withId(defaults.value) }

// 끌어서 옮기기 (PC)
const dragOver = ref(null)
let drag = null
function onDragStart(e, type, gi, extra) { drag = { type, gi, extra }; e.dataTransfer.effectAllowed = 'move' }
function onDragEnd() { dragOver.value = null }
function onDrop(gi) {
  dragOver.value = null
  if (!drag) return
  if (drag.type === 'i') { const k = groups.value[drag.gi].items.splice(drag.extra, 1)[0]; groups.value[gi].items.push(k) }
  else if (drag.type === 'u') { groups.value[gi].items.push(drag.extra) }
  else if (drag.type === 'g' && drag.gi !== gi) { const g = groups.value.splice(drag.gi, 1)[0]; groups.value.splice(gi, 0, g) }
  drag = null
}

async function save() {
  const bad = groups.value.find(g => warn(g))
  if (bad) { say(`'${bad.label || '이름 없음'}': ${warn(bad)}`, 'error'); return }
  saving.value = true
  try {
    const payload = groups.value.map(({ _id, ...g }) => ({ ...g, path: g.type === 'hub' ? g.path : undefined }))
    const { data } = await axios.post('/api/admin/settings/menu-groups', { groups: payload, enabled: enabled.value })
    say(data.message || '저장했어요')
    site.updateMenuGroups?.(data.data?.groups || payload, enabled.value)
  } catch (e) { say(e.response?.data?.message || '저장하지 못했어요', 'error') }
  saving.value = false
}
</script>
