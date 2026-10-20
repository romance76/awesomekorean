<template>
  <span><template v-for="(p, i) in parts" :key="i"><a v-if="p.url" :href="p.url" target="_blank" rel="noopener noreferrer nofollow" class="text-primary underline break-all" @click.prevent.stop="openExternal(p.url)">{{ p.text }}</a><a v-else-if="p.tel" :href="'tel:' + p.tel" class="text-primary underline">{{ p.text }}</a><template v-else>{{ p.text }}</template></template></span>
</template>

<script setup>
// 이벤트 본문 글 속의 http(s) 주소를 새 탭 링크로, "📞 문의:" 뒤의 전화번호를 전화 링크로 바꿔 보여 준다.
// 주소는 http/https 로 시작하는 것만 링크로 만들고, 본문은 v-html 이 아니라 글자로 그려서 안전하다.
import { computed } from 'vue'
import { openExternal } from '../../utils/eventCategory'

const props = defineProps({ text: { type: String, default: '' } })

const URL_RE = /(https?:\/\/[^\s<>"')]+)/g
const PHONE_RE = /(📞 문의: )(\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4})/

const parts = computed(() => {
  const s = props.text || ''
  const out = []
  let last = 0
  let m
  URL_RE.lastIndex = 0
  while ((m = URL_RE.exec(s)) !== null) {
    const url = m[1].replace(/[.,;:!?]+$/, '')
    if (m.index > last) out.push({ text: s.slice(last, m.index) })
    out.push({ url, text: url })
    last = m.index + url.length
    URL_RE.lastIndex = last
  }
  if (last < s.length) out.push({ text: s.slice(last) })
  // 전화번호 줄: 글 조각 안에서 "📞 문의: 번호" 를 찾아 번호만 링크로
  const result = []
  for (const p of out) {
    if (p.url) { result.push(p); continue }
    const pm = p.text.match(PHONE_RE)
    if (!pm) { result.push(p); continue }
    const i = p.text.indexOf(pm[0])
    result.push({ text: p.text.slice(0, i) + pm[1] })
    result.push({ tel: pm[2].replace(/[^\d+]/g, ''), text: pm[2] })
    result.push({ text: p.text.slice(i + pm[0].length) })
  }
  return result
})
</script>
