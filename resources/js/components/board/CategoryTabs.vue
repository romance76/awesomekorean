<template>
  <div class="flex gap-1.5 mb-3 overflow-x-auto pb-1" style="scrollbar-width:none;">
    <button v-for="tab in tabs" :key="tab.value" @click="$emit('update:modelValue', tab.value)"
      :class="['px-3 py-1.5 rounded-full text-xs border whitespace-nowrap transition',
        modelValue===tab.value ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-400']">
      <span v-if="tab.emoji">{{ tab.emoji }} </span>{{ tab.label }}
    </button>
  </div>
</template>

<script setup>
defineProps({ tabs: Array, modelValue: String })
defineEmits(['update:modelValue'])
</script>
