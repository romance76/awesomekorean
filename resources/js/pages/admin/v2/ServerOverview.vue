<template>
  <!-- /admin/v2/server/overview (Phase 2-C 묶음 8) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">🖥️ 서버 개요</h2>
      <span v-if="data?.mock_mode" class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Mock 모드 (DO Token 미설정)</span>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 p-6 text-center">로딩 중...</div>
    <div v-else-if="data" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Droplet 정보 -->
      <div class="bg-white rounded-xl shadow-sm p-4 md:col-span-2">
        <h3 class="font-semibold mb-3">Droplet 정보</h3>
        <dl class="grid grid-cols-2 gap-2 text-sm">
          <dt class="text-gray-500">이름</dt><dd class="font-mono">{{ data.droplet.name }}</dd>
          <dt class="text-gray-500">IP</dt><dd class="font-mono">{{ data.droplet.networks?.v4?.[0]?.ip_address }}</dd>
          <dt class="text-gray-500">리전</dt><dd>{{ data.droplet.region?.name || data.droplet.region?.slug }}</dd>
          <dt class="text-gray-500">사이즈</dt><dd class="font-mono">{{ data.droplet.size_slug }}</dd>
          <dt class="text-gray-500">RAM</dt><dd>{{ data.droplet.memory }} MB</dd>
          <dt class="text-gray-500">vCPU</dt><dd>{{ data.droplet.vcpus }}</dd>
          <dt class="text-gray-500">Disk</dt><dd>{{ data.droplet.disk }} GB</dd>
          <dt class="text-gray-500">업타임</dt><dd>{{ data.uptime?.pretty }}</dd>
        </dl>
      </div>

      <!-- 서비스 상태 -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="font-semibold mb-3">서비스</h3>
        <ul class="space-y-2 text-sm">
          <li class="flex justify-between"><span>DB</span><span :class="data.services.database ? 'text-green-600' : 'text-red-600'">{{ data.services.database ? '✅' : '❌' }}</span></li>
          <li class="flex justify-between"><span>Redis</span><span :class="data.services.redis ? 'text-green-600' : 'text-red-600'">{{ data.services.redis ? '✅' : '❌' }}</span></li>
          <li class="flex justify-between"><span>PHP</span><span class="font-mono text-xs">{{ data.services.php }}</span></li>
          <li class="flex justify-between"><span>Laravel</span><span class="font-mono text-xs">{{ data.services.laravel }}</span></li>
        </ul>
      </div>

      <!-- 리소스 -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="font-semibold mb-3">💾 Disk</h3>
        <p class="text-2xl font-bold">{{ data.disk.used_pct }}%</p>
        <div class="w-full bg-gray-200 rounded-full h-2 my-2"><div class="bg-amber-400 h-2 rounded-full" :style="{width: data.disk.used_pct + '%'}"></div></div>
        <p class="text-xs text-gray-500">{{ (data.disk.total_gb - data.disk.free_gb).toFixed(1) }} / {{ data.disk.total_gb }} GB</p>
      </div>

      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="font-semibold mb-3">🧠 RAM</h3>
        <p class="text-2xl font-bold">{{ data.memory.used_pct }}%</p>
        <div class="w-full bg-gray-200 rounded-full h-2 my-2"><div class="bg-amber-400 h-2 rounded-full" :style="{width: data.memory.used_pct + '%'}"></div></div>
        <p class="text-xs text-gray-500">{{ data.memory.used_mb }} / {{ data.memory.total_mb }} MB</p>
      </div>

      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="font-semibold mb-3">⚙️ Load Average</h3>
        <p class="text-lg font-mono">{{ data.load[0]?.toFixed(2) }} / {{ data.load[1]?.toFixed(2) }} / {{ data.load[2]?.toFixed(2) }}</p>
        <p class="text-xs text-gray-500 mt-1">1m / 5m / 15m</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const data = ref(null)
const loading = ref(true)
onMounted(async () => {
  try {
    const res = await axios.get('/api/admin/server/overview')
    data.value = res.data.data
  } catch {} finally { loading.value = false }
})
</script>
