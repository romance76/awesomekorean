<template>
  <div class="w-12 h-12 rounded-xl mx-auto mb-3 shadow-btn overflow-hidden">
    <img v-if="!imgError" :src="site.logoUrl" alt="AwesomeKorean" class="w-full h-full object-contain bg-white" @error="imgError = true" />
    <div v-else class="w-full h-full bg-gradient-to-br from-[#FF8A4D] to-[#F0266B] flex items-center justify-center text-xl font-black text-white">AK</div>
  </div>
</template>
<script setup>
import { ref } from 'vue'
import { useSiteStore } from '../stores/site'
const site = useSiteStore()
const imgError = ref(false)
</script>
