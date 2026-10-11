<!-- 관리자 공용 표/카드 전환 목록 (할 일 #83) — PC 는 표, 휴대폰은 카드로 같은 데이터를 보여 준다.
     표 크기 기준은 Revenue.vue(매출/결제 현황): 글자 13px, 줄마다 넉넉한 여백, 윗줄 메인+아랫줄 보조 두 줄 칸.
     사용법:
       <AdmTable :rows="reports" :columns="[{key:'status',label:'상태'},{key:'type',label:'유형'}]" row-key="id" @row-click="openDetail">
         <template #cell-status="{ row }">...커스텀 셀...</template>
         <template #mobile-card="{ row }">...커스텀 카드...</template>
       </AdmTable>
     columns 를 안 쓰고 완전히 커스텀으로 그리고 싶으면 #cell-<key> 슬롯만 비워 두고 기본(row[key]) 출력을 그대로 써도 된다. -->
<template>
<div>
  <!-- PC: 표 -->
  <div class="hidden sm:block overflow-x-auto">
    <table class="w-full text-[13px]">
      <thead class="bg-gray-50 text-ink-muted text-left border-b border-gray-100">
        <tr>
          <th v-for="c in columns" :key="c.key" class="px-3 py-2.5 font-semibold whitespace-nowrap" :class="c.class">{{ c.label }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <tr v-for="row in rows" :key="row[rowKey]" class="hover:bg-amber-50/40 transition-colors" :class="clickable ? 'cursor-pointer' : ''" @click="clickable && $emit('row-click', row)">
          <td v-for="c in columns" :key="c.key" class="px-3 py-3" :class="c.class">
            <slot :name="`cell-${c.key}`" :row="row" :value="row[c.key]">{{ row[c.key] }}</slot>
          </td>
        </tr>
      </tbody>
    </table>
    <div v-if="!rows.length" class="px-4 py-10 text-center text-[14px] text-ink-muted">{{ loading ? '불러오는 중…' : (emptyText || '데이터가 없어요') }}</div>
  </div>

  <!-- 휴대폰: 카드 -->
  <div class="sm:hidden space-y-2">
    <div v-for="row in rows" :key="row[rowKey]"
      class="bg-white border border-gray-100 rounded-2xl p-3.5" :class="clickable ? 'active:bg-gray-50' : ''"
      @click="clickable && $emit('row-click', row)">
      <slot name="mobile-card" :row="row">
        <!-- 커스텀 카드 슬롯이 없으면 컬럼을 라벨:값 으로 그냥 쌓아서 보여 줌(최소 동작 보장용 기본값) -->
        <div v-for="c in columns" :key="c.key" class="flex justify-between gap-2 py-1 text-[14px]">
          <span class="text-ink-muted">{{ c.label }}</span>
          <span class="text-ink font-medium text-right"><slot :name="`cell-${c.key}`" :row="row" :value="row[c.key]">{{ row[c.key] }}</slot></span>
        </div>
      </slot>
    </div>
    <div v-if="!rows.length" class="text-center text-ink-muted py-12 text-[15px]">{{ loading ? '불러오는 중…' : (emptyText || '데이터가 없어요') }}</div>
  </div>
</div>
</template>
<script setup>
defineProps({
  rows: { type: Array, default: () => [] },
  columns: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  loading: { type: Boolean, default: false },
  emptyText: { type: String, default: '' },
  clickable: { type: Boolean, default: false },
})
defineEmits(['row-click'])
</script>
