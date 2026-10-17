<template>
<div class="border border-gray-200 rounded-xl bg-white overflow-hidden">
  <div class="flex items-center gap-2 flex-wrap px-3 py-2 border-b border-gray-100 bg-gray-50">
    <label class="btn-secondary text-xs px-3 py-1.5 cursor-pointer inline-flex items-center gap-1" :class="busy ? 'opacity-50 pointer-events-none' : ''">
      📷 {{ busy ? '올리는 중...' : '사진 넣기' }}
      <input type="file" accept="image/*" multiple class="hidden" @change="pick" />
    </label>
    <span class="text-[11px] text-ink-muted">사진 넣을 자리에 커서를 두고 누르세요 · <b>캡처한 화면은 Ctrl+V(붙여넣기)로 바로 넣어져요</b> ·
      <template v-if="minPhotos > 0">직접 찍은 사진 <b :class="photoCount >= minPhotos ? 'text-green-600' : 'text-red-500'">{{ photoCount }}</b>/{{ minPhotos }}장 이상</template>
      <template v-else>사진 {{ photoCount }}장</template>
    </span>
  </div>

  <div class="p-3 space-y-2" @paste="onPaste">
    <template v-for="(b, i) in blocks" :key="b.id">
      <div v-if="b.type === 'text'" class="relative">
        <!-- 걸린 글자 표시: 글 칸 뒤에 같은 글을 깔고 걸린 부분만 빨갛게 칠한다 (글 칸은 배경이 투명) -->
        <div v-if="hasFlag(b.text)" class="flag-mirror absolute inset-0 w-full rounded-lg border border-transparent px-2 py-1.5 text-sm leading-relaxed text-transparent pointer-events-none whitespace-pre-wrap break-words overflow-hidden" aria-hidden="true" v-html="flagHtml(b.text)"></div>
        <textarea v-model="b.text" rows="3" :ref="el => setRef(b.id, el)"
          class="relative w-full rounded-lg border border-transparent hover:border-gray-200 focus:border-amber-300 focus:outline-none px-2 py-1.5 text-sm leading-relaxed resize-none overflow-hidden bg-transparent"
          :placeholder="i === 0 ? '얼마나 오래, 어떻게 써봤는지 / 좋은 점 / 아쉬운 점을 자유롭게 써주세요. 사진은 위의 [사진 넣기]로 넣거나, 주문내역 같은 캡처 화면을 복사해서 여기에 붙여넣기(Ctrl+V) 하세요.' : '이어서 쓰기...'"
          @input="autosize($event.target); mark(i, $event)" @focus="mark(i, $event)" @click="mark(i, $event)" @keyup="mark(i, $event)"></textarea>
      </div>
      <div v-else class="relative rounded-lg overflow-hidden border border-gray-100 bg-gray-50">
        <img :src="b.url" alt="" class="w-full max-h-96 object-contain" />
        <div class="absolute top-1.5 right-1.5 flex gap-1">
          <button type="button" @click="move(i, -1)" class="w-7 h-7 rounded-full bg-black/60 text-white text-xs" title="위로">▲</button>
          <button type="button" @click="move(i, 1)" class="w-7 h-7 rounded-full bg-black/60 text-white text-xs" title="아래로">▼</button>
          <button type="button" @click="removeImage(i)" class="w-7 h-7 rounded-full bg-red-500 text-white text-xs" title="사진 빼기">✕</button>
        </div>
      </div>
    </template>
  </div>
  <p v-if="err" class="px-3 pb-2 text-xs text-red-500">{{ err }}</p>
</div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({ modelValue: { type: Array, required: true }, minPhotos: { type: Number, default: 0 }, flag: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])

const blocks = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })
const photoCount = computed(() => blocks.value.filter(b => b.type === 'image').length)
const busy = ref(false)
const err = ref('')
const refs = {}

// 걸린 글자(flag)를 글 속에서 찾는 식 — 글자 사이에 공백·기호를 끼워 넣은 경우도 같이 찾는다 (필터가 그런 우회도 막기 때문)
const flagSrc = computed(() => {
  const w = (props.flag || '').trim()
  if (!w) return ''
  const esc = c => c.replace(/[.*+?^${}()|[\]\\/-]/g, '\\$&')
  const gap = '[\\s*._~^#+=()\\[\\]{}<>/|?,;:"\'`-]*'
  return [...w].filter(c => !/\s/.test(c)).map(esc).join(gap)
})
const escHtml = t => String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const hasFlag = text => !!flagSrc.value && new RegExp(flagSrc.value, 'iu').test(text || '')
function flagHtml(text) {
  const src = text || ''
  if (!flagSrc.value) return escHtml(src)
  const re = new RegExp(flagSrc.value, 'giu')
  let out = '', last = 0, m
  while ((m = re.exec(src)) && m[0]) { out += escHtml(src.slice(last, m.index)) + '<mark class="flag-hit">' + escHtml(m[0]) + '</mark>'; last = m.index + m[0].length }
  return out + escHtml(src.slice(last)) + '\n'
}
const focus = { idx: -1, pos: 0 }
let uid = 0
const nid = () => `b${Date.now()}_${uid++}`

