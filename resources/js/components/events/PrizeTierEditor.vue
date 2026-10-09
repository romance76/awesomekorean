<template>
<div class="space-y-2">
  <div v-for="(t, i) in tiers" :key="t.rank" class="border border-amber-100 rounded-xl bg-white p-3">
    <div class="flex items-center gap-2 mb-2">
      <span class="w-7 h-7 rounded-full bg-amber-400 text-white text-xs font-black grid place-items-center flex-shrink-0">{{ t.rank }}</span>
      <span class="text-sm font-bold text-ink">{{ t.rank }}등</span>
      <button v-if="i > 0 && !disabled" type="button" class="ml-auto text-[11px] font-bold text-amber-700 bg-amber-50 rounded-lg px-2 py-1" @click="copyPrev(i)">윗 등수 복사</button>
    </div>
    <div class="flex gap-3">
      <div class="w-20 flex-shrink-0">
        <ImageUploadBox mode="upload" kind="prize" aspect="1 / 1" compact :preview="t.prize_image" :disabled="disabled"
          :recommend-ratio="1" :max-m-b="5" :soft-m-b="1"
          @uploaded="u => setField(i, 'prize_image', u)" @remove="setField(i, 'prize_image', '')" />
      </div>
      <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-3 gap-2">
        <div class="sm:col-span-2">
          <input :value="t.prize_name" @input="setField(i, 'prize_name', $event.target.value)" type="text" maxlength="100" :disabled="disabled"
            :placeholder="`${t.rank}등 상품명 *`" class="input-soft px-3 disabled:opacity-60" :class="!t.prize_name && showErrors ? '!border-red-400' : ''" />
        </div>
        <div>
          <input :value="t.prize_value" @input="setField(i, 'prize_value', $event.target.value)" type="number" min="0" step="0.01" :disabled="disabled"
            placeholder="가치 ($)" class="input-soft px-3 disabled:opacity-60" />
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import ImageUploadBox from './ImageUploadBox.vue'

const props = defineProps({
  tiers: { type: Array, required: true },       // [{rank, prize_name, prize_value, prize_image}]
  disabled: { type: Boolean, default: false },
  showErrors: { type: Boolean, default: false },
})
const emit = defineEmits(['update:tiers'])

function setField(i, k, v) {
  const next = props.tiers.map((t, idx) => idx === i ? { ...t, [k]: v } : t)
  emit('update:tiers', next)
}
function copyPrev(i) {
  const p = props.tiers[i - 1]
  const next = props.tiers.map((t, idx) => idx === i ? { ...t, prize_name: p.prize_name, prize_value: p.prize_value, prize_image: p.prize_image } : t)
  emit('update:tiers', next)
}
</script>
