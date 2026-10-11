<template>
  <div>
  <div class="card p-6 mb-4">
    <h2 class="text-base font-semibold text-ink mb-6">푸터 편집</h2>

    <p class="text-xs text-ink-muted mb-4">사이트 맨 아래 푸터와 같은 모양이에요. 첫 칸은 로고 + 소개 문구, 그 옆으로 아래 섹션들이 나란히 표시돼요. 저장하면 바로 사이트에 반영돼요.</p>
    <div class="mb-5">
      <label class="input-label">로고 아래 소개 문구</label>
      <input v-model="footer.tagline" type="text" class="input-field" placeholder="미국 한인 No.1 커뮤니티" />
    </div>

    <!-- Footer columns -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <div v-for="(col, ci) in footer.columns" :key="ci" class="border border-gray-100 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
          <input v-model="col.title" type="text" class="input-field text-sm font-medium" placeholder="섹션 제목" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(link, li) in col.links" :key="li" class="flex items-center gap-2">
            <input v-model="link.label" type="text" class="input-field text-xs flex-1" placeholder="링크 텍스트" />
            <input v-model="link.url" type="text" class="input-field text-xs flex-1" placeholder="URL" />
            <button @click="removeFooterLink(ci, li)" class="text-red-400 hover:text-red-600 flex-shrink-0 text-lg leading-none">&times;</button>
          </div>
        </div>
        <button @click="addFooterLink(ci)" class="text-xs text-amber-600 hover:text-amber-700 font-medium transition-colors">+ 링크 추가</button>
      </div>
    </div>

    <!-- Copyright -->
    <div class="mb-5">
      <label class="input-label">저작권 문구</label>
      <input v-model="footer.copyright" type="text" class="input-field" placeholder="© 2024 AwesomeKorean Inc. All rights reserved." />
    </div>

    <!-- SNS Links -->
    <div class="mb-5">
      <h3 class="text-sm font-semibold text-ink-muted mb-3">SNS 링크</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="flex items-center gap-2">
          <span class="text-sm text-ink-muted w-24 flex-shrink-0">Facebook</span>
          <input v-model="footer.sns.facebook" type="text" class="input-field text-sm flex-1" placeholder="https://facebook.com/..." />
        </div>
        <div class="flex items-center gap-2">
          <span class="text-sm text-ink-muted w-24 flex-shrink-0">Instagram</span>
          <input v-model="footer.sns.instagram" type="text" class="input-field text-sm flex-1" placeholder="https://instagram.com/..." />
        </div>
        <div class="flex items-center gap-2">
          <span class="text-sm text-ink-muted w-24 flex-shrink-0">Twitter/X</span>
          <input v-model="footer.sns.twitter" type="text" class="input-field text-sm flex-1" placeholder="https://twitter.com/..." />
        </div>
        <div class="flex items-center gap-2">
          <span class="text-sm text-ink-muted w-24 flex-shrink-0">YouTube</span>
          <input v-model="footer.sns.youtube" type="text" class="input-field text-sm flex-1" placeholder="https://youtube.com/..." />
        </div>
        <div class="flex items-center gap-2">
          <span class="text-sm text-ink-muted w-24 flex-shrink-0">Threads</span>
          <input v-model="footer.sns.threads" type="text" class="input-field text-sm flex-1" placeholder="https://www.threads.com/@..." />
        </div>
      </div>
    </div>

    <!-- Additional Text -->
    <div class="mb-6">
      <label class="input-label">하단 추가 텍스트</label>
      <textarea v-model="footer.additional_text" rows="3" class="input-field resize-none text-sm" placeholder="사업자등록번호, 통신판매업 신고번호 등 법적 고지사항..."></textarea>
    </div>

    <div class="flex justify-end">
      <button @click="saveFooter" :disabled="saving" class="btn-primary">
        <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
        저장하기
      </button>
    </div>
  </div>

  <!-- Live Footer Preview -->
  <div class="bg-gray-800 rounded-xl overflow-hidden">
    <div class="px-5 py-3 bg-gray-700 flex items-center gap-2">
      <div class="w-3 h-3 rounded-full bg-red-400"></div>
      <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
      <div class="w-3 h-3 rounded-full bg-green-400"></div>
      <span class="text-gray-300 text-xs ml-2">푸터 미리보기</span>
    </div>
    <footer class="px-8 py-10 text-gray-400 text-sm">
      <div class="grid gap-8 mb-8" :style="{ gridTemplateColumns: `repeat(${footer.columns.length + 1}, minmax(0, 1fr))` }">
        <div>
          <img v-if="company.logo_dark_url" :src="company.logo_dark_url" alt="AwesomeKorean" class="h-7 w-auto mb-2" />
          <div v-else class="text-amber-400 font-black text-sm mb-2">AwesomeKorean</div>
          <div class="text-xs text-gray-400">{{ footer.tagline }}</div>
        </div>
        <div v-for="(col, ci) in footer.columns" :key="ci">
          <h4 class="text-white font-semibold mb-3">{{ col.title || '섹션명' }}</h4>
          <ul class="space-y-1.5">
            <li v-for="(link, li) in col.links" :key="li">
              <a href="#" class="hover:text-white transition-colors text-xs">{{ link.label || '링크' }}</a>
            </li>
            <li v-if="col.links.length === 0" class="text-xs text-gray-600">링크를 추가하세요</li>
          </ul>
        </div>
      </div>
      <div class="border-t border-gray-700 pt-6 flex flex-col md:flex-row items-center justify-between gap-4">
        <p class="text-xs">{{ footer.copyright || '© 2024 AwesomeKorean Inc.' }}</p>
        <div class="flex items-center gap-3">
          <span v-if="footer.sns.facebook" class="text-xs hover:text-white cursor-pointer">Facebook</span>
          <span v-if="footer.sns.instagram" class="text-xs hover:text-white cursor-pointer">Instagram</span>
          <span v-if="footer.sns.twitter" class="text-xs hover:text-white cursor-pointer">Twitter</span>
          <span v-if="footer.sns.youtube" class="text-xs hover:text-white cursor-pointer">YouTube</span>
          <span v-if="footer.sns.threads" class="text-xs hover:text-white cursor-pointer">Threads</span>
        </div>
      </div>
      <p v-if="footer.additional_text" class="text-xs text-gray-600 mt-4">{{ footer.additional_text }}</p>
    </footer>
  </div>
  </div>
</template>

<script setup>
// 푸터 편집 탭 — 값(footer)은 SiteSettings.vue 가 불러오고 저장한다. 미리보기에 다크 로고(company.logo_dark_url)를 쓴다.
const props = defineProps({
  footer: { type: Object, required: true },
  company: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save'])
const footer = props.footer
const company = props.company
const saveFooter = () => emit('save')

function addFooterLink(colIndex) {
  footer.columns[colIndex].links.push({ label: '', url: '' })
}

function removeFooterLink(colIndex, linkIndex) {
  footer.columns[colIndex].links.splice(linkIndex, 1)
}
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}
</style>
