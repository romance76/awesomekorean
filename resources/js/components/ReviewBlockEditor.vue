<template>
<div class="border border-gray-200 rounded-xl bg-white overflow-hidden">
  <div class="flex items-center gap-2 flex-wrap px-3 py-2 border-b border-gray-100 bg-gray-50">
    <label class="btn-secondary text-xs px-3 py-1.5 cursor-pointer inline-flex items-center gap-1" :class="busy ? 'opacity-50 pointer-events-none' : ''">
      📷 {{ busy ? '올리는 중...' : '사진 넣기' }}
      <input type="file" accept="image/*" multiple class="hidden" @change="pick" />
    </label>
    <span class="text-[11px] text-ink-muted">글 쓰다가 사진을 넣을 자리에 커서를 두고 누르세요 · 직접 찍은 사진 <b :class="photoCount >= minPhotos ? 'text-green-600' : 'text-red-500'">{{ photoCount }}</b>/{{ minPhotos }}장 이상</span>
  </div>

  <div class="p-3 space-y-2">
    <template v-for="(b, i) in blocks" :key="b.id">
      <textarea v-if="b.type === 'text'" v-model="b.text" rows="3" :ref="el => setRef(b.id, el)"
        class="w-full rounded-lg border border-transparent hover:border-gray-200 focus:border-amber-300 focus:outline-none px-2 py-1.5 text-sm leading-relaxed resize-none overflow-hidden"
        :placeholder="i === 0 ? '얼마나 오래, 어떻게 써봤는지 / 좋은 점 / 아쉬운 점을 자유롭게 써주세요. 사진은 위의 [사진 넣기]로 글 사이에 넣어요.' : '이어서 쓰기...'"
        @input="autosize($event.target); mark(i, $event)" @focus="mark(i, $event)" @click="mark(i, $event)" @keyup="mark(i, $event)"></textarea>
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

const props = defineProps({ modelValue: { type: Array, required: true }, minPhotos: { type: Number, default: 2 } })
const emit = defineEmits(['update:modelValue'])

const blocks = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })
const photoCount = computed(() => blocks.value.filter(b => b.type === 'image').length)
const busy = ref(false)
const err = ref('')
const refs = {}
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
  if (!files.length) return
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
