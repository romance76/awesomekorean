<template>
  <!-- 직접 쓴 이력서를 레터지 한 장 모양으로 보여 준다 (화면 미리보기 · 인쇄 공용) -->
  <div class="rp-page" ref="pageEl">
    <header class="rp-head">
      <h1 class="rp-name">{{ data.name || '이름' }}</h1>
      <p v-if="data.title" class="rp-title">{{ data.title }}</p>
      <div class="rp-contact">
        <span v-if="data.phone">{{ data.phone }}</span>
        <span v-if="data.email">{{ data.email }}</span>
        <span v-if="place">{{ place }}</span>
      </div>
    </header>

    <section v-if="data.summary" class="rp-sec"><h3>자기소개</h3><p class="rp-text">{{ data.summary }}</p></section>

    <section v-if="hasWish" class="rp-sec">
      <h3>희망 조건</h3>
      <dl class="rp-kv">
        <template v-if="catLabel"><dt>희망 직종</dt><dd>{{ catLabel }}</dd></template>
        <template v-if="typeLabel"><dt>근무 형태</dt><dd>{{ typeLabel }}</dd></template>
        <template v-if="data.desired_pay"><dt>희망 급여</dt><dd>{{ data.desired_pay }}</dd></template>
      </dl>
    </section>

    <section v-if="data.experience" class="rp-sec"><h3>경력</h3><p class="rp-text">{{ data.experience }}</p></section>
    <section v-if="data.education" class="rp-sec"><h3>학력</h3><p class="rp-text">{{ data.education }}</p></section>
    <section v-if="data.skills" class="rp-sec"><h3>보유 기술</h3><p class="rp-text">{{ data.skills }}</p></section>
    <section v-if="data.certifications" class="rp-sec"><h3>자격증</h3><p class="rp-text">{{ data.certifications }}</p></section>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { RESUME_CATEGORIES, RESUME_TYPES } from './resumeOptions'

const props = defineProps({ data: { type: Object, required: true } })
const pageEl = ref(null)
defineExpose({ pageEl })

const place = computed(() => [props.data.city, props.data.state].filter(Boolean).join(', '))
const catLabel = computed(() => RESUME_CATEGORIES.find(c => c.value === props.data.category)?.label || '')
const typeLabel = computed(() => RESUME_TYPES.find(c => c.value === props.data.desired_type)?.label || '')
const hasWish = computed(() => !!(catLabel.value || typeLabel.value || props.data.desired_pay))
</script>
