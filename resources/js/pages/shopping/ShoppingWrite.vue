<template>
<div class="min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-5">
    <DetailHeader :title="editId ? '리뷰 수정' : '리뷰 쓰기'" fallback="/shopping" />
    <h1 class="hidden lg:flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="20" /></span>
      {{ editId ? '내돈내산 리뷰 수정' : '내돈내산 리뷰 쓰기' }}
    </h1>

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <VerifyGate v-else message="이메일 인증 후 리뷰를 쓸 수 있어요.">
      <!-- 1) 태그가 없으면 먼저 등록 -->
      <div v-if="!tag" class="card p-5 space-y-3">
        <h2 class="font-bold text-ink">먼저 내 Amazon Associates 태그를 등록해주세요</h2>
        <p class="text-xs text-ink-muted leading-relaxed">
          내돈내산 리뷰는 <b>본인의 Amazon Associates 계정</b>이 있는 회원만 쓸 수 있어요.
          리뷰의 "Amazon에서 보기" 링크에는 내 태그가 붙고, 그 링크로 생긴 수익은 <b>내 Associates 계정</b>으로 들어갑니다.
          (Awesome Korean은 수익을 가져가지 않아요.)
        </p>
        <TagForm v-model="tagInput" :saving="tagSaving" :msg="tagMsg" @save="saveTag" />
      </div>

      <form v-else @submit.prevent="submit" class="space-y-4">
        <!-- 규칙 -->
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800 leading-relaxed">
          <div class="font-bold mb-1">🚫 리뷰 작성 규칙 — 꼭 읽어주세요</div>
          <ul class="list-disc pl-4 space-y-0.5">
            <li><b>내 사이트·쇼핑몰·블로그·SNS·유튜브·가게 홍보나 광고는 금지</b>예요. 웹 주소, 이메일, 전화번호, 카톡/인스타 아이디를 넣을 수 없어요.</li>
            <li>직접 구입해서 써본 제품만, 솔직하게 장단점을 써주세요. (협찬·광고 글 금지)</li>
            <li>직접 찍은 사진을 한 장 이상 올려주세요.</li>
            <li>다른 회원이 신고하면 리뷰가 내려갈 수 있고, 반복되면 작성 권한이 제한돼요.</li>
          </ul>
        </div>
        <div v-if="!editId && rules.first_approval" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800">
          처음 쓰는 리뷰는 <b>관리자 확인 후</b> 공개돼요. 한 번 승인되면 이후 리뷰는 바로 공개됩니다.
        </div>

        <div class="card p-4 space-y-4">
          <div v-if="!editId">
            <label class="lbl">Amazon 상품 주소 또는 ASIN <span class="text-red-500">*</span></label>
            <input v-model="f.input" type="text" class="input-soft" placeholder="https://www.amazon.com/.../dp/B0XXXXXXXX" />
            <p class="hint">amazon.com 상품 페이지 주소(…/dp/XXXXXXXXXX)나 ASIN 10자리를 붙여넣으세요. amzn.to 같은 짧은 주소는 안 돼요. 링크는 내 태그(<b>{{ tag }}</b>)로 자동 만들어져요.</p>
          </div>
          <div v-else class="text-xs text-ink-muted">상품과 태그는 수정할 수 없어요. (ASIN {{ editAsin }})</div>

          <div>
            <label class="lbl">제목 <span class="text-red-500">*</span></label>
            <input v-model="f.title" type="text" maxlength="200" class="input-soft" placeholder="예) 스탠리 푸어오버 6개월 써본 후기" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="lbl">카테고리</label>
              <select v-model="f.category" class="input-soft">
                <option value="">선택 안 함</option>
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="lbl">별점 <span class="text-red-500">*</span></label>
              <div class="flex gap-0.5 text-2xl leading-none pt-1">
                <button v-for="n in 5" :key="n" type="button" @click="f.rating = n" :class="n <= f.rating ? 'text-amber-400' : 'text-gray-300'" :aria-label="`${n}점`">★</button>
              </div>
            </div>
          </div>

          <div>
            <label class="lbl">리뷰 내용 <span class="text-red-500">*</span></label>
            <textarea v-model="f.body" rows="8" maxlength="5000" class="input-soft" :placeholder="`얼마나 오래, 어떻게 써봤는지 / 좋은 점 / 아쉬운 점 (최소 ${rules.min_chars}자)`"></textarea>
            <div class="text-right text-[11px]" :class="f.body.trim().length < rules.min_chars ? 'text-red-500' : 'text-ink-faint'">{{ f.body.trim().length }}/5000 · 최소 {{ rules.min_chars }}자</div>
          </div>

          <div>
            <label class="lbl">직접 찍은 사진 <span v-if="!editId" class="text-red-500">*</span> <span class="text-ink-faint font-normal">(최대 5장)</span></label>
            <div class="flex flex-wrap gap-2">
              <img v-for="(u, i) in keepPhotos" :key="'k'+i" :src="u" class="w-20 h-20 object-cover rounded-lg border border-gray-100" />
              <div v-for="(p, i) in newPhotos" :key="'n'+i" class="relative">
                <img :src="p.preview" class="w-20 h-20 object-cover rounded-lg border border-gray-100" />
                <button type="button" @click="newPhotos.splice(i, 1)" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-black/70 text-white text-xs leading-5">×</button>
              </div>
              <label v-if="keepPhotos.length + newPhotos.length < 5" class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-200 flex items-center justify-center text-gray-400 cursor-pointer hover:border-amber-300">
                <AppIcon name="camera" :size="20" /><input type="file" accept="image/*" multiple class="hidden" @change="pickPhotos" />
              </label>
            </div>
            <p v-if="editId" class="hint">새 사진을 올리면 기존 사진 뒤에 추가돼요.</p>
          </div>

          <label class="flex items-start gap-2 text-sm text-ink-light cursor-pointer">
            <input v-model="f.purchased" type="checkbox" class="mt-0.5 accent-amber-500" />
            <span>제가 <b>직접 구매해서 사용해 본</b> 제품이에요 (협찬·광고 아님)</span>
          </label>
          <label class="flex items-start gap-2 text-sm text-ink-light cursor-pointer">
            <input v-model="f.agree" type="checkbox" class="mt-0.5 accent-amber-500" />
            <span>위 <b>리뷰 작성 규칙</b>(내 사이트·가게 홍보 금지 등)을 읽었고 동의해요</span>
          </label>
        </div>

        <p v-if="err" class="text-sm text-red-500 whitespace-pre-line">{{ err }}</p>
        <div class="flex gap-2">
          <button type="button" @click="$router.back()" class="btn-secondary flex-1">취소</button>
          <button type="submit" :disabled="busy" class="btn-primary flex-1 py-3 font-bold disabled:opacity-50">{{ busy ? '저장 중...' : editId ? '수정 저장' : '리뷰 올리기' }}</button>
        </div>

        <div class="text-[11px] text-ink-faint text-center">
          내 태그: <b>{{ tag }}</b> ·
          <button type="button" class="underline" @click="changeTag = !changeTag">태그 변경</button>
        </div>
        <div v-if="changeTag" class="card p-4"><TagForm v-model="tagInput" :saving="tagSaving" :msg="tagMsg" @save="saveTag" /></div>
      </form>
    </VerifyGate>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, defineComponent, h } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import DetailHeader from '../../components/DetailHeader.vue'
