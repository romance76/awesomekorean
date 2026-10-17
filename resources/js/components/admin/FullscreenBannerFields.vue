<!-- 전체창 배너 문구 입력 (관리자 › 팝업 배너). v-model = banner.extra 객체. 화면 표시는 FullscreenBanner.vue -->
<template>
<div class="space-y-3">
  <!-- 그림형: 완성된 그림을 올리면 그 그림을 화면 가득 보여 주고, 그림 위의 버튼 자리에 눌리는 영역을 얹어요 (아래 글자 칸은 그림이 없거나 못 불러올 때 대신 보여 줘요) -->
  <div class="rounded-xl border border-pink-200 bg-pink-50/60 p-3 space-y-3">
    <div class="text-[13px] font-bold text-pink-700">🖼️ 그림형 (완성된 그림으로 보여 주기)</div>
    <div v-for="k in IMG_KEYS" :key="k.key" class="space-y-1">
      <span class="block text-[12px] text-ink-muted">{{ k.label }} <span class="text-ink-faint">{{ k.hint }}</span></span>
      <div class="flex gap-2">
        <input v-model="x[k.key]" maxlength="500" placeholder="/images/banners/… 또는 https://…" class="flex-1 min-w-0 min-h-[44px] rounded-xl border border-gray-200 px-3 bg-white text-[13px]" />
        <label class="shrink-0 min-h-[44px] px-3 rounded-xl bg-white border border-pink-300 text-pink-600 text-[13px] font-bold flex items-center cursor-pointer">
          {{ uploading === k.key ? '올리는 중…' : '올리기' }}
          <input type="file" accept="image/*" class="hidden" @change="e => upload(k.key, e)" />
        </label>
      </div>
      <img v-if="x[k.key]" :src="x[k.key]" alt="" class="max-h-28 rounded-lg border border-gray-200" />
    </div>
    <details class="text-[12px]">
      <summary class="cursor-pointer text-ink-muted select-none">눌리는 영역 설정 (고급)</summary>
      <div v-for="k in SPOT_KEYS" :key="k.key" class="mt-2">
        <span class="block text-ink-muted mb-1">{{ k.label }}</span>
        <textarea v-model="spotText[k.key]" rows="5" @blur="applySpots(k.key)" class="w-full rounded-xl border border-gray-200 px-3 py-2 bg-white font-mono text-[11px]"></textarea>
        <span v-if="spotErr[k.key]" class="block text-red-500 mt-0.5">{{ spotErr[k.key] }}</span>
      </div>
      <p class="mt-2 text-ink-faint leading-relaxed">종류: close(닫기) · cta(버튼) · link(이동) · check(오늘 하루 열지 않음). x·y·w·h는 그림 크기에 대한 %예요. 주소 끝에 ?bannerdebug=1 을 붙여 열면 눌리는 영역이 파란 점선으로 보여요.</p>
    </details>
  </div>

  <label class="block">
    <span class="block text-[12px] text-ink-muted mb-1">작은 글씨 (맨 위 알약) <span class="text-ink-faint">예: SOFT OPEN</span></span>
    <input v-model="x.eyebrow" maxlength="40" class="w-full min-h-[44px] rounded-xl border border-gray-200 px-3 bg-white" />
  </label>
  <label class="block">
    <span class="block text-[12px] text-ink-muted mb-1">큰 제목 <span class="text-red-500">*</span></span>
    <input v-model="x.headline" maxlength="80" placeholder="예: 어썸코리안이 문을 열었어요!" class="w-full min-h-[44px] rounded-xl border border-gray-200 px-3 bg-white" />
  </label>
  <label class="block">
    <span class="block text-[12px] text-ink-muted mb-1">부제 (설명)</span>
    <textarea v-model="x.subtitle" rows="3" maxlength="300" class="w-full rounded-xl border border-gray-200 px-3 py-2 bg-white"></textarea>
  </label>
  <label class="flex items-start gap-2 text-[14px] text-ink">
    <input v-model="x.show_open_event" type="checkbox" class="mt-1 w-5 h-5 accent-pink-500" />
    <span>오픈 기념 이벤트 혜택 보여 주기 <span class="block text-[12px] text-ink-muted">이벤트 설정(포인트 2배·무료·할인 등)을 실시간으로 가져와요. 이벤트가 끝나면 자동으로 사라져요.</span></span>
  </label>
  <label class="block">
    <span class="block text-[12px] text-ink-muted mb-1">둘러보기 제목</span>
    <input v-model="x.features_title" maxlength="40" placeholder="예: 이렇게 써 보세요" class="w-full min-h-[44px] rounded-xl border border-gray-200 px-3 bg-white" />
  </label>
  <label class="block">
    <span class="block text-[12px] text-ink-muted mb-1">둘러보기 목록 — 한 줄에 하나: <b>아이콘|이름|이동 주소</b></span>
    <textarea v-model="x.features_text" rows="6" maxlength="900" placeholder="🛒|중고장터|/market" class="w-full rounded-xl border border-gray-200 px-3 py-2 bg-white font-mono text-[13px]"></textarea>
    <span class="block text-[11px] text-ink-faint mt-1">이동 주소는 / 로 시작하는 사이트 안 경로(예: /market)나 https:// 주소. 비우면 누를 수 없는 칸이에요. 최대 12개.</span>
  </label>
  <label class="block">
    <span class="block text-[12px] text-ink-muted mb-1">안내문 (아래쪽 작은 글, "문의하기" 링크가 자동으로 붙어요)</span>
    <textarea v-model="x.note" rows="2" maxlength="300" class="w-full rounded-xl border border-gray-200 px-3 py-2 bg-white"></textarea>
  </label>
  <div class="grid grid-cols-2 gap-2">
    <label class="block">
      <span class="block text-[12px] text-ink-muted mb-1">버튼 글자</span>
      <input v-model="x.cta_label" maxlength="20" placeholder="둘러보기" class="w-full min-h-[44px] rounded-xl border border-gray-200 px-3 bg-white" />
    </label>
    <label class="block">
      <span class="block text-[12px] text-ink-muted mb-1">버튼 이동 주소 (비우면 그냥 닫기)</span>
      <input v-model="x.cta_link" maxlength="500" placeholder="/events" class="w-full min-h-[44px] rounded-xl border border-gray-200 px-3 bg-white" />
    </label>
  </div>
