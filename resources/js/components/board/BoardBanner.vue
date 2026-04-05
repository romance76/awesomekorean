<template>
  <div :class="['rounded-2xl p-5 mb-4', theme==='orange' ? 'bg-gradient-to-r from-orange-500 to-amber-500' : 'bg-gradient-to-r from-blue-600 to-indigo-600']">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-3xl">{{ emoji }}</span>
        <div class="text-white">
          <h1 class="text-xl font-bold">{{ title }}</h1>
          <p class="text-sm opacity-80">{{ subtitle }}
            <span v-if="count!==null" class="ml-1 bg-white/20 px-2 py-0.5 rounded-full text-xs">{{ locationDisplay }} {{ count.toLocaleString() }}건</span>
          </p>
        </div>
      </div>
      <button v-if="registerLabel" @click="$emit('register')"
        class="bg-white/20 backdrop-blur text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/30 transition hidden sm:block">{{ registerLabel }}</button>
    </div>
  </div>
</template>

<script setup>
defineProps({ emoji: String, title: String, subtitle: String, registerLabel: String, count: { type: Number, default: null }, locationDisplay: { type: String, default: '' }, theme: { type: String, default: 'blue' } })
defineEmits(['register'])
</script>
