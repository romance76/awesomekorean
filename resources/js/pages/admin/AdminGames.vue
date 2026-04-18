<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-gray-500">관리자 › 서비스 › 게임</div>
    <h1 class="text-2xl font-black text-gray-800 mt-1">🎮 게임 관리</h1>
    <p class="text-xs text-gray-400 mt-0.5">플레이 가능한 게임 카테고리 관리 (고스톱/포커 등은 전용 페이지에서)</p>
  </div>

  <div class="flex gap-2 mb-3 flex-wrap">
    <RouterLink to="/admin/poker" class="bg-white border rounded-lg px-4 py-2 text-sm hover:bg-amber-50 hover:border-amber-300 transition">♠️ 포커 상세</RouterLink>
    <RouterLink to="/admin/point-settings" class="bg-white border rounded-lg px-4 py-2 text-sm hover:bg-amber-50 hover:border-amber-300 transition">💰 게임 포인트 설정</RouterLink>
  </div>

  <AdminListView icon="🎮" title="" api-url="/api/game-categories"
    :extra-cols='[{"key":"slug","label":"Slug"},{"key":"is_active","label":"활성"}]'
    @open-user="u => { selectedUserId = u?.id; showUser = true }" />
  <AdminUserModal :show="showUser" :user-id="selectedUserId" @close="showUser=false" />
</div>
</template>
<script setup>
import { ref } from 'vue'
import AdminListView from '../../components/AdminListView.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'
const showUser = ref(false)
const selectedUserId = ref(null)
</script>
