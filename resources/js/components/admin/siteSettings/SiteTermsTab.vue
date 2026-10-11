<template>
  <div class="card p-6">
    <h2 class="text-base font-semibold text-ink mb-5">약관 관리</h2>

    <!-- Sub tabs -->
    <div class="flex gap-1 mb-6 border-b border-gray-200">
      <button
        v-for="st in termsTabs"
        :key="st.key"
        @click="activeTermsTab = st.key"
        :class="[
          'px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
          activeTermsTab === st.key
            ? 'border-amber-500 text-amber-700'
            : 'border-transparent text-ink-muted hover:text-ink-light'
        ]"
      >
        {{ st.label }}
      </button>
    </div>

    <!-- v-show 대신 v-if 사용 — v-for 안에서 v-show로 탭을 토글하면
         버튼 쪽 활성 표시(activeTermsTab 비교)는 제대로 바뀌는데도
         이 div의 실제 표시 여부(el.style.display)는 최초 렌더 상태에
         그대로 고정돼버려서, 탭을 바꿔도 항상 처음 탭(이용약관)
         내용만 보이고 거기에 입력한 게 다른 탭에도 그대로 반영되는
         것처럼 보이던 버그였음(실제로는 두 탭 다 같은 div가 계속
         보이고 있었을 뿐). template로 감싸고 v-if로 바꿔서 탭 전환마다
         해당 div를 새로 만들도록 함. -->
    <template v-for="st in termsTabs" :key="st.key">
    <div v-if="activeTermsTab === st.key">
      <!-- Toolbar -->
      <div class="flex items-center gap-1 mb-2 p-1 bg-gray-50 border border-gray-100 rounded-xl">
        <button @click="formatText(st.key, 'bold')" class="toolbar-btn font-bold">B</button>
        <button @click="formatText(st.key, 'italic')" class="toolbar-btn italic">I</button>
        <div class="w-px h-5 bg-gray-300 mx-1"></div>
        <button @click="formatText(st.key, 'h2')" class="toolbar-btn">H2</button>
        <button @click="formatText(st.key, 'h3')" class="toolbar-btn">H3</button>
        <div class="w-px h-5 bg-gray-300 mx-1"></div>
        <button @click="formatText(st.key, 'list')" class="toolbar-btn">&#8801;</button>
        <button @click="formatText(st.key, 'link')" class="toolbar-btn">&#128279;</button>
        <div class="ml-auto text-xs text-ink-faint">{{ termsContent[st.key]?.length || 0 }}자</div>
      </div>

      <textarea
        :ref="el => termsRef[st.key] = el"
        v-model="termsContent[st.key]"
        rows="16"
        class="input-field resize-none font-mono text-sm"
        :placeholder="`${st.label} 내용을 입력하세요...`"
      ></textarea>

      <div class="flex items-center justify-between mt-3">
        <span class="text-xs text-ink-faint">
          마지막 수정: {{ termsLastUpdated[st.key] || '미정' }}
        </span>
        <button @click="saveTerms(st.key)" :disabled="saving" class="btn-primary">
          <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
          저장하기
        </button>
      </div>

      <!-- Version History -->
      <div class="mt-6 border-t border-gray-100 pt-5">
        <h3 class="text-sm font-semibold text-ink-light mb-3">버전 히스토리</h3>
        <div v-if="termsHistory[st.key]?.length" class="space-y-2">
          <div
            v-for="(version, vi) in termsHistory[st.key]"
            :key="vi"
            class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg"
          >
            <div>
              <span class="text-sm text-ink-light font-medium">v{{ version.version }}</span>
              <span class="text-xs text-ink-faint ml-2">{{ version.date }}</span>
              <span class="text-xs text-ink-muted ml-2">{{ version.summary }}</span>
            </div>
            <button @click="restoreVersion(st.key, version)" class="btn-soft px-3 py-1 text-xs">
              복원
            </button>
          </div>
        </div>
        <div v-else class="text-sm text-ink-muted py-4 text-center">버전 히스토리가 없습니다.</div>
      </div>
    </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'

// 약관 관리 탭 — 내용·이력은 SiteSettings.vue 가 불러오고 저장한다. 여기서는 편집 화면과 서식 버튼만 맡는다.
const props = defineProps({
  termsContent: { type: Object, required: true },
  termsLastUpdated: { type: Object, required: true },
  termsHistory: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'toast'])
const termsContent = props.termsContent
const termsLastUpdated = props.termsLastUpdated
const termsHistory = props.termsHistory
const saveTerms = (type) => emit('save', type)
const showToast = (message, type = 'success') => emit('toast', message, type)

const activeTermsTab = ref('terms')
const termsTabs = [
  { key: 'terms', label: '이용약관' },
  { key: 'privacy', label: '개인정보처리방침' },
]
const termsRef = reactive({ terms: null, privacy: null })

// ─── Terms helpers ────────────────────────────────────────────────────────────
function formatText(type, format) {
  const el = termsRef[type]
  if (!el) return
  const start = el.selectionStart
  const end = el.selectionEnd
  const selected = termsContent[type].substring(start, end)
  let replacement = selected

  switch (format) {
    case 'bold': replacement = `**${selected}**`; break
    case 'italic': replacement = `*${selected}*`; break
    case 'h2': replacement = `\n## ${selected || '제목'}`; break
    case 'h3': replacement = `\n### ${selected || '소제목'}`; break
    case 'list': replacement = `\n- ${selected || '항목'}`; break
    case 'link': {
      const url = window.prompt('URL을 입력하세요:', 'https://')
      if (url) replacement = `[${selected || '링크 텍스트'}](${url})`
      break
    }
  }

  termsContent[type] =
    termsContent[type].substring(0, start) +
    replacement +
    termsContent[type].substring(end)
}

function restoreVersion(type, version) {
  if (confirm(`v${version.version} (${version.date}) 버전으로 복원하시겠습니까?`)) {
    // In production, would fetch version content from API
    showToast(`v${version.version} 버전으로 복원 요청되었습니다.`)
  }
}
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}

.toolbar-btn {
  @apply px-2.5 py-1 text-sm text-ink-light hover:bg-gray-200 rounded-lg font-medium transition-colors;
}
</style>
