<template>
  <div class="relative">
    <input v-bind="$attrs" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"
      :type="show ? 'text' : 'password'" class="!pr-10" />
    <button type="button" @click="show = !show" tabindex="-1"
      class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-ink-muted hover:text-ink transition-colors"
      :aria-label="show ? '비밀번호 숨기기' : '비밀번호 보기'" :title="show ? '비밀번호 숨기기' : '비밀번호 보기'">
      <AppIcon :name="show ? 'eye-off' : 'eye'" :size="17" />
    </button>
  </div>
</template>

<script setup>
// 비밀번호 입력 + 눈 아이콘(입력한 글자 보기/숨기기). class/placeholder/required 등은 input 으로 그대로 전달.
import { ref } from 'vue'
import AppIcon from './AppIcon.vue'

defineOptions({ inheritAttrs: false })
defineProps({ modelValue: { type: String, default: '' } })
defineEmits(['update:modelValue'])

const show = ref(false)
</script>