function setRef(id, el) { if (el) refs[id] = el }
function autosize(el) { if (!el) return; el.style.height = 'auto'; el.style.height = Math.max(el.scrollHeight, 72) + 'px' }
function mark(i, e) { focus.idx = i; focus.pos = e.target.selectionStart ?? 0 }

// 맨 앞/맨 뒤/사진과 사진 사이에는 항상 글 칸이 있게 하고, 글 칸끼리 붙으면 합친다
function normalize(list) {
  const out = []
  for (const b of list) {
    const last = out[out.length - 1]
    if (b.type === 'text' && last && last.type === 'text') { last.text = [last.text, b.text].filter(Boolean).join('\n'); continue }
    if (b.type === 'image' && (!last || last.type === 'image')) out.push({ id: nid(), type: 'text', text: '' })
    out.push(b)
  }
  if (!out.length || out[out.length - 1].type === 'image') out.push({ id: nid(), type: 'text', text: '' })
  return out
}
function commit(list) { blocks.value = normalize(list); nextTick(() => Object.values(refs).forEach(autosize)) }

async function pick(e) {
  const files = Array.from(e.target.files || []); e.target.value = ''
  await upload(files)
}

// 클립보드에 사진(캡처)이 있으면 글자 대신 사진으로 넣는다. 글자만 있으면 평소처럼 붙여넣기.
function onPaste(e) {
  const files = Array.from(e.clipboardData?.items || []).filter(it => it.kind === 'file' && it.type.startsWith('image/')).map(it => it.getAsFile()).filter(Boolean)
  if (!files.length) return
  e.preventDefault()
  if (e.target?.tagName === 'TEXTAREA') {
    const i = blocks.value.findIndex(b => refs[b.id] === e.target)
    if (i >= 0) { focus.idx = i; focus.pos = e.target.selectionStart ?? 0 }
  }
  upload(files)
}

async function upload(files) {
  if (!files.length || busy.value) return
  err.value = ''; busy.value = true
  for (const file of files) {
    if (photoCount.value >= 10) { err.value = '사진은 최대 10장까지 넣을 수 있어요.'; break }
    try {
      const fd = new FormData(); fd.append('image', file)
      const { data } = await axios.post('/api/shopping/review-image', fd)
      insertImage(data.data.url)
    } catch (er) { err.value = er.response?.data?.message || '사진을 올리지 못했어요'; break }
  }
  busy.value = false
}

// 커서가 있던 글 칸을 커서 위치에서 둘로 쪼개 그 사이에 사진을 넣는다 (커서 없으면 맨 아래)
function insertImage(url) {
  const list = blocks.value.map(b => ({ ...b }))
  const img = { id: nid(), type: 'image', url }
  const i = focus.idx
  if (i >= 0 && list[i] && list[i].type === 'text') {
    const t = list[i].text || ''
    const pos = Math.min(focus.pos, t.length)
    const before = { id: list[i].id, type: 'text', text: t.slice(0, pos).replace(/\s+$/, '') }
    const after = { id: nid(), type: 'text', text: t.slice(pos).replace(/^\s+/, '') }
    list.splice(i, 1, before, img, after)
    focus.idx = i + 2; focus.pos = 0
  } else {
    list.push(img)
    focus.idx = list.length; focus.pos = 0   // normalize 가 뒤에 글 칸을 붙인다
  }
  commit(list)
}
function removeImage(i) { const l = blocks.value.map(b => ({ ...b })); l.splice(i, 1); commit(l) }
function move(i, d) {
  const l = blocks.value.map(b => ({ ...b })); const j = i + d
  if (j < 0 || j >= l.length) return
  ;[l[i], l[j]] = [l[j], l[i]]; commit(l)
}

onMounted(() => nextTick(() => Object.values(refs).forEach(autosize)))
</script>
<style scoped>
.flag-mirror :deep(.flag-hit) { background: #fecaca; color: transparent; border-bottom: 2px solid #ef4444; border-radius: 2px; }
</style>
