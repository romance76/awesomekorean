<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-[70] bg-black/60 flex items-center justify-center p-3 overflow-y-auto" @click.self="close">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-auto" role="dialog" aria-label="폰 화면 광고 위치">
        <div class="flex items-center justify-between px-5 pt-4">
          <div>
            <div class="font-bold text-ink text-sm">📱 폰에서는 이렇게 보여요</div>
            <div class="text-[11px] text-ink-muted mt-0.5">폰에는 사이드바가 없어서 광고가 목록 중간에 가로 띠로 한 번 나옵니다.</div>
          </div>
          <button type="button" class="text-ink-muted hover:text-ink text-lg leading-none px-2" aria-label="닫기" @click="close">✕</button>
        </div>

        <div class="grid md:grid-cols-2 gap-5 p-5 items-start">
          <!-- 폰 모양 -->
          <div class="mx-auto">
            <div class="w-[300px] rounded-[28px] border-[6px] border-slate-800 bg-white overflow-hidden shadow-xl">
              <div class="h-5 bg-slate-800 flex justify-center items-end"><div class="w-16 h-1.5 bg-slate-600 rounded-full mb-1"></div></div>
              <div class="px-3 py-2 border-b border-gray-100 text-[11px] font-bold text-ink flex items-center gap-1">
                <span class="w-4 h-4 rounded bg-amber-100"></span>{{ pageLabel }}
              </div>
              <div class="p-3 space-y-2.5">
                <template v-for="n in 7" :key="n">
                  <div class="flex gap-2 items-center">
                    <div class="w-12 h-12 rounded-lg bg-gray-100 shrink-0"></div>
                    <div class="flex-1 space-y-1.5">
                      <div class="h-2.5 bg-gray-200 rounded w-4/5"></div>
                      <div class="h-2 bg-gray-100 rounded w-full"></div>
                      <div class="h-2 bg-gray-100 rounded w-2/5"></div>
                    </div>
                  </div>
                  <!-- 5번째 글 아래: 모바일 광고 띠 -->
                  <AdGuideBox v-if="n === 5" name="모바일 광고" icon="📱" tone="premium"
                    size-label="가로 가득 · 높이 80px 띠" :used="null" style="height:67px" />
                </template>
              </div>
            </div>
            <div class="text-center text-[10px] text-ink-faint mt-2">폰 화면 폭 360px 기준 (실제 비율로 축소 표시)</div>
          </div>

          <!-- 설명 -->
          <div class="space-y-4 text-xs text-ink-light">
            <div>
              <div class="font-bold text-ink mb-1">어디에 나오나요?</div>
              <p class="leading-relaxed">목록 <b>5번째 글 바로 아래</b>에 한 칸이 나옵니다. 신청한 프리미엄·스탠다드 광고가 돌아가며 보이고, 프리미엄 A/B는 각 35%, 스탠다드 A/B는 각 15% 확률로 노출됩니다.</p>
            </div>
            <div>
              <div class="font-bold text-ink mb-1">이미지는 어떻게 잘리나요?</div>
              <p class="leading-relaxed mb-2">폰에서는 이미지의 <b>가운데 가로 띠만</b> 보입니다. 위아래는 잘립니다. 글자나 로고는 가운데에 넣어 주세요.</p>
              <div class="flex items-start gap-3">
                <div class="relative w-[140px] shrink-0 rounded-md overflow-hidden border border-gray-300 bg-gradient-to-br from-amber-200 to-amber-300" style="aspect-ratio: 10 / 7">
                  <div class="absolute inset-x-0 top-0 bg-black/45" :style="{ height: band.edge + '%' }"></div>
                  <div class="absolute inset-x-0 bottom-0 bg-black/45" :style="{ height: band.edge + '%' }"></div>
                  <div class="absolute inset-0 flex items-center justify-center text-[10px] font-black text-slate-800">여기만 보임</div>
                </div>
                <ul class="space-y-1 leading-snug">
                  <li>업로드 비율 <b>10:7</b> (사이드바와 동일)</li>
                  <li>폰 표시: 폭 가득 × 높이 80px</li>
                  <li>보이는 범위: 이미지 높이의 약 <b>{{ band.pct }}%</b> (가운데)</li>
                </ul>
              </div>
            </div>
            <div class="flex gap-2 pt-1">
              <RouterLink :to="{ path: '/ad-apply', query: { page } }" @click="close"
                class="flex-1 text-center text-xs font-bold text-white bg-gradient-to-r from-[#FF8A4D] to-[#FC226B] rounded-lg py-2">광고 신청하기</RouterLink>
              <button type="button" @click="close" class="flex-1 text-center text-xs font-bold text-ink-light bg-gray-100 hover:bg-gray-200 rounded-lg py-2 transition-colors">닫기</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, watch, onBeforeUnmount } from 'vue'
import AdGuideBox from './AdGuideBox.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  page: { type: String, default: '' },
  pageLabel: { type: String, default: '목록' },
})
const emit = defineEmits(['update:modelValue'])

function close() { emit('update:modelValue', false) }

// 폭 360px 폰에서 10:7 이미지(높이 252px)에 80px 띠가 보이는 비율
const band = computed(() => {
  const imgH = 360 * 0.7
  const visible = 80 / imgH
  return { pct: Math.round(visible * 100), edge: ((1 - visible) / 2) * 100 }
})

function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close() } }
watch(() => props.modelValue, (v) => {
  if (v) window.addEventListener('keydown', onKey, true)
  else window.removeEventListener('keydown', onKey, true)
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKey, true))
</script>
