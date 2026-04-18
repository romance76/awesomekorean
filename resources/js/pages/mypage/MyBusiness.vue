<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">🏪 내 업소</h3>
      <router-link to="/directory" class="text-xs text-amber-600 hover:text-amber-800">업소록 →</router-link>
    </div>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">🏪</p>
      <p>등록된 업소가 없습니다.</p>
      <p class="text-xs mt-2 text-gray-400">업소 소유권을 주장하려면 업소록에서 클레임을 신청하세요.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="b in items" :key="b.id" class="py-3 flex items-center gap-3">
        <img :src="b.image_url || b.logo || b.photos?.[0] || '/images/placeholder.png'" @error="$event.target.src='/images/placeholder.png'" class="w-16 h-16 rounded object-cover bg-gray-100" />
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm truncate">{{ b.name }}</p>
          <p class="text-xs text-gray-500 truncate">{{ b.address }}</p>
          <p class="text-xs text-gray-400 mt-0.5">📞 {{ b.phone || '-' }} · ⭐ {{ b.rating?.toFixed(1) || '?' }}</p>
        </div>
        <router-link :to="`/directory/${b.id}`" class="text-xs px-2 py-1 bg-amber-100 text-amber-700 rounded hover:bg-amber-200">관리</router-link>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const items = ref([])
const loading = ref(true)
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/my-businesses').catch(() => ({ data: { data: [] } }))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
