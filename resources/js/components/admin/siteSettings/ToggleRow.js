// 켜기/끄기 한 줄 (설명 + 스위치). SiteSettings.vue 안에 있던 것을 여러 탭이 같이 쓰도록 분리 — 내용은 그대로.
export default {
  // pending: 아직 사이트에 연결되지 않은 스위치 — 눌러도 아무 일도 없으니 잠그고 "아직 연결 안 됨" 표시
  props: ['modelValue', 'label', 'desc', 'pending'],
  emits: ['update:modelValue'],
  template: `
    <div class="flex items-center justify-between gap-3" :class="pending ? 'opacity-60' : ''">
      <div class="min-w-0">
        <p class="text-sm font-medium text-gray-700">{{ label }}
          <span v-if="pending" class="ml-1.5 text-[11px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 align-middle">아직 연결 안 됨</span>
        </p>
        <p v-if="desc" class="text-xs text-gray-400 leading-relaxed">{{ desc }}</p>
      </div>
      <button
        type="button"
        :disabled="!!pending"
        :aria-pressed="!!modelValue"
        :aria-label="label"
        @click="$emit('update:modelValue', !modelValue)"
        :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors flex-shrink-0',
          pending ? 'cursor-not-allowed' : '',
          modelValue ? 'bg-blue-500' : 'bg-gray-200']"
      >
        <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
          modelValue ? 'translate-x-6' : 'translate-x-1']"></span>
      </button>
    </div>
  `
}
