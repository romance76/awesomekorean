<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <h1 class="text-xl font-bold text-gray-800 mb-5">물품 등록</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
      <div class="space-y-5">
        <!-- 제목 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">제목 *</label>
          <input v-model="form.title" type="text" placeholder="물품명을 입력하세요" maxlength="200"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- 가격 (눈에 띄게) -->
        <div class="bg-red-50 border border-red-100 rounded-xl p-4">
          <div class="flex items-center gap-4">
            <div class="flex-1">
              <label class="block text-sm font-bold text-red-700 mb-1">가격 ($) *</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-red-600 font-bold text-lg">$</span>
                <input v-model="form.price" type="number" min="0" placeholder="0"
                  class="w-full border border-red-200 rounded-lg pl-8 pr-3 py-3 text-lg font-bold text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 bg-white" />
              </div>
            </div>
            <div class="flex items-center pt-6">
              <input v-model="form.price_negotiable" type="checkbox" id="nego" class="rounded text-red-600 w-4 h-4" />
              <label for="nego" class="text-sm text-red-600 font-medium ml-2">가격 협의 가능</label>
            </div>
          </div>
        </div>

        <!-- 카테고리 / 상태 -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">카테고리</label>
            <select v-model="form.category" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="">선택</option>
              <option>전자제품</option><option>의류/잡화</option><option>가구/인테리어</option>
              <option>식품</option><option>도서</option><option>유아동</option><option>기타</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">상품 상태</label>
            <select v-model="form.condition" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="">선택</option>
              <option value="새것">새것</option>
              <option value="거의 새것">거의 새것</option>
              <option value="사용감 있음">사용감 있음</option>
              <option value="많이 사용">많이 사용</option>
            </select>
          </div>
        </div>

        <!-- 지역 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">지역</label>
          <select v-model="form.region" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
            <option value="">선택</option>
            <option>Atlanta</option><option>New York</option><option>Los Angeles</option>
            <option>Dallas</option><option>Chicago</option><option>Seattle</option>
          </select>
        </div>

        <!-- 사진 업로드 (6장: 1장 무료 + 5장 유료) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">사진 첨부</label>
          <p class="text-xs text-gray-500 mb-3">첫 번째 사진은 무료, 추가 사진은 각 10포인트가 차감됩니다.</p>
          <div class="grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2">
            <div v-for="i in 6" :key="i" class="relative">
              <div v-if="photos[i-1]" class="h-24 rounded-xl overflow-hidden relative group">
                <img :src="photos[i-1].preview" class="w-full h-full object-cover" />
                <button @click="removePhoto(i-1)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
                <span v-if="i === 1" class="absolute bottom-1 left-1 bg-green-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-medium">기본</span>
                <span v-else class="absolute bottom-1 left-1 bg-orange-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-medium">10P</span>
              </div>
              <label v-else class="h-24 rounded-xl border-2 border-dashed flex flex-col items-center justify-center cursor-pointer transition-colors"
                :class="i === 1 ? 'border-green-300 hover:border-green-400 hover:bg-green-50' : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'">
                <span class="text-2xl" :class="i === 1 ? 'text-green-400' : 'text-gray-400'">+</span>
                <span class="text-[10px] mt-1 font-medium" :class="i === 1 ? 'text-green-500' : 'text-orange-500'">
                  {{ i === 1 ? '기본 (무료)' : '추가 (10P)' }}
                </span>
                <input type="file" accept="image/*" class="hidden" @change="addPhoto($event, i-1)" />
              </label>
            </div>
          </div>
          <p v-if="additionalPhotoCount > 0" class="text-xs text-orange-600 mt-2 font-medium">
            추가 사진 {{ additionalPhotoCount }}장 = {{ additionalPhotoCount * 10 }}P 차감 예정
          </p>
        </div>

        <!-- 주소 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">거래 장소</label>
          <AddressInput v-model="form.addressObj" />
        </div>

        <!-- 외부 링크 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">외부 링크</label>
          <input v-model="form.external_link" type="url" placeholder="https:// 외부 판매 링크"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- 상세 설명 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">상세 설명</label>
          <textarea v-model="form.description" rows="8" placeholder="물품 상태, 구매 시기, 하자 유무 등을 자세히 입력해주세요"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
        </div>

        <!-- 연락처 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">연락처 정보</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">전화번호</label>
              <input v-model="form.contact_phone" type="tel" placeholder="000-000-0000"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">선호 연락 방법</label>
              <select v-model="form.preferred_contact" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="">선택</option>
                <option value="전화">전화</option>
                <option value="문자">문자</option>
                <option value="쪽지">쪽지</option>
              </select>
            </div>
          </div>
        </div>

        <!-- 에러 -->
        <div v-if="error" class="text-red-600 text-sm bg-red-50 p-3 rounded-lg">{{ error }}</div>

        <!-- 버튼 -->
        <div class="flex justify-end space-x-3">
          <button @click="$router.back()" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">취소</button>
          <button @click="submit" :disabled="loading" class="px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ loading ? '등록 중...' : '물품 등록' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import AddressInput from '../../components/AddressInput.vue';

const router = useRouter();
const loading = ref(false);
const error = ref('');

const form = ref({
  title: '', category: '', condition: '', price: '', region: '',
  description: '', price_negotiable: false, item_type: 'sell',
  address: '', external_link: '', contact_phone: '', preferred_contact: '',
  addressObj: { address1: '', address2: '', city: '', state: '', zip: '', full: '' }
});

// Photos (6 slots: 1 free + 5 paid)
const photos = reactive([]);
const additionalPhotoCount = computed(() => {
  return photos.filter((p, i) => p && i > 0).length;
});

function addPhoto(event, index) {
  const file = event.target.files[0];
  if (!file) return;
  photos[index] = { file, preview: URL.createObjectURL(file) };
}
function removePhoto(index) {
  if (photos[index]?.preview) URL.revokeObjectURL(photos[index].preview);
  photos[index] = null;
}

async function submit() {
  if (!form.value.title.trim()) { error.value = '제목을 입력하세요.'; return; }
  if (!form.value.price && form.value.price !== 0) { error.value = '가격을 입력하세요.'; return; }
  loading.value = true; error.value = '';
  try {
    const fd = new FormData();
    form.value.address = form.value.addressObj.full || '';
    Object.keys(form.value).forEach(key => {
      if (key === 'addressObj') return;
      const val = form.value[key];
      if (val !== null && val !== '') {
        fd.append(key, typeof val === 'boolean' ? (val ? '1' : '0') : val);
      }
    });
    photos.forEach((photo) => {
      if (photo?.file) fd.append('photos[]', photo.file);
    });

    const { data } = await axios.post('/api/market', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    router.push(`/market/${data.item.id}`);
  } catch(e) {
    error.value = e.response?.data?.message || '오류가 발생했습니다.';
  } finally { loading.value = false; }
}
</script>
