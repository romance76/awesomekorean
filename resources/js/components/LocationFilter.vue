<template>
  <div class="flex items-center gap-2 text-sm">
    <span class="text-gray-500">📍</span>
    <select v-model="radius" @change="$emit('update:modelValue', radius)"
      class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-400 bg-white">
      <option :value="5">5마일</option>
      <option :value="10">10마일</option>
      <option :value="20">20마일</option>
      <option :value="30">30마일</option>
      <option :value="50">50마일</option>
      <option :value="100">100마일</option>
      <option :value="0">미국 전체</option>
    </select>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Number,
    default: 30,
  },
});

defineEmits(['update:modelValue']);

const radius = ref(props.modelValue);

watch(() => props.modelValue, (val) => {
  radius.value = val;
});
</script>