import VerifyGate from '../../components/VerifyGate.vue'
import { useSiteStore } from '../../stores/site'

const route = useRoute()
const router = useRouter()
const site = useSiteStore()
const editId = computed(() => route.query.edit ? Number(route.query.edit) : null)
const categories = [
  '인기상품', '오늘의 딜', '주방용품', '한국요리 관련 제품', '식품', '생활용품',
  '가전/전자제품', '육아/교육', 'Beauty/K-Beauty', 'Home', '계절상품', '선물',
]

const loading = ref(true)
const tag = ref(null)
const tagInput = ref('')
const tagSaving = ref(false)
const tagMsg = ref('')
const changeTag = ref(false)
const rules = reactive({ min_chars: 60, first_approval: true })
const f = reactive({ input: '', title: '', category: '', rating: 5, body: '', purchased: false, agree: false })
const keepPhotos = ref([])
const newPhotos = ref([])
const editAsin = ref('')
const busy = ref(false)
const err = ref('')

// 태그 입력 폼 (등록/변경 공용)
const TagForm = defineComponent({
  props: { modelValue: String, saving: Boolean, msg: String },
  emits: ['update:modelValue', 'save'],
  setup(p, { emit }) {
    return () => h('div', { class: 'space-y-2' }, [
      h('input', { value: p.modelValue, class: 'input-soft', placeholder: '예) myshop-20', maxlength: 40, onInput: e => emit('update:modelValue', e.target.value) }),
      h('p', { class: 'text-[11px] text-ink-faint leading-relaxed' }, 'Amazon Associates Central 우측 상단의 Store ID예요. (영문/숫자/하이픈, 끝이 -20 같은 숫자 2자리) 아직 계정이 없다면 affiliate-program.amazon.com 에서 먼저 가입하세요.'),
      p.msg ? h('p', { class: 'text-xs text-red-500' }, p.msg) : null,
      h('button', { type: 'button', disabled: p.saving || !p.modelValue?.trim(), class: 'btn-primary px-4 py-2 text-sm disabled:opacity-50', onClick: () => emit('save') }, p.saving ? '저장 중...' : '태그 저장'),
    ])
  },
})

