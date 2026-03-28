<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <h1 class="text-xl font-bold text-gray-800 mb-6">관리자 대시보드</h1>

    <!-- 통계 카드 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="stat in statCards" :key="stat.label"
        class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-gray-800">{{ stat.value?.toLocaleString() }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ stat.label }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- 신고 목록 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
          <h2 class="font-semibold text-gray-800">미처리 신고</h2>
          <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded font-medium">{{ reports.length }}건</span>
        </div>
        <div v-if="reports.length === 0" class="text-center py-8 text-gray-400 text-sm">미처리 신고가 없습니다.</div>
        <ul v-else>
          <li v-for="r in reports" :key="r.id" class="px-5 py-3 border-b border-gray-50 last:border-0">
            <div class="flex items-center justify-between">
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-700 truncate">
                  <span class="font-medium">{{ r.reporter?.name }}</span>
                  <span class="text-gray-400"> → {{ r.reason }}</span>
                </p>
                <p class="text-xs text-gray-400">{{ formatDate(r.created_at) }}</p>
              </div>
              <button @click="dismissReport(r)" class="ml-3 text-xs bg-gray-100 px-3 py-1 rounded hover:bg-gray-200 text-gray-600">처리</button>
            </div>
          </li>
        </ul>
      </div>

      <!-- 회원 목록 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-semibold text-gray-800">최근 가입 회원</h2>
          <input v-model="userSearch" @keyup.enter="searchUsers" type="text" placeholder="이름/이메일 검색"
            class="border border-gray-200 rounded-lg px-3 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>
        <ul>
          <li v-for="user in users" :key="user.id" class="px-5 py-3 border-b border-gray-50 last:border-0">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-800">{{ user.name }} <span class="text-gray-400 text-xs">@{{ user.username }}</span></p>
                <p class="text-xs text-gray-400">{{ user.email }} · {{ user.level }}</p>
              </div>
              <div class="flex items-center space-x-2">
                <span :class="['text-xs px-2 py-0.5 rounded font-medium', user.status === 'active' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600']">
                  {{ user.status === 'active' ? '활성' : '정지' }}
                </span>
                <button v-if="user.status === 'active' && !user.is_admin" @click="banUser(user)"
                  class="text-xs text-red-400 hover:text-red-600">정지</button>
                <button v-else-if="user.status !== 'active'" @click="unbanUser(user)"
                  class="text-xs text-green-500 hover:text-green-700">해제</button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const stats = ref({});
const reports = ref([]);
const users = ref([]);
const userSearch = ref('');

const statCards = computed(() => [
  { label: '전체 회원', value: stats.value.users },
  { label: '게시글', value: stats.value.posts },
  { label: '오늘 가입', value: stats.value.new_users_today },
  { label: '미처리 신고', value: stats.value.reports },
]);

async function searchUsers() {
  const { data } = await axios.get('/api/admin/users', { params: { search: userSearch.value } });
  users.value = data.data;
}

async function banUser(user) {
  if (!confirm(`${user.name} 계정을 정지하시겠습니까?`)) return;
  await axios.post(`/api/admin/users/${user.id}/ban`);
  user.status = 'banned';
}

async function unbanUser(user) {
  await axios.post(`/api/admin/users/${user.id}/unban`);
  user.status = 'active';
}

async function dismissReport(report) {
  await axios.post(`/api/admin/reports/${report.id}/dismiss`);
  reports.value = reports.value.filter(r => r.id !== report.id);
  stats.value.reports--;
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('ko-KR');
}

onMounted(async () => {
  const [statsRes, reportsRes, usersRes] = await Promise.all([
    axios.get('/api/admin/stats'),
    axios.get('/api/admin/reports'),
    axios.get('/api/admin/users'),
  ]);
  stats.value = statsRes.data;
  reports.value = reportsRes.data.data || [];
  users.value = usersRes.data.data || [];
});
</script>
