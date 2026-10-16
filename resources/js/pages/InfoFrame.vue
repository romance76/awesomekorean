<template>
  <!-- 정보(/info) 글은 검색엔진용으로 서버에서 그리는 페이지라, 예전엔 들어갈 때마다 사이트 전체가 새로 불러와져 듣던 음악이 끊겼다.
       사이트 안에서 이동할 때는 같은 서버 페이지를 이 틀 안에 보여 줘서 음악 플레이어가 그대로 이어지게 한다.
       (주소를 직접 열거나 검색엔진이 들어오면 예전처럼 서버가 통째로 그린 페이지가 나간다) -->
  <div class="info-frame">
    <iframe ref="fr" :src="src" title="정보" class="block w-full border-0" :style="{ height: h + 'px' }" @load="onLoad"></iframe>
  </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const fr = ref(null)
const h = ref(700)
let ro = null

// 서버에는 ?embed=1 을 붙여 요청 — 머리말·꼬리말(사이트와 중복)과 분석/광고 코드를 뺀 본문만 내려온다
const src = computed(() => {
  const p = new URLSearchParams()
  Object.entries(route.query).forEach(([k, v]) => { if (k !== 'embed') p.append(k, Array.isArray(v) ? v[0] : v) })
  p.set('embed', '1')
  return route.path + '?' + p.toString()
})

function go(url) {
  // 사이트 안 주소는 라우터로(음악이 이어지게), 라우터가 모르는 서버 주소는 일반 이동
  const r = router.resolve(url.pathname + url.search + url.hash)
  const known = r.matched.some(m => m.path !== '/:pathMatch(.*)*')
  if (known) router.push(r.fullPath)
  else window.location.assign(url.href)
}

function onClick(e) {
  if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return
  const a = e.target.closest && e.target.closest('a[href]')
  if (!a || a.hasAttribute('download')) return
  const raw = a.getAttribute('href')
  if (!raw || raw.startsWith('#') || /^(mailto:|tel:|javascript:)/i.test(raw)) return
  const url = new URL(a.href, window.location.href)
  if (a.target === '_blank') return
  e.preventDefault()
  if (url.origin !== window.location.origin) { window.open(url.href, '_blank', 'noopener'); return }
  go(url)
}

function onSubmit(e) {
  const f = e.target
  if (!f || (f.method || 'get').toLowerCase() !== 'get') return
  e.preventDefault()
  const url = new URL(f.getAttribute('action') || window.location.pathname, window.location.href)
  const q = new URLSearchParams(new FormData(f))
  const clean = new URLSearchParams()
  q.forEach((v, k) => { if (v !== '' && k !== 'embed') clean.append(k, v) })
  url.search = clean.toString()
  go(url)
}

function onLoad() {
  const doc = fr.value?.contentDocument
  if (!doc) return
  if (doc.title) document.title = doc.title
  doc.addEventListener('click', onClick)
  doc.addEventListener('submit', onSubmit)
  const measure = () => { const v = Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0); if (v) h.value = v }
  measure()
  ro?.disconnect()
  if (typeof ResizeObserver !== 'undefined') { ro = new ResizeObserver(measure); ro.observe(doc.documentElement); if (doc.body) ro.observe(doc.body) }
  // 이미지가 늦게 뜨며 길이가 달라지는 경우
  doc.querySelectorAll('img').forEach(i => { if (!i.complete) i.addEventListener('load', measure, { once: true }) })
}

onBeforeUnmount(() => { ro?.disconnect() })
</script>
