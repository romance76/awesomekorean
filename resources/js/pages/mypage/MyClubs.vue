<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h3 class="font-bold mb-3">👥 내 동호회</h3>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">👥</p>
      <p>가입한 동호회가 없습니다.</p>
      <router-link to="/clubs" class="inline-block mt-3 px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold">동호회 둘러보기 →</router-link>
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <router-link v-for="c in items" :key="c.id" :to="`/clubs/${c.id}`" class="block border rounded-lg p-3 hover:shadow-md transition">
        <div class="flex items-start gap-2">
          <img :src="c.image_url || c.logo || '/images/placeholder.png'" @error="$event.target.src='/images/placeholder.png'" class="w-12 h-12 rounded object-cover bg-gray-100" />
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm truncate">{{ c.name || c.title }}</p>
            <p class="text-xs text-gray-500 truncate">{{ c.description }}</p>
            <p class="text-xs text-gray-400 mt-1">👥 {{ c.member_count || c.members_count || 0 }}명</p>
          </div>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const items = ref([])
const loading = ref(true)
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/clubs?mine=1&per_page=50').catch(() => ({ data: { data: [] } }))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
