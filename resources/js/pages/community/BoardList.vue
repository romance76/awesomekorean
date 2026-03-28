<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <h1 class="text-xl font-bold text-gray-800 mb-6">커뮤니티 게시판</h1>

    <div v-for="(group, category) in grouped" :key="category" class="mb-6">
      <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3 px-1">
        {{ categoryLabel(category) }}
      </h2>
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <router-link
          v-for="board in group" :key="board.id"
          :to="`/community/${board.slug}`"
          class="flex items-center justify-between px-5 py-3.5 border-b border-gray-50 last:border-0 hover:bg-gray-50 transition">
          <div class="flex items-center space-x-3">
            <span class="text-xl">{{ board.icon || '📋' }}</span>
            <div>
              <p class="text-gray-800 font-medium text-sm">{{ board.name }}</p>
              <p class="text-gray-400 text-xs">{{ board.description }}</p>
            </div>
          </div>
          <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const boards = ref([]);

const grouped = computed(() => {
  const g = {};
  boards.value.forEach(b => {
    const cat = b.category || 'community';
    if (!g[cat]) g[cat] = [];
    g[cat].push(b);
  });
  return g;
});

function categoryLabel(cat) {
  return { community: '커뮤니티', local: '지역별', expert: '전문가 칼럼' }[cat] || cat;
}

onMounted(async () => {
  const { data } = await axios.get('/api/boards');
  boards.value = data;
});
</script>
