<template>
<div class="min-h-screen bg-gray-100">
  <div class="max-w-3xl mx-auto px-4 py-6 space-y-5">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button @click="$router.back()" class="w-9 h-9 flex items-center justify-center rounded-full bg-white shadow-sm border border-gray-200 text-gray-500 hover:bg-gray-50">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <h1 class="text-xl font-black text-gray-800">{{ isEdit ? '공고 수정' : '공고 등록' }}</h1>
    </div>


    <!-- Section 1: 글 유형 -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100" :class="isHiring ? 'bg-amber-50' : 'bg-blue-50'">
        <h2 class="text-sm font-bold" :class="isHiring ? 'text-amber-800' : 'text-blue-800'">공고 유형</h2>
      </div>
      <div class="p-5">
        <div class="flex gap-3">
          <button type="button" @click="form.post_type='hiring'"
            class="flex-1 py-3 rounded-xl font-bold text-sm transition-all border-2 flex items-center justify-center gap-2"
            :class="isHiring ? 'bg-amber-400 border-amber-400 text-amber-900 shadow-md shadow-amber-200' : 'bg-white border-gray-200 text-gray-400 hover:border-amber-300'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            구인 (채용합니다)
          </button>
          <button type="button" @click="form.post_type='seeking'"
            class="flex-1 py-3 rounded-xl font-bold text-sm transition-all border-2 flex items-center justify-center gap-2"
            :class="!isHiring ? 'bg-blue-500 border-blue-500 text-white shadow-md shadow-blue-200' : 'bg-white border-gray-200 text-gray-400 hover:border-blue-300'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            구직 (일자리 찾습니다)
          </button>
        </div>
      </div>
    </section>

    <!-- Section 2: 기본 정보 -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100" :class="isHiring ? 'bg-amber-50' : 'bg-blue-50'">
        <h2 class="text-sm font-bold" :class="isHiring ? 'text-amber-800' : 'text-blue-800'">기본 정보</h2>
      </div>
      <div class="p-5 space-y-4">
        <!-- 로고 업로드 -->
        <div>
          <label class="text-sm font-semibold text-gray-700 block mb-2">로고 이미지 (선택)</label>
          <div class="flex items-center gap-4">
            <div class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
              <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" />
              <svg v-else class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
              <input ref="logoInputRef" type="file" accept="image/*" @change="onLogoSelect" class="hidden" />
              <button type="button" @click="logoInputRef?.click()"
                class="px-4 py-2 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                {{ logoFile || logoPreview ? '로고 변경' : '로고 선택' }}
              </button>
              <button v-if="logoFile || logoPreview" type="button" @click="clearLogo"
                class="ml-2 px-3 py-2 rounded-lg text-xs font-semibold bg-red-50 text-red-600 hover:bg-red-100 transition">
                제거
              </button>
              <p class="text-xs text-gray-400 mt-1">최대 5MB, JPG/PNG</p>
            </div>
          </div>
        </div>

        <!-- 제목 -->
        <div>
          <label class="text-sm font-semibold text-gray-700 block mb-1">
            제목 <span class="text-red-400">*</span>
          </label>
          <input v-model="form.title" type="text"
            :placeholder="isHiring ? '예: 한식당 주방보조 구합니다' : '예: 경력 3년 웹개발자 구직합니다'"
            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm transition outline-none"
            :class="focusRing" />
        </div>

        <!-- 회사명/이름 -->
        <div>
          <label class="text-sm font-semibold text-gray-700 block mb-1">
            {{ isHiring ? '회사명' : '이름 / 경력 (선택)' }}
            <span v-if="isHiring" class="text-red-400">*</span>
          </label>
          <input v-model="form.company" type="text"
            :placeholder="isHiring ? '예: OO 레스토랑' : '예: 홍길동 / 요식업 3년'"
            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm transition outline-none"
            :class="focusRing" />
        </div>

        <!-- 카테고리 -->
        <div>
          <label class="text-sm font-semibold text-gray-700 block mb-1">
            카테고리 <span class="text-red-400">*</span>
          </label>
          <select v-model="form.category"
            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm transition outline-none appearance-none bg-white"
            :class="focusRing">
            <option value="" disabled>카테고리를 선택하세요</option>
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>

        <!-- 직종 태그 (multi-select) -->
        <div v-if="isHiring">
          <label class="text-sm font-semibold text-gray-700 block mb-2">직종 태그 (여러 개 선택 가능)</label>
          <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
            <button v-for="t in jobTagOptions" :key="t.value" type="button"
              @click="toggleJobTag(t.value)"
              class="py-2 px-2 rounded-lg text-xs font-semibold transition border-2"
              :class="selectedJobTags.includes(t.value)
                ? 'border-amber-400 bg-amber-50 text-amber-800'
                : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'">
              {{ t.label }}
            </button>
          </div>
          <p class="text-xs text-gray-400 mt-1.5">선택됨: {{ selectedJobTags.length }}개</p>
        </div>

        <!-- 고용형태 -->
        <div>
          <label class="text-sm font-semibold text-gray-700 block mb-2">고용형태</label>
          <div class="flex gap-2">
            <button v-for="t in typeOptions" :key="t.value" type="button"
              @click="form.type = t.value"
              class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition border-2"
              :class="form.type === t.value
                ? (isHiring ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-blue-400 bg-blue-50 text-blue-800')
                : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'">
              {{ t.label }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Section 3: 급여 & 위치 -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100" :class="isHiring ? 'bg-amber-50' : 'bg-blue-50'">
        <h2 class="text-sm font-bold" :class="isHiring ? 'text-amber-800' : 'text-blue-800'">급여 & 위치</h2>
      </div>
      <div class="p-5 space-y-4">
        <!-- 급여 -->
        <div>
          <label class="text-sm font-semibold text-gray-700 block mb-2">급여</label>
          <!-- 급여 단위 버튼 -->
          <div class="flex gap-2 mb-3">
            <button v-for="s in salaryTypes" :key="s.value" type="button"
              @click="form.salary_type = s.value"
              class="px-4 py-2 rounded-lg text-xs font-semibold transition border-2"
              :class="form.salary_type === s.value
                ? (isHiring ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-blue-400 bg-blue-50 text-blue-800')
                : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'">
              {{ s.label }}
            </button>
          </div>
          <!-- 급여 범위 -->
          <div class="flex items-center gap-3">
            <div class="flex-1 relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input v-model.number="form.salary_min" type="number" placeholder="최소"
                class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2.5 text-sm transition outline-none"
                :class="focusRing" />
            </div>
            <span class="text-gray-400 font-bold">~</span>
            <div class="flex-1 relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input v-model.number="form.salary_max" type="number" placeholder="최대"
                class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2.5 text-sm transition outline-none"
                :class="focusRing" />
            </div>
          </div>
        </div>

        <!-- 위치 -->
        <div>
          <label class="text-sm font-semibold text-gray-700 block mb-2">근무 위치</label>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="text-xs text-gray-500 block mb-1">City</label>
              <input v-model="form.city" type="text" placeholder="예: Los Angeles"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm transition outline-none"
                :class="focusRing" />
            </div>
            <div>
              <label class="text-xs text-gray-500 block mb-1">State</label>
              <input v-model="form.state" type="text" placeholder="예: CA"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm transition outline-none"
                :class="focusRing" />
            </div>
            <div>
              <label class="text-xs text-gray-500 block mb-1">Zip Code</label>
              <input v-model="form.zipcode" type="text" placeholder="예: 90001" maxlength="5"
                @input="onJobZipChange"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm transition outline-none font-mono"
                :class="focusRing" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Section 3.5: 상위노출 (급여/위치 입력 뒤에 배치) -->
    <PromotionSection resource="jobs" :is-edit="isEdit"
      :category="form.category" :state="form.state"
      v-model="jobPromotion" ref="jobPromoRef"
      category-label="카테고리" />

    <!-- Section 4: 복리후생 (Benefits) - only for hiring -->
    <section v-if="isHiring" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-amber-50">
        <h2 class="text-sm font-bold text-amber-800">복리후생 (Benefits)</h2>
      </div>
      <div class="p-5 space-y-3">
        <p class="text-xs text-gray-500">제공하는 복리후생을 모두 선택하세요.</p>
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
          <button v-for="b in benefitOptions" :key="b.value" type="button"
            @click="toggleBenefit(b.value)"
            class="py-2 px-2 rounded-lg text-xs font-semibold transition border-2 flex items-center justify-center gap-1"
            :class="selectedBenefits.includes(b.value)
              ? 'border-amber-400 bg-amber-50 text-amber-800'
              : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'">
            <span v-if="selectedBenefits.includes(b.value)">&#10003;</span>
            {{ b.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- Section 5: 상세 설명 (rich text) -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100" :class="isHiring ? 'bg-amber-50' : 'bg-blue-50'">
        <h2 class="text-sm font-bold" :class="isHiring ? 'text-amber-800' : 'text-blue-800'">상세 설명</h2>
      </div>
      <div class="p-5">
        <label class="text-xs text-gray-500 block mb-2">
          {{ isHiring ? '근무 조건, 자격 요건, 우대사항 등을 자세히 작성해주세요' : '본인 소개, 경력, 희망 근무조건 등을 작성해주세요' }}
        </label>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-1 mb-2 p-2 bg-gray-50 border border-gray-200 rounded-lg">
          <button type="button" @click="execCmd('bold')" title="굵게"
            class="w-8 h-8 rounded hover:bg-gray-200 font-bold text-gray-700">B</button>
          <button type="button" @click="execCmd('italic')" title="기울임"
            class="w-8 h-8 rounded hover:bg-gray-200 italic text-gray-700">I</button>
          <button type="button" @click="execCmd('underline')" title="밑줄"
            class="w-8 h-8 rounded hover:bg-gray-200 underline text-gray-700">U</button>
          <span class="w-px h-5 bg-gray-300 mx-1"></span>
          <button type="button" @click="execCmd('formatBlock', 'H2')" title="제목 H2"
            class="px-2 h-8 rounded hover:bg-gray-200 text-xs font-bold text-gray-700">H2</button>
          <button type="button" @click="execCmd('formatBlock', 'H3')" title="제목 H3"
            class="px-2 h-8 rounded hover:bg-gray-200 text-xs font-bold text-gray-700">H3</button>
          <button type="button" @click="execCmd('formatBlock', 'P')" title="단락"
            class="px-2 h-8 rounded hover:bg-gray-200 text-xs text-gray-700">P</button>
          <span class="w-px h-5 bg-gray-300 mx-1"></span>
          <button type="button" @click="execCmd('insertUnorderedList')" title="글머리 기호"
            class="w-8 h-8 rounded hover:bg-gray-200 text-gray-700">•</button>
          <button type="button" @click="execCmd('insertOrderedList')" title="번호 목록"
            class="w-8 h-8 rounded hover:bg-gray-200 text-xs text-gray-700">1.</button>
          <span class="w-px h-5 bg-gray-300 mx-1"></span>
          <button type="button" @click="contentImageInputRef?.click()" title="이미지 삽입"
            class="w-8 h-8 rounded hover:bg-gray-200 text-gray-700">
            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </button>
          <input ref="contentImageInputRef" type="file" accept="image/*" @change="onInsertImage" class="hidden" />
          <button type="button" @click="execCmd('removeFormat')" title="서식 지우기"
            class="ml-auto px-2 h-8 rounded hover:bg-gray-200 text-xs text-gray-500">지우기</button>
        </div>

        <div ref="editorRef" contenteditable="true"
          @input="onEditorInput"
          class="w-full min-h-[240px] border border-gray-300 rounded-lg px-4 py-3 text-sm transition outline-none leading-relaxed prose prose-sm max-w-none"
          :class="focusRing"
          :data-placeholder="isHiring
            ? '근무시간, 자격요건, 우대사항, 지원방법 등을 작성해주세요'
            : '경력, 가능업무, 희망지역 등을 작성해주세요'"></div>
        <p class="text-xs text-gray-400 mt-1 text-right">{{ contentTextLength }} 자</p>

        <!-- 회사 소개 PDF -->
        <div class="mt-5 pt-5 border-t border-gray-100">
          <label class="text-sm font-semibold text-gray-700 block mb-2">회사 소개 PDF (선택)</label>
          <div class="flex items-center gap-3">
            <input ref="pdfInputRef" type="file" accept="application/pdf" @change="onPdfSelect" class="hidden" />
            <button type="button" @click="pdfInputRef?.click()"
              class="px-4 py-2 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
              {{ pdfName ? 'PDF 변경' : 'PDF 선택' }}
            </button>
            <div v-if="pdfName" class="flex items-center gap-2 flex-1 min-w-0">
              <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 7V3.5L18.5 9H13z"/></svg>
              <span class="text-xs text-gray-700 truncate">{{ pdfName }}</span>
              <button type="button" @click="clearPdf" class="text-xs text-red-600 hover:underline flex-shrink-0">제거</button>
            </div>
            <span v-else class="text-xs text-gray-400">최대 10MB</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Section 6: 연락처 -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100" :class="isHiring ? 'bg-amber-50' : 'bg-blue-50'">
        <h2 class="text-sm font-bold" :class="isHiring ? 'text-amber-800' : 'text-blue-800'">연락처</h2>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-semibold text-gray-700 block mb-1">전화번호</label>
            <div class="relative">
              <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              <input v-model="form.contact_phone" type="text" placeholder="213-000-0000"
                class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm transition outline-none"
                :class="focusRing" />
            </div>
          </div>
          <div>
            <label class="text-sm font-semibold text-gray-700 block mb-1">이메일</label>
            <div class="relative">
              <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <input v-model="form.contact_email" type="email" placeholder="email@example.com"
                class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm transition outline-none"
                :class="focusRing" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Section 7: 만료일 -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100" :class="isHiring ? 'bg-amber-50' : 'bg-blue-50'">
        <h2 class="text-sm font-bold" :class="isHiring ? 'text-amber-800' : 'text-blue-800'">만료일 (선택)</h2>
      </div>
      <div class="p-5">
        <label class="text-xs text-gray-500 block mb-2">설정하지 않으면 무기한 게시됩니다</label>
        <input v-model="form.expires_at" type="date"
          class="w-full sm:w-auto border border-gray-300 rounded-lg px-4 py-2.5 text-sm transition outline-none"
          :class="focusRing" />
      </div>
    </section>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-5 py-3 text-sm font-medium">
      {{ error }}
    </div>

    <!-- Actions -->
    <div class="flex gap-3 pb-10">
      <button @click="submit" :disabled="submitting"
        class="flex-1 sm:flex-none font-bold px-8 py-3 rounded-xl shadow-md transition disabled:opacity-50 text-sm"
        :class="isHiring
          ? 'bg-amber-400 text-amber-900 hover:bg-amber-500 shadow-amber-200'
          : 'bg-blue-500 text-white hover:bg-blue-600 shadow-blue-200'">
        {{ submitting ? '저장 중...' : (isEdit ? '수정하기' : '등록하기') }}
      </button>
      <button @click="$router.back()"
        class="px-6 py-3 rounded-xl text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 text-sm font-medium transition">
        취소
      </button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import PromotionSection from '../../components/PromotionSection.vue'

// 공통 컴포넌트로 프로모션 관리 (jobPromotion 으로 v-model)
const jobPromotion = reactive({ tier: 'none', days: 7 })
const jobPromoRef = ref(null)

// zipcode 자동 city/state 감지
let jobZipTimer = null
function onJobZipChange() {
  clearTimeout(jobZipTimer)
  jobZipTimer = setTimeout(async () => {
    const z = (form.zipcode || '').trim()
    if (!/^\d{5}$/.test(z)) return
    try {
      const r = await fetch(`https://api.zippopotam.us/us/${z}`)
      if (!r.ok) return
      const d = await r.json()
      const p = d.places?.[0]
      if (p) {
        form.city = p['place name'] || ''
        form.state = p['state abbreviation'] || ''
      }
    } catch {}
  }, 500)
}

const router = useRouter()
const route = useRoute()

const form = reactive({
  post_type: 'hiring',
  title: '',
  company: '',
  category: '',
  type: 'full',
  salary_min: null,
  salary_max: null,
  salary_type: 'hourly',
  city: '',
  state: '',
  zipcode: '',
  content: '',
  contact_phone: '',
  contact_email: '',
  expires_at: '',
})

const categories = [
  { value: 'restaurant', label: '요식업 (Restaurant)' },
  { value: 'it', label: 'IT / 기술 (Tech)' },
  { value: 'beauty', label: '미용 (Beauty)' },
  { value: 'driving', label: '운전 (Driving)' },
  { value: 'retail', label: '판매 (Retail)' },
  { value: 'office', label: '사무직 (Office)' },
  { value: 'construction', label: '건설 (Construction)' },
  { value: 'medical', label: '의료 (Medical)' },
  { value: 'education', label: '교육 (Education)' },
  { value: 'etc', label: '기타 (Other)' },
]

const typeOptions = [
  { value: 'full', label: '풀타임 (Full-time)' },
  { value: 'part', label: '파트타임 (Part-time)' },
  { value: 'contract', label: '계약직 (Contract)' },
]

const salaryTypes = [
  { value: 'hourly', label: '시급 (Hourly)' },
  { value: 'monthly', label: '월급 (Monthly)' },
  { value: 'yearly', label: '연봉 (Yearly)' },
]

const jobTagOptions = [
  { value: 'cook', label: '요리사' },
  { value: 'server', label: '서빙' },
  { value: 'cashier', label: '캐셔' },
  { value: 'manager', label: '매니저' },
  { value: 'barista', label: '바리스타' },
  { value: 'delivery', label: '배달' },
  { value: 'driver', label: '운전' },
  { value: 'mechanic', label: '정비' },
  { value: 'accountant', label: '회계' },
  { value: 'receptionist', label: '접수' },
  { value: 'cleaner', label: '청소' },
  { value: 'nail', label: '네일' },
  { value: 'hair', label: '미용사' },
  { value: 'esthetician', label: '피부관리' },
  { value: 'teacher', label: '강사' },
  { value: 'tutor', label: '과외' },
  { value: 'developer', label: '개발자' },
  { value: 'designer', label: '디자이너' },
  { value: 'sales', label: '영업' },
  { value: 'nurse', label: '간호사' },
]

const benefitOptions = [
  { value: 'health', label: '건강보험' },
  { value: 'dental', label: '치과보험' },
  { value: 'vision', label: '비전보험' },
  { value: '401k', label: '401K' },
  { value: 'paid_vacation', label: '유급휴가' },
  { value: 'sick_leave', label: '병가' },
  { value: 'meal', label: '식사제공' },
  { value: 'tips', label: '팁' },
  { value: 'bonus', label: '보너스' },
  { value: 'sponsor', label: '비자 스폰서' },
  { value: 'training', label: '교육지원' },
  { value: 'parking', label: '주차지원' },
]

const selectedJobTags = ref([])
const selectedBenefits = ref([])

// Logo
const logoFile = ref(null)
const logoPreview = ref(null)
const logoInputRef = ref(null)
const existingLogoUrl = ref(null)

// PDF
const pdfFile = ref(null)
const pdfName = ref('')
const pdfInputRef = ref(null)
const existingPdfUrl = ref(null)

// Rich text editor
const editorRef = ref(null)
const contentImageInputRef = ref(null)
const contentTextLength = ref(0)

const error = ref('')
const submitting = ref(false)
const isEdit = ref(false)
const editId = ref(null)

const isHiring = computed(() => form.post_type === 'hiring')
const focusRing = computed(() =>
  isHiring.value ? 'focus:ring-2 focus:ring-amber-400 focus:border-amber-400' : 'focus:ring-2 focus:ring-blue-400 focus:border-blue-400'
)

function toggleJobTag(v) {
  const idx = selectedJobTags.value.indexOf(v)
  if (idx >= 0) selectedJobTags.value.splice(idx, 1)
  else selectedJobTags.value.push(v)
}

function toggleBenefit(v) {
  const idx = selectedBenefits.value.indexOf(v)
  if (idx >= 0) selectedBenefits.value.splice(idx, 1)
  else selectedBenefits.value.push(v)
}

// Logo handlers
function onLogoSelect(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    error.value = '로고 파일 크기는 5MB 이하여야 합니다.'
    e.target.value = ''
    return
  }
  logoFile.value = file
  const reader = new FileReader()
  reader.onload = () => { logoPreview.value = reader.result }
  reader.readAsDataURL(file)
}
function clearLogo() {
  logoFile.value = null
  logoPreview.value = null
  existingLogoUrl.value = null
  if (logoInputRef.value) logoInputRef.value.value = ''
}

// PDF handlers
function onPdfSelect(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.type !== 'application/pdf') {
    error.value = 'PDF 파일만 업로드할 수 있습니다.'
    e.target.value = ''
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    error.value = 'PDF 파일 크기는 10MB 이하여야 합니다.'
    e.target.value = ''
    return
  }
  pdfFile.value = file
  pdfName.value = file.name
}
function clearPdf() {
  pdfFile.value = null
  pdfName.value = ''
  existingPdfUrl.value = null
  if (pdfInputRef.value) pdfInputRef.value.value = ''
}

// Rich text editor
function execCmd(cmd, value = null) {
  editorRef.value?.focus()
  document.execCommand(cmd, false, value)
  syncContent()
}
function onEditorInput() {
  syncContent()
}
function syncContent() {
  if (!editorRef.value) return
  form.content = editorRef.value.innerHTML
  contentTextLength.value = (editorRef.value.innerText || '').length
}
function onInsertImage(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    error.value = '이미지는 5MB 이하여야 합니다.'
    e.target.value = ''
    return
  }
  const reader = new FileReader()
  reader.onload = () => {
    execCmd('insertImage', reader.result)
    e.target.value = ''
  }
  reader.readAsDataURL(file)
}

async function submit() {
  syncContent()
  const plainText = (editorRef.value?.innerText || '').trim()

  if (!form.title) { error.value = '제목을 입력해주세요'; return }
  if (!form.company && isHiring.value) { error.value = '회사명을 입력해주세요'; return }
  if (!form.category) { error.value = '카테고리를 선택해주세요'; return }
  if (!plainText) { error.value = '상세 설명을 입력해주세요'; return }
  // 프로모션 선택했지만 슬롯 만석 → 등록 차단
  if (['state_plus','national'].includes(jobPromotion.tier) && jobPromoRef.value?.isSlotFull) {
    const t = jobPromoRef.value?.nextSlotTimeFmt
    error.value = t
      ? `상위노출 슬롯 만석. ${t} 이후 가능합니다. 상위노출을 "사용 안 함" 으로 바꾸거나 다시 선택해주세요.`
      : '상위노출 슬롯이 모두 사용 중입니다.'
    return
  }

  submitting.value = true
  error.value = ''

  const fd = new FormData()
  fd.append('post_type', form.post_type)
  fd.append('title', form.title)
  fd.append('company', form.company || '')
  fd.append('category', form.category)
  fd.append('type', form.type)
  if (form.salary_min !== null && form.salary_min !== '') fd.append('salary_min', form.salary_min)
  if (form.salary_max !== null && form.salary_max !== '') fd.append('salary_max', form.salary_max)
  fd.append('salary_type', form.salary_type)
  fd.append('city', form.city || '')
  fd.append('state', form.state || '')
  fd.append('zipcode', form.zipcode || '')
  fd.append('content', form.content)
  fd.append('contact_phone', form.contact_phone || '')
  fd.append('contact_email', form.contact_email || '')
  if (form.expires_at) fd.append('expires_at', form.expires_at)

  fd.append('job_tags', JSON.stringify(selectedJobTags.value))
  fd.append('benefits', JSON.stringify(selectedBenefits.value))

  if (logoFile.value) fd.append('logo', logoFile.value)
  if (pdfFile.value) fd.append('company_pdf', pdfFile.value)

  try {
    let createdId = editId.value
    if (isEdit.value) {
      fd.append('_method', 'PUT')
      await axios.post(`/api/jobs/${editId.value}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    } else {
      const { data } = await axios.post('/api/jobs', fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      createdId = data?.data?.id ?? data?.id
    }

    if (!isEdit.value && jobPromotion.tier !== 'none' && createdId) {
      try {
        await axios.post(`/api/jobs/${createdId}/promote`, {
          tier: jobPromotion.tier,
          days: jobPromotion.days,
        })
      } catch (promoErr) {
        console.warn('Promotion failed', promoErr)
        if (promoErr.response?.data?.message) error.value = promoErr.response.data.message
      }
    }

    router.push(`/jobs/${createdId}`)
  } catch (e) {
    const resp = e.response?.data
    // Laravel validation 422 형식 { errors: { field: [msg] } } 처리
    if (resp?.errors && typeof resp.errors === 'object') {
      const firstMsg = Object.values(resp.errors).flat()[0]
      error.value = firstMsg || resp.message || '입력값을 확인해주세요.'
    } else {
      error.value = resp?.message || '등록에 실패했습니다. 다시 시도해주세요.'
    }
  }
  submitting.value = false
}

onMounted(async () => {
  if (route.query.edit) {
    editId.value = route.query.edit
    isEdit.value = true
    try {
      const { data } = await axios.get(`/api/jobs/${editId.value}`)
      const j = data.data
      Object.keys(form).forEach(k => {
        if (j[k] !== undefined && j[k] !== null) form[k] = j[k]
      })
      // Load tags and benefits
      if (Array.isArray(j.job_tags)) selectedJobTags.value = [...j.job_tags]
      else if (typeof j.job_tags === 'string') {
        try { selectedJobTags.value = JSON.parse(j.job_tags) || [] } catch { selectedJobTags.value = [] }
      }
      if (Array.isArray(j.benefits)) selectedBenefits.value = [...j.benefits]
      else if (typeof j.benefits === 'string') {
        try { selectedBenefits.value = JSON.parse(j.benefits) || [] } catch { selectedBenefits.value = [] }
      }
      // Logo
      if (j.logo_url || j.logo) {
        existingLogoUrl.value = j.logo_url || j.logo
        logoPreview.value = existingLogoUrl.value
      }
      // PDF
      if (j.company_pdf_url || j.company_pdf) {
        existingPdfUrl.value = j.company_pdf_url || j.company_pdf
        pdfName.value = (existingPdfUrl.value || '').split('/').pop() || 'company.pdf'
      }

      await nextTick()
      if (editorRef.value) {
        editorRef.value.innerHTML = form.content || ''
        contentTextLength.value = (editorRef.value.innerText || '').length
      }
    } catch {}
  } else {
    await nextTick()
    if (editorRef.value) {
      editorRef.value.innerHTML = ''
      contentTextLength.value = 0
    }
  }
})
</script>

<style scoped>
[contenteditable="true"]:empty::before {
  content: attr(data-placeholder);
  color: #9ca3af;
  pointer-events: none;
}
[contenteditable="true"] img {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 8px 0;
}
[contenteditable="true"] h2 {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0.5rem 0;
}
[contenteditable="true"] h3 {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0.5rem 0;
}
[contenteditable="true"] ul {
  list-style: disc;
  padding-left: 1.5rem;
}
[contenteditable="true"] ol {
  list-style: decimal;
  padding-left: 1.5rem;
}
</style>