async function saveTag() {
  tagSaving.value = true; tagMsg.value = ''
  try {
    const { data } = await axios.put('/api/shopping/my-tag', { amazon_tag: tagInput.value })
    tag.value = data.data.amazon_tag
    changeTag.value = false
    site.toast(data.message || '저장했어요', 'success')
  } catch (e) {
    tagMsg.value = e.response?.data?.message || '저장하지 못했어요'
  }
  tagSaving.value = false
}

function pickPhotos(e) {
  for (const file of Array.from(e.target.files || [])) {
    if (keepPhotos.value.length + newPhotos.value.length >= 5) break
    newPhotos.value.push({ file, preview: URL.createObjectURL(file) })
  }
  e.target.value = ''
}

async function submit() {
  err.value = ''
  const need = []
  if (!editId.value && !f.input.trim()) need.push('상품 주소 또는 ASIN')
  if (!f.title.trim()) need.push('제목')
  if (f.body.trim().length < rules.min_chars) need.push(`리뷰 내용(최소 ${rules.min_chars}자)`)
  if (!editId.value && !newPhotos.value.length) need.push('사진 1장 이상')
  if (!f.purchased) need.push('직접 구매 체크')
  if (!f.agree) need.push('규칙 동의 체크')
  if (need.length) { err.value = `다음 항목을 확인해주세요: ${need.join(', ')}`; return }

  const fd = new FormData()
  if (!editId.value) fd.append('input', f.input.trim())
  fd.append('title', f.title.trim())
  if (f.category) fd.append('category', f.category)
  fd.append('rating', f.rating)
  fd.append('body', f.body.trim())
  fd.append('purchased', '1'); fd.append('agree_rules', '1')
  newPhotos.value.forEach(p => fd.append('photos[]', p.file))
  if (editId.value && newPhotos.value.length) keepPhotos.value.forEach(u => fd.append('keep_photos[]', u))

  busy.value = true
  try {
    const url = editId.value ? `/api/shopping/reviews/${editId.value}` : '/api/shopping/reviews'
    const { data } = await axios.post(url, fd)
    site.toast(data.message || '저장했어요', 'success')
    router.replace(data.data?.status === 'published' ? `/shopping/${data.data.id}` : '/dashboard?tab=reviews')
  } catch (e) {
    const r = e.response?.data
    if (r?.needs_tag) tag.value = null
    err.value = r?.errors ? Object.values(r.errors).flat().join('\n') : (r?.message || '저장하지 못했어요')
  }
  busy.value = false
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/shopping/my')
    tag.value = data.data.amazon_tag
    tagInput.value = data.data.amazon_tag || ''
    Object.assign(rules, data.data.rules || {})
    if (editId.value) {
      const p = data.data.items.find(i => i.id === editId.value)
      if (!p) { router.replace('/dashboard?tab=reviews'); return }
      Object.assign(f, { title: p.title, category: p.category || '', rating: p.rating || 5, body: p.our_description || '', purchased: true, agree: true })
      keepPhotos.value = p.own_image_urls || []
      editAsin.value = p.asin
    }
  } catch {}
  loading.value = false
})
</script>
<style scoped>
.lbl { display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--color-ink-light, #4b5563); }
.hint { font-size: 11px; color: #9ca3af; margin-top: 0.25rem; line-height: 1.4; }
</style>