</div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({ modelValue: { type: Object, default: () => ({}) } })
const emit = defineEmits(['update:modelValue'])

// 부모 값을 복사해 편집하고, 바뀔 때마다 부모에 돌려준다
const x = reactive({
  eyebrow: '', headline: '', subtitle: '', show_open_event: false, features_title: '', features_text: '', note: '', cta_label: '', cta_link: '',
  img_desktop: '', img_mobile: '', hotspots_desktop: [], hotspots_mobile: [],
  ...(props.modelValue || {}),
})
watch(x, () => emit('update:modelValue', { ...x }), { deep: true })
watch(() => props.modelValue, v => { if (v && JSON.stringify(v) !== JSON.stringify({ ...x })) { Object.assign(x, v); syncSpotText() } })

const IMG_KEYS = [
  { key: 'img_desktop', label: 'PC 그림', hint: '(가로 16:9, 예: 1920×1080)' },
  { key: 'img_mobile', label: '휴대폰 그림', hint: '(세로 9:16, 예: 1080×1920)' },
]
const SPOT_KEYS = [
  { key: 'hotspots_desktop', label: 'PC 그림의 눌리는 영역(JSON)' },
  { key: 'hotspots_mobile', label: '휴대폰 그림의 눌리는 영역(JSON)' },
]

// 눌리는 영역은 JSON 글자로 편집한다(잘못된 JSON 이면 적용하지 않고 알려 준다)
const spotText = reactive({ hotspots_desktop: '', hotspots_mobile: '' })
const spotErr = reactive({ hotspots_desktop: '', hotspots_mobile: '' })
function syncSpotText() {
  for (const k of ['hotspots_desktop', 'hotspots_mobile']) spotText[k] = JSON.stringify(x[k] || [], null, 1)
}
syncSpotText()
function applySpots(key) {
  try {
    const v = JSON.parse(spotText[key] || '[]')
    if (!Array.isArray(v)) throw new Error('목록([...]) 형태여야 해요')
    x[key] = v
    spotErr[key] = ''
  } catch (e) {
    spotErr[key] = 'JSON 형식이 맞지 않아요: ' + e.message
  }
}

// 그림 올리기 — 올리면 주소가 칸에 들어간다
const uploading = ref('')
async function upload(key, e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  uploading.value = key
  try {
    const fd = new FormData()
    fd.append('image', file)
    const { data } = await axios.post('/api/admin/popup-banners/image', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    x[key] = data.url
  } catch (err) {
    alert('그림을 올리지 못했어요: ' + (err.response?.data?.message || err.message))
  }
  uploading.value = ''
}
</script>
