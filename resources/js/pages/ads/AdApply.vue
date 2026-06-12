<template>
<div :class="embedded ? '' : 'min-h-screen'">
  <div :class="embedded ? '' : 'max-w-5xl mx-auto px-4 py-5'">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="megaphone" :size="20" /></span>
      광고 신청 (월간 경매)
    </h1>
    <p class="text-sm text-ink-muted mb-1">매달 말일 24시간 입찰 접수 → 최고 입찰자 순으로 배정</p>
    <p class="text-xs text-amber-600 font-bold mb-2">다음 경매: {{ nextAuctionDate }}</p>
    <div v-if="adDiscountPct" class="mb-5 bg-red-50 border border-red-200 rounded-lg px-3 py-2 flex items-center gap-2">
      <span class="icon-chip w-8 h-8 bg-red-100 text-red-500"><AppIcon name="sparkles" :size="16" /></span>
      <div class="flex-1">
        <div class="text-sm font-bold text-red-700">{{ adPromotion?.title }} — 전체 광고 {{ adDiscountPct }}% 할인 진행중</div>
        <div class="text-xs text-red-500">종료: {{ fmtPromoEnd(adPromotion?.ends_at) }}</div>
      </div>
    </div>


    <!-- ═══ Step 1: 페이지 선택 (단일 선택) ═══ -->
    <div class="card p-5 mb-5">
      <h2 class="font-bold text-ink text-sm mb-3 flex items-center gap-2"><span class="icon-chip w-6 h-6 bg-amber-50 text-amber-600 text-xs font-black">1</span>광고 노출 페이지 선택</h2>

      <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
        <!-- 홈 -->
        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors text-xs font-bold"
          :class="selectedSub === 'home' ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
          <input type="radio" name="page_select" value="home" v-model="selectedSub" class="accent-amber-500" />
          <AppIcon name="home" :size="13" /> 홈
        </label>
        <!-- 서브 페이지들 -->
        <label v-for="sp in subPages" :key="sp.key"
          class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors text-xs font-bold"
          :class="selectedSub === sp.key ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
          <input type="radio" name="page_select" :value="sp.key" v-model="selectedSub" class="accent-amber-500" />
          <AppIcon :name="menuIcon(sp.key)" :size="13" /> {{ sp.label }}
        </label>
      </div>
    </div>

    <!-- ═══ Step 2: 지역 선택 (가격에 영향) ═══ -->
    <div class="card p-5 mb-5">
      <h2 class="font-bold text-ink text-sm mb-3 flex items-center gap-2"><span class="icon-chip w-6 h-6 bg-amber-50 text-amber-600 text-xs font-black">2</span>타겟 지역 선택</h2>
      <p class="text-xs text-ink-muted mb-3">페이지 종류에 따라 지역 설정이 다릅니다</p>

      <!-- 페이지 미선택 -->
      <div v-if="!selectedSub" class="text-center py-4 text-xs text-ink-muted">
        먼저 Step 1에서 페이지를 선택해 주세요
      </div>

      <!-- 전국 페이지 안내 -->
      <div v-else-if="isNationalPage" class="bg-blue-50 border border-blue-200 rounded-xl p-3">
        <div class="text-xs font-bold text-blue-700 mb-1 flex items-center gap-1"><AppIcon name="globe" :size="13" />전국 자동 노출</div>
        <div class="text-xs text-blue-600">
          {{ selectedPageLabel }} — 전국 페이지이므로 별도 지역 설정이 불필요합니다
        </div>
      </div>

      <!-- 지역별 페이지 설정 -->
      <div v-else>
        <div class="text-xs font-bold text-ink-light mb-2 flex items-center gap-1"><AppIcon name="map-pin" :size="13" class="text-amber-600" />타겟 지역 선택</div>
        <div class="border border-gray-100 rounded-xl p-3 bg-gray-50 mb-3">
          <div class="flex items-center gap-2 flex-wrap">
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition-colors"
              :class="adForm.geo_scope==='city' ? 'border-purple-400 bg-purple-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="city" v-model="adForm.geo_scope" class="accent-purple-500" />
              <span :class="adForm.geo_scope==='city' ? 'text-purple-700 font-bold' : 'text-ink-muted'">시티</span>
              <span class="text-[11px] text-ink-faint">-{{ geoMarkup.cityDiscount.toLocaleString() }}P</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition-colors"
              :class="adForm.geo_scope==='county' ? 'border-green-400 bg-green-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="county" v-model="adForm.geo_scope" class="accent-green-500" />
              <span :class="adForm.geo_scope==='county' ? 'text-green-700 font-bold' : 'text-ink-muted'">카운티</span>
              <span class="text-[11px] text-amber-600 font-bold">기본</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition-colors"
              :class="adForm.geo_scope==='state' ? 'border-blue-400 bg-blue-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="state" v-model="adForm.geo_scope" class="accent-blue-500" />
              <span :class="adForm.geo_scope==='state' ? 'text-blue-700 font-bold' : 'text-ink-muted'">주</span>
              <span class="text-[11px] text-ink-faint">+{{ geoMarkup.state.toLocaleString() }}P</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition-colors"
              :class="adForm.geo_scope==='all' ? 'border-amber-400 bg-amber-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="all" v-model="adForm.geo_scope" class="accent-amber-500" />
              <span :class="adForm.geo_scope==='all' ? 'text-amber-700 font-bold' : 'text-ink-muted'">전국</span>
              <span class="text-[11px] text-ink-faint">+{{ (geoMarkup.state + geoMarkup.national).toLocaleString() }}P</span>
            </label>
          </div>
          <div class="mt-2 text-xs text-ink-faint">
            {{ adForm.geo_scope === 'city' ? '특정 시티만 타겟 (가장 저렴)' : adForm.geo_scope === 'county' ? '카운티 전체 타겟 (기본가)' : adForm.geo_scope === 'state' ? '주 전체 타겟' : '미국 전국 타겟' }}
          </div>
        </div>

        <!-- Zip Code 조회 (카운티/주 선택 시) -->
        <div v-if="adForm.geo_scope !== 'all'" class="border-t border-gray-100 pt-3">
          <div class="text-xs font-bold text-ink-light mb-2 flex items-center gap-1"><AppIcon name="mail" :size="13" class="text-amber-600" />Zip Code 조회</div>
          <div class="flex gap-2">
            <input v-model="zipInput" @input="onZipInput" placeholder="Zip Code (5자리)" maxlength="5" class="input-soft flex-1" />
            <button @click="lookupZip" :disabled="zipLoading" class="btn-primary text-xs px-4 py-2">{{ zipLoading ? '...' : '조회' }}</button>
          </div>
          <div v-if="zipResult" class="mt-2 text-xs text-green-600 font-bold flex items-center gap-1">
            <AppIcon name="check" :size="13" />{{ adForm.geo_scope === 'city' ? zipResult.city + ', ' + zipResult.state : adForm.geo_scope === 'county' ? zipResult.county + ', ' + zipResult.state : zipResult.state }} — {{ adForm.geo_scope === 'city' ? '시티' : adForm.geo_scope === 'county' ? '카운티' : '주' }} 타겟 적용됨
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Step 3: 슬롯 등급 선택 ═══ -->
    <div class="card p-5 mb-5">
      <h2 class="font-bold text-ink text-sm mb-3 flex items-center gap-2"><span class="icon-chip w-6 h-6 bg-amber-50 text-amber-600 text-xs font-black">3</span>광고 등급 · 슬롯 선택</h2>

      <div v-if="!selectedSub" class="text-center py-8 text-xs text-ink-muted">먼저 Step 1에서 페이지를 선택해 주세요</div>

      <div v-else-if="!pageSlotConfig.left_slots && !pageSlotConfig.right_slots" class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-300">
        <div class="icon-chip w-12 h-12 bg-gray-100 text-gray-300 mx-auto mb-2"><AppIcon name="alert-circle" :size="24" :stroke-width="1.5" /></div>
        <div class="text-sm font-bold text-ink-light">이 페이지에는 광고 슬롯이 설정되지 않았습니다</div>
        <div class="text-xs text-ink-muted mt-1">관리자 페이지에서 좌/우 슬롯 수를 1 이상으로 설정해야 노출됩니다</div>
      </div>

      <template v-else>
        <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-gray-50">
          <div class="bg-gradient-to-r from-[#FF8A53] to-[#F2570F] h-8 flex items-center px-4">
            <span class="text-xs font-black text-white">AwesomeKorean — {{ selectedPageLabel }}</span>
          </div>
          <div class="grid grid-cols-12 gap-2 p-3 min-h-[320px]">
            <!-- 왼쪽 사이드바 -->
            <div v-if="pageSlotConfig.left_slots > 0" class="col-span-3 space-y-2">
              <div class="bg-white rounded-lg border border-gray-100 p-2"><div class="text-[11px] font-bold text-ink-faint flex items-center gap-1"><AppIcon name="list" :size="10" />카테고리</div></div>

              <div v-if="pageSlotConfig.left_slots >= 1" @click="selectSlot('left', 1, 'premium')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('left',1) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-yellow-400 bg-yellow-50/50 hover:border-amber-400'">
                <div class="mb-0.5 flex justify-center" :class="isSelected('left',1) ? 'text-amber-600' : 'text-yellow-600'"><AppIcon :name="isSelected('left',1) ? 'check' : 'trophy'" :size="14" /></div>
                <div class="text-[11px] font-black text-yellow-700">프리미엄 A</div>
                <div class="text-[11px] text-ink-muted">고정 독점 · 200×140</div>
                <div class="text-[11px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-ink-faint line-through font-normal mr-1">{{ slotOriginalPrice('left','premium').toLocaleString() }}</span>{{ slotMinPrice('left','premium').toLocaleString() }}P/월
                </div>
              </div>

              <div v-if="pageSlotConfig.left_slots >= 2" @click="selectSlot('left', 2, 'standard')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('left',2) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-blue-300 bg-blue-50/50 hover:border-amber-400'">
                <div class="mb-0.5 flex justify-center" :class="isSelected('left',2) ? 'text-amber-600' : 'text-blue-500'"><AppIcon :name="isSelected('left',2) ? 'check' : 'star'" :size="14" /></div>
                <div class="text-[11px] font-black text-blue-700">스탠다드 A</div>
                <div class="text-[11px] text-ink-muted">고정 독점 · 200×140</div>
                <div class="text-[11px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-ink-faint line-through font-normal mr-1">{{ slotOriginalPrice('left','standard').toLocaleString() }}</span>{{ slotMinPrice('left','standard').toLocaleString() }}P/월
                </div>
              </div>
            </div>

            <!-- 메인 -->
            <div :class="mainColSpan">
              <div class="bg-white rounded-lg border border-gray-100 p-3 h-full flex flex-col gap-2">
                <div class="text-xs text-gray-300 font-bold text-center">메인 콘텐츠 영역 (리스트 아이템)</div>
                <div class="h-2 bg-gray-100 rounded w-full"></div>
                <div class="h-2 bg-gray-100 rounded w-full"></div>

                <!-- 📝 텍스트 인라인 슬롯 (리스트 중간) -->
                <div @click="selectSlot('inline-text', 1, 'text')"
                  class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                  :class="isSelected('inline-text',1) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-purple-300 bg-purple-50/50 hover:border-amber-400'">
                  <div class="mb-0.5 flex justify-center" :class="isSelected('inline-text',1) ? 'text-amber-600' : 'text-purple-500'"><AppIcon :name="isSelected('inline-text',1) ? 'check' : 'edit'" :size="14" /></div>
                  <div class="text-xs font-black text-purple-700">텍스트 인라인</div>
                  <div class="text-[11px] text-ink-muted">상호+전화+설명 · 마퀴 · 리스트 중간</div>
                  <div class="text-[11px] font-bold text-red-600 mt-0.5">
                    <span v-if="adDiscountPct" class="text-ink-faint line-through font-normal mr-1">{{ slotOriginalPrice('inline-text','text').toLocaleString() }}</span>{{ slotMinPrice('inline-text','text').toLocaleString() }}P/월
                  </div>
                </div>

                <div class="h-2 bg-gray-100 rounded w-full"></div>
                <div class="h-2 bg-gray-100 rounded w-full"></div>
              </div>
            </div>

            <!-- 오른쪽 사이드바 -->
            <div v-if="pageSlotConfig.right_slots > 0" class="col-span-3 space-y-2">
              <div class="bg-white rounded-lg border border-gray-100 p-2"><div class="text-[11px] font-bold text-ink-faint flex items-center gap-1"><AppIcon name="flame" :size="10" />인기글</div></div>

              <div v-if="pageSlotConfig.right_slots >= 1" @click="selectSlot('right', 1, 'premium')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('right',1) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-yellow-400 bg-yellow-50/50 hover:border-amber-400'">
                <div class="mb-0.5 flex justify-center" :class="isSelected('right',1) ? 'text-amber-600' : 'text-yellow-600'"><AppIcon :name="isSelected('right',1) ? 'check' : 'trophy'" :size="14" /></div>
                <div class="text-[11px] font-black text-yellow-700">프리미엄 B</div>
                <div class="text-[11px] text-ink-muted">고정 독점 · 300×210</div>
                <div class="text-[11px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-ink-faint line-through font-normal mr-1">{{ slotOriginalPrice('right','premium').toLocaleString() }}</span>{{ slotMinPrice('right','premium').toLocaleString() }}P/월
                </div>
              </div>

              <div v-if="pageSlotConfig.right_slots >= 2" @click="selectSlot('right', 2, 'standard')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('right',2) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-blue-300 bg-blue-50/50 hover:border-amber-400'">
                <div class="mb-0.5 flex justify-center" :class="isSelected('right',2) ? 'text-amber-600' : 'text-blue-500'"><AppIcon :name="isSelected('right',2) ? 'check' : 'star'" :size="14" /></div>
                <div class="text-[11px] font-black text-blue-700">스탠다드 B</div>
                <div class="text-[11px] text-ink-muted">고정 독점 · 300×210</div>
                <div class="text-[11px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-ink-faint line-through font-normal mr-1">{{ slotOriginalPrice('right','standard').toLocaleString() }}</span>{{ slotMinPrice('right','standard').toLocaleString() }}P/월
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 등급 설명 (각 슬롯은 1명 고정 독점) -->
        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-2"><span class="font-bold text-yellow-700 inline-flex items-center gap-1"><AppIcon name="trophy" :size="12" />프리미엄 (A/B)</span><br>데스크톱 고정 독점. 모바일 랜덤 회전 가중치 높음 (35% 각).</div>
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-2"><span class="font-bold text-blue-700 inline-flex items-center gap-1"><AppIcon name="star" :size="12" />스탠다드 (A/B)</span><br>데스크톱 고정 독점. 모바일 랜덤 회전 가중치 낮음 (15% 각).</div>
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-2"><span class="font-bold text-purple-700 inline-flex items-center gap-1"><AppIcon name="edit" :size="12" />텍스트 인라인</span><br>이미지 불필요. 리스트 페이지 페이지네이션 위에만 노출.</div>
        </div>
        <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg p-2 text-xs text-amber-800">
          <AppIcon name="info" :size="13" class="inline-block" style="vertical-align:-2px" /> <strong>노출 규칙:</strong> 데스크톱 = 좌우 사이드바 각 위치 1명 고정. 모바일 = 프리미엄A/B·스탠다드A/B 4개가 리스트 중간 배너 한 자리에서 35:35:15:15 가중 랜덤 회전. 상세 페이지에서는 텍스트 인라인이 없고 댓글↔페이지네이션 사이에 4광고 랜덤 배너만 나옵니다.
        </div>
      </template>
    </div>

    <!-- ═══ Step 4: 상세 설정 ═══ -->
    <Transition name="slide">
      <div v-if="selectedSlot" class="card p-5 mb-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-ink flex items-center gap-2"><span class="icon-chip w-6 h-6 bg-amber-50 text-amber-600 text-xs font-black">4</span>{{ tierLabels[selectedSlot.tier] }} 광고 설정</h2>
          <button @click="selectedSlot=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="16" /></button>
        </div>
        <div class="space-y-4">
          <!-- 텍스트 인라인 선택 시 전용 필드 -->
          <template v-if="isTextAd">
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-[11px] text-purple-700">
              <AppIcon name="edit" :size="12" class="inline-block" style="vertical-align:-2px" /> <strong>텍스트 인라인 광고</strong> — 이미지 없이 상호 · 전화 · 한 줄 설명만 입력. 리스트 중간에 마퀴로 노출됩니다.
            </div>
            <div>
              <label class="input-label">상호 *</label>
              <input v-model="adForm.title" @input="saveDraft" maxlength="30" class="input-soft" placeholder="예: 💈 강남미용실" />
              <div class="text-xs text-ink-faint mt-0.5">{{ adForm.title.length }}/30 · 이모지를 앞에 붙이면 눈에 잘 띕니다</div>
            </div>
            <div>
              <label class="input-label">전화번호</label>
              <input v-model="adForm.phone" @input="saveDraft" maxlength="20" class="input-soft" placeholder="예: 770-555-1234" />
            </div>
            <div>
              <label class="input-label">한 줄 설명 *</label>
              <input v-model="adForm.description" @input="saveDraft" maxlength="120" class="input-soft" placeholder="예: 한인 전용 $30부터 · 첫방문 20% 할인 · Duluth GA" />
              <div class="text-xs text-ink-faint mt-0.5">{{ (adForm.description || '').length }}/120</div>
            </div>
            <!-- 실시간 미리보기 — 데스크톱 + 모바일 실제 사이즈 -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-bold text-ink-light inline-flex items-center gap-1"><AppIcon name="search" :size="13" class="text-amber-600" />실시간 미리보기 (실제 노출 크기)</label>
                <!-- 속도 조절 -->
                <div class="flex items-center gap-1">
                  <span class="text-xs text-ink-muted mr-1">속도:</span>
                  <button v-for="s in speedOptions" :key="s.value" type="button"
                    @click="previewSpeed = s.value"
                    :class="previewSpeed === s.value ? 'bg-amber-400 text-white border-amber-400' : 'bg-white text-ink-light border-gray-200 hover:border-amber-300'"
                    class="text-xs font-bold px-2 py-0.5 border rounded-full transition-colors">
                    {{ s.label }}
                  </button>
                </div>
              </div>

              <!-- 🖥️ 데스크톱 (약 640px, 리스트 중앙 영역 폭) -->
              <div class="mb-3">
                <div class="flex items-center gap-2 text-xs text-ink-muted mb-1">
                  <span class="bg-gray-100 px-1.5 py-0.5 rounded font-bold inline-flex items-center gap-1"><AppIcon name="monitor" :size="11" />데스크톱</span>
                  <span class="text-ink-faint">리스트 중앙 영역 폭 ≈ 640px</span>
                </div>
                <div class="border border-dashed border-gray-300 rounded-lg p-2 bg-gray-50" style="width: 640px; max-width: 100%; overflow: hidden;">
                  <TextInlineAd :manualAd="textPreviewAd" :autoLoad="false" :speed="previewSpeed" />
                </div>
              </div>

              <!-- 📱 모바일 (375px, iPhone 기본) -->
              <div>
                <div class="flex items-center gap-2 text-xs text-ink-muted mb-1">
                  <span class="bg-gray-100 px-1.5 py-0.5 rounded font-bold inline-flex items-center gap-1"><AppIcon name="phone" :size="11" />모바일</span>
                  <span class="text-ink-faint">iPhone 기본 폭 ≈ 375px</span>
                </div>
                <div class="border border-dashed border-gray-300 rounded-lg p-2 bg-gray-50" style="width: 375px; max-width: 100%; overflow: hidden;">
                  <TextInlineAd :manualAd="textPreviewAd" :autoLoad="false" :speed="previewSpeed" />
                </div>
              </div>

              <div class="text-xs text-ink-faint mt-2">
                <AppIcon name="info" :size="12" class="inline-block" style="vertical-align:-2px" /> 긴 텍스트는 마퀴로 좌→우 스크롤, 짧으면 좌우 왕복 애니메이션으로 노출됩니다. 실제 노출은 <strong>느림</strong> 기본입니다.
              </div>
            </div>
          </template>

          <!-- 이미지 광고 (기존) -->
          <template v-else>
            <div>
              <label class="input-label">광고 제목</label>
              <input v-model="adForm.title" @input="saveDraft" class="input-soft" placeholder="광고 이름" />
            </div>
            <div>
              <label class="input-label">광고 이미지 <span class="text-amber-600 font-normal">(권장: {{ recommendedSize }})</span></label>
              <input ref="fileInput" type="file" accept="image/*" @change="onImageChange" class="input-soft" />
              <div v-if="imagePreview" class="mt-2 border border-gray-100 rounded-lg p-3 bg-gray-50">
                <img :src="imagePreview" class="max-h-40 rounded-lg border border-gray-100 mx-auto" />
                <div class="mt-2 text-center">
                  <div class="text-xs text-ink-light">업로드 이미지: <span class="font-bold">{{ imgWidth }}×{{ imgHeight }}px</span></div>
                  <div v-if="imgSizeOk" class="text-xs text-green-600 font-bold mt-1 inline-flex items-center gap-1"><AppIcon name="check" :size="13" />권장 사이즈와 일치합니다</div>
                  <div v-else-if="imgRatioOk" class="text-xs text-amber-600 font-bold mt-1 inline-flex items-center gap-1"><AppIcon name="alert-circle" :size="13" />비율은 맞지만 사이즈가 다릅니다 (자동 조정됨)</div>
                  <div v-else class="text-xs text-red-600 font-bold mt-1 inline-flex items-center gap-1"><AppIcon name="alert-circle" :size="13" />권장 비율({{ recommendedRatio }})과 다릅니다 — 이미지가 잘릴 수 있습니다</div>
                  <div class="flex justify-center gap-2 mt-2">
                    <button @click="confirmImage" class="btn-primary text-xs px-4 py-1.5">이대로 사용</button>
                    <button @click="resetImage" class="btn-secondary text-xs px-4 py-1.5">다시 업로드</button>
                  </div>
                </div>
              </div>
              <div v-if="imageConfirmed" class="mt-1 text-xs text-green-600 font-bold inline-flex items-center gap-1"><AppIcon name="check" :size="13" />이미지 확정됨 ({{ imgWidth }}×{{ imgHeight }}px)</div>
            </div>
          </template>
          <div>
            <label class="input-label">클릭 시 URL (선택)</label>
            <div class="flex gap-2">
              <input v-model="adForm.link_url" @input="saveDraft" class="input-soft flex-1" placeholder="awesomekorean.com 또는 https://..." />
              <button v-if="adForm.link_url" @click="previewUrl" type="button" class="btn-soft text-xs px-3 py-2 flex-shrink-0">확인</button>
            </div>
            <div v-if="urlConfirmed" class="mt-1 text-xs text-green-600 font-bold inline-flex items-center gap-1"><AppIcon name="check" :size="13" />{{ normalizedUrl }} 확정됨</div>
          </div>

          <!-- 입찰 금액 (자동 계산) -->
          <div class="border-2 border-amber-200 rounded-xl p-4 bg-amber-50/50">
            <label class="text-xs font-bold text-amber-800 mb-2 inline-flex items-center gap-1"><AppIcon name="coins" :size="13" />월간 입찰 금액</label>
            <!-- 가격 산출 내역 -->
            <div class="bg-white rounded-lg p-3 mb-3 text-xs space-y-1 border border-gray-100">
              <div class="flex justify-between">
                <span class="text-ink-muted">슬롯: {{ posLabels[selectedSlot.position] }} {{ tierLabels[selectedSlot.tier] }}</span>
                <span class="font-bold">{{ basePricePerSlot.toLocaleString() }}P</span>
              </div>
              <div v-if="isTextAd" class="flex justify-between text-purple-600 bg-purple-50 -mx-1 px-1 py-0.5 rounded">
                <span class="font-bold inline-flex items-center gap-1"><AppIcon name="edit" :size="12" />텍스트 인라인 할인 적용 중</span>
                <span class="text-[11px]">시티 -200 · 카운티 0 · 주 +200 · 전국 +400</span>
              </div>
              <div class="flex justify-between">
                <span class="text-ink-muted">지역 {{ geoExtra >= 0 ? '추가금' : '할인' }}</span>
                <span class="font-bold" :class="geoExtra < 0 ? 'text-red-500' : ''">{{ geoExtra >= 0 ? '+' : '' }}{{ geoExtra.toLocaleString() }}P</span>
              </div>
              <div v-if="adDiscountPct" class="flex justify-between text-red-600">
                <span class="font-bold inline-flex items-center gap-1"><AppIcon name="sparkles" :size="13" />{{ adPromotion?.title }}</span>
                <span class="font-bold">-{{ adDiscountPct }}%</span>
              </div>
              <div class="flex justify-between border-t border-gray-100 pt-1 mt-1">
                <span class="font-bold text-amber-800">최소 입찰가</span>
                <span class="font-black text-amber-800 flex items-baseline gap-2">
                  <span v-if="adDiscountPct" class="text-ink-faint line-through text-xs font-normal">{{ totalMinBidOriginal.toLocaleString() }}P</span>
                  {{ totalMinBid.toLocaleString() }}P
                </span>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <input type="number" v-model.number="adForm.bid_amount" :min="totalMinBid" step="100" @input="saveDraft"
                class="flex-1 border-2 border-amber-400 rounded-xl px-4 py-3 text-lg font-black text-amber-800 text-center outline-none focus:ring-4 focus:ring-amber-400/10" />
              <span class="text-lg font-black text-amber-700">P</span>
            </div>
            <div class="mt-2 text-xs">
              <span v-if="hasEnough" class="text-green-600 inline-flex items-center gap-1">보유: {{ (auth.user?.points||0).toLocaleString() }}P <AppIcon name="check" :size="13" /></span>
              <span v-else class="text-red-600 inline-flex items-center gap-1">
                보유: {{ (auth.user?.points||0).toLocaleString() }}P <AppIcon name="alert-circle" :size="13" />
                <button @click="goPointShop" class="ml-2 inline-flex items-center gap-0.5 bg-red-500 text-white px-3 py-1 rounded-lg text-xs font-bold hover:bg-red-600 transition-colors">충전 <AppIcon name="arrow-right" :size="11" /></button>
              </span>
            </div>
          </div>

          <button @click="submitAd" :disabled="submitting || !canSubmit"
            class="w-full py-3 rounded-xl font-bold text-sm transition disabled:opacity-50"
            :class="canSubmit ? 'bg-amber-400 text-white hover:bg-amber-500 shadow-btn' : 'bg-gray-200 text-gray-400'">
            {{ submitting ? '신청 중...' : `입찰 신청 (${(adForm.bid_amount||0).toLocaleString()}P)` }}
          </button>
        </div>
      </div>
    </Transition>

    <!-- ═══ 내 광고 ═══ -->
    <div class="card p-5">
      <h2 class="font-bold text-ink mb-4 flex items-center gap-2"><span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="list" :size="14" /></span>내 입찰 내역</h2>
      <div v-if="loading" class="text-center py-8 text-ink-muted text-sm">로딩중...</div>
      <div v-else-if="!myAds.length" class="text-center py-8 text-ink-muted text-sm">신청한 광고가 없습니다</div>
      <div v-else class="space-y-3">
        <div v-for="ad in myAds" :key="ad.id" class="border border-gray-100 rounded-xl p-3 flex gap-3">
          <div class="w-20 h-14 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
            <img :src="ad.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="statusClasses[ad.status]">{{ statusLabels[ad.status] }}</span>
              <span class="badge-purple">{{ posLabels[ad.position] }}{{ ad.slot_number }}</span>
              <span class="badge-primary font-bold">{{ (ad.bid_amount||0).toLocaleString() }}P</span>
            </div>
            <div class="text-sm font-bold text-ink truncate mt-0.5">{{ ad.title }}</div>
            <div class="text-xs text-ink-faint">{{ (ad.target_pages||[ad.page]).join(', ') }} · {{ ad.geo_scope!=='all'?ad.geo_value:'전국' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import { useModal } from '../../composables/useModal'
import TextInlineAd from '../../components/TextInlineAd.vue'
import AppIcon from '../../components/AppIcon.vue'
import { menuIcon } from '../../utils/menuIcons'
import axios from 'axios'

const siteStore = useSiteStore()

const props = defineProps({ embedded: { type: Boolean, default: false } })
const router = useRouter()
const auth = useAuthStore()
const { showAlert, showConfirm } = useModal()

const loading = ref(true)
const myAds = ref([])
const selectedSlot = ref(null)
const submitting = ref(false)
const imagePreview = ref(null)
const adImage = ref(null)
const zipInput = ref('')
const zipLoading = ref(false)
const zipResult = ref(null)
const urlConfirmed = ref(false)
const normalizedUrl = ref('')
const selectedSub = ref('')
const DRAFT_KEY = 'sk_ad_draft'

// 카운티 기본가 (관리자 설정에서 로드)
const basePrices = ref({
  // 데스크톱 사이드바 4슬롯 (각 1명 고정 독점)
  left_premium: 8000,    // 프리미엄 A
  left_standard: 5000,   // 스탠다드 A
  right_premium: 10000,  // 프리미엄 B
  right_standard: 7000,  // 스탠다드 B
  // 텍스트 인라인 (리스트 중앙 — 상세에는 노출 안됨)
  'inline-text_text': 1000,
})
// 지역별 추가금 (관리자 설정에서 로드)
const geoMarkup = ref({ cityDiscount: 1000, state: 2000, national: 3000 })

// 페이지별 슬롯 설정 (관리자 API에서 로드)
const pageConfigs = ref({})

const adForm = reactive({ title: '', link_url: '', bid_amount: 4000, geo_scope: 'county', geo_value: '', phone: '', description: '' })

const textPreviewAd = computed(() => ({
  id: 0,
  title: adForm.title || '📝 상호를 입력하세요',
  phone: adForm.phone,
  description: adForm.description || '설명을 입력하면 여기에 보입니다',
}))

// 미리보기 속도 (slow/normal/fast)
const previewSpeed = ref('slow')
const speedOptions = [
  { value: 'slow',   label: '느림' },
  { value: 'normal', label: '보통' },
  { value: 'fast',   label: '빠름' },
]

// 광고 노출 가능 서브 페이지 — 활성 메뉴 중 관리자 광고 슬롯이 > 0 인 것만
const subPages = computed(() => {
  const mc = siteStore.menuConfig
  if (!mc || !Array.isArray(mc)) return []
  return mc
    .filter(m => m.enabled !== false && !m.admin_only && m.key !== 'home')
    .filter(m => {
      const cfg = pageConfigs.value[m.key]
      if (!cfg) return false // 관리자가 명시적으로 슬롯 수를 설정해야 노출
      return (cfg.left_slots || 0) > 0 || (cfg.right_slots || 0) > 0
    })
    .map(m => ({ key: m.key, icon: m.icon, label: m.label }))
})

// 전국 페이지 (자동 geo_scope='all')
const nationalPages = ['home', 'community', 'qa', 'news', 'recipes', 'shorts', 'games', 'music']

const posLabels = { left: '좌측 A', right: '우측 B', 'inline-text': '리스트 중간' }
const tierLabels = { premium: '프리미엄', standard: '스탠다드', text: '텍스트 인라인' }
const isTextAd = computed(() => selectedSlot.value?.tier === 'text')
const statusLabels = { pending:'입찰대기', active:'게시중', rejected:'거절', expired:'만료', paused:'중지' }
const statusClasses = { pending:'bg-amber-100 text-amber-700', active:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700', expired:'bg-gray-200 text-gray-500', paused:'bg-gray-200 text-gray-500' }

const nextAuctionDate = computed(() => {
  const now = new Date()
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  return `${lastDay.getFullYear()}.${lastDay.getMonth()+1}.${lastDay.getDate()}`
})

// 선택된 페이지가 전국 페이지인지
const isNationalPage = computed(() => nationalPages.includes(selectedSub.value))

// 선택된 페이지의 라벨
const selectedPageLabel = computed(() => {
  if (selectedSub.value === 'home') return '홈'
  return subPages.value.find(s => s.key === selectedSub.value)?.label || selectedSub.value
})

// 선택된 페이지의 슬롯 설정 — 관리자 설정이 없으면 0/0 으로 취급 (명시적 활성화 필요)
const pageSlotConfig = computed(() => {
  const cfg = pageConfigs.value[selectedSub.value]
  if (cfg) return cfg
  return { left_slots: 0, right_slots: 0 }
})

// 메인 콘텐츠 컬럼 span (사이드바 유무에 따라 동적)
const mainColSpan = computed(() => {
  const hasLeft = pageSlotConfig.value.left_slots > 0
  const hasRight = pageSlotConfig.value.right_slots > 0
  if (hasLeft && hasRight) return 'col-span-6'
  if (hasLeft || hasRight) return 'col-span-9'
  return 'col-span-12'
})

// 슬롯별 카운티 기본가
function getBasePrice(position, tier) {
  return basePrices.value[`${position}_${tier}`] || 4000
}

// 텍스트 인라인 전용 가중치 (작은 단위: 시티 -200, 카운티 0, 주 +200, 전국 +400)
const textGeoMarkup = { city: -200, county: 0, state: 200, national: 400 }

/** 주어진 tier 기반 지역 가중치 계산 (슬롯별 독립 가격 표시용)
 *  - tier === 'text' → 작은 가중치 (±200/±400)
 *  - 그 외 (premium/standard/economy) → 이미지 광고 가중치 */
function computeGeoExtra(tier) {
  if (!selectedSub.value) return 0

  if (tier === 'text') {
    if (isNationalPage.value || adForm.geo_scope === 'all') return textGeoMarkup.national
    if (adForm.geo_scope === 'state') return textGeoMarkup.state
    if (adForm.geo_scope === 'county') return textGeoMarkup.county
    if (adForm.geo_scope === 'city') return textGeoMarkup.city
    return 0
  }

  // 이미지 광고 (기존): 전국 페이지는 자동 전국이지만 추가금 포함
  if (isNationalPage.value) {
    return geoMarkup.value.state + geoMarkup.value.national
  }
  // 지역별 페이지
  if (adForm.geo_scope === 'city') return -geoMarkup.value.cityDiscount
  if (adForm.geo_scope === 'county') return 0
  if (adForm.geo_scope === 'state') return geoMarkup.value.state
  if (adForm.geo_scope === 'all') return geoMarkup.value.state + geoMarkup.value.national
  return 0
}

// 현재 선택된 슬롯의 지역 가중치 (4️⃣ 섹션 가격 산출용)
const geoExtra = computed(() => {
  if (!selectedSlot.value) return 0
  return computeGeoExtra(selectedSlot.value.tier)
})

// 슬롯 1개의 기본가
const basePricePerSlot = computed(() => {
  if (!selectedSlot.value) return 0
  return getBasePrice(selectedSlot.value.position, selectedSlot.value.tier)
})

// 현재 활성 광고 할인 이벤트
const adPromotion = ref(null)
const adDiscountPct = computed(() => adPromotion.value?.discount_pct || 0)

function fmtPromoEnd(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

function applyDiscount(price) {
  if (!adDiscountPct.value) return price
  return Math.round(price * (100 - adDiscountPct.value) / 100)
}

// 원가(할인 전) — 취소선 표시용 (각 슬롯은 자기 tier 기준 가중치로 계산)
function slotOriginalPrice(position, tier) {
  return getBasePrice(position, tier) + computeGeoExtra(tier)
}

// 총 최소 입찰가 = (기본가 + 지역 추가금) × 할인 적용
const totalMinBid = computed(() => applyDiscount(basePricePerSlot.value + geoExtra.value))
const totalMinBidOriginal = computed(() => basePricePerSlot.value + geoExtra.value)

// 슬롯 선택 UI에 표시할 가격 (할인 적용) — 각 슬롯은 자기 tier 기준 가중치로 독립 계산
function slotMinPrice(position, tier) {
  return applyDiscount(getBasePrice(position, tier) + computeGeoExtra(tier))
}

const hasEnough = computed(() => (auth.user?.points || 0) >= (adForm.bid_amount || 0))
const canSubmit = computed(() => {
  if (!selectedSub.value) return false
  if (!selectedSlot.value) return false
  if (!adForm.title) return false
  // 텍스트 인라인은 이미지 불필요, 설명 필수
  if (isTextAd.value) {
    if (!adForm.description?.trim()) return false
  } else {
    if (!adImage.value || !imageConfirmed.value) return false
  }
  if (adForm.bid_amount < totalMinBid.value) return false
  if (!hasEnough.value) return false
  if (!isNationalPage.value && adForm.geo_scope !== 'all' && !adForm.geo_value) return false
  return true
})

function isSelected(pos, slot) { return selectedSlot.value?.position === pos && selectedSlot.value?.slot === slot }

function selectSlot(position, slot, tier) {
  selectedSlot.value = { position, slot, tier }
  adForm.bid_amount = getBasePrice(position, tier) + computeGeoExtra(tier)
  saveDraft()
}

// 페이지 변경 시 geo_scope 리셋 + 슬롯 선택 해제
watch(selectedSub, (val) => {
  if (!val) return
  if (isNationalPage.value) {
    adForm.geo_scope = 'all'
    adForm.geo_value = ''
  } else {
    adForm.geo_scope = 'county'
    adForm.geo_value = ''
  }
  // 슬롯 선택 해제 (새 페이지의 슬롯 설정이 다를 수 있음)
  selectedSlot.value = null
  zipResult.value = null
  saveDraft()
})

// 지역 변경 시 최소 입찰가로 리셋
watch(() => adForm.geo_scope, () => {
  if (selectedSlot.value) {
    adForm.bid_amount = totalMinBid.value
  }
  // 전국 선택 시 geo_value 초기화
  if (adForm.geo_scope === 'all') {
    adForm.geo_value = ''
  }
  saveDraft()
})

const fileInput = ref(null)
const imgWidth = ref(0)
const imgHeight = ref(0)
const imageConfirmed = ref(false)

const recommendedSize = computed(() => selectedSlot.value?.position === 'left' ? '200×140px' : '300×210px')
const recommendedW = computed(() => selectedSlot.value?.position === 'left' ? 200 : 300)
const recommendedH = computed(() => selectedSlot.value?.position === 'left' ? 150 : 250)
const recommendedRatio = computed(() => `${recommendedW.value}:${recommendedH.value}`)

const imgSizeOk = computed(() => imgWidth.value === recommendedW.value && imgHeight.value === recommendedH.value)
const imgRatioOk = computed(() => {
  if (!imgWidth.value || !imgHeight.value) return false
  const target = recommendedW.value / recommendedH.value
  const actual = imgWidth.value / imgHeight.value
  return Math.abs(target - actual) < 0.05
})

function onImageChange(e) {
  const f = e.target.files[0]
  if (!f) return
  imageConfirmed.value = false
  adImage.value = f
  imagePreview.value = URL.createObjectURL(f)
  const img = new Image()
  img.onload = () => { imgWidth.value = img.naturalWidth; imgHeight.value = img.naturalHeight }
  img.src = imagePreview.value
}

function confirmImage() { imageConfirmed.value = true }
function resetImage() {
  adImage.value = null; imagePreview.value = null; imageConfirmed.value = false
  imgWidth.value = 0; imgHeight.value = 0
  if (fileInput.value) fileInput.value.value = ''
}

// URL 정규화 + 미리보기 팝업
function normalizeUrl(raw) {
  let url = raw.trim()
  if (!url) return ''
  if (!/^https?:\/\//i.test(url)) url = 'https://' + url
  return url
}

async function previewUrl() {
  const url = normalizeUrl(adForm.link_url)
  if (!url) return
  normalizedUrl.value = url

  const ok = await showConfirm(
    `이 주소가 맞습니까?\n\n${url}\n\n맞으면 "확인", 다시 입력하려면 "취소"`,
    'URL 확인'
  )
  if (ok) {
    adForm.link_url = url
    urlConfirmed.value = true
    saveDraft()
  } else {
    urlConfirmed.value = false
  }
}
function onZipInput() { if (zipInput.value.length === 5) lookupZip() }

async function lookupZip() {
  if (zipInput.value.length !== 5) return; zipLoading.value = true
  try {
    const r = await fetch(`https://api.zippopotam.us/us/${zipInput.value}`)
    if (!r.ok) throw 0; const d = await r.json(); const p = d.places?.[0]
    if (p) {
      zipResult.value = { state: p['state abbreviation'], city: p['place name'] }
      // geo_scope에 따라 값 설정
      if (adForm.geo_scope === 'city') adForm.geo_value = p['place name']
      else if (adForm.geo_scope === 'county') adForm.geo_value = p['place name'] // county는 zip API에서 city로 대체
      else if (adForm.geo_scope === 'state') adForm.geo_value = p['state abbreviation']
      else adForm.geo_value = ''
      saveDraft()
    }
  } catch { showAlert('유효하지 않은 Zip Code', '오류') }
  zipLoading.value = false
}

function saveDraft() {
  try {
    localStorage.setItem(DRAFT_KEY, JSON.stringify({
      selectedSub: selectedSub.value,
      selectedSlot: selectedSlot.value,
      adForm: { ...adForm },
      zipInput: zipInput.value,
      ts: Date.now()
    }))
  } catch {}
}

function loadDraft() {
  try {
    const r = localStorage.getItem(DRAFT_KEY)
    if (!r) return
    const d = JSON.parse(r)
    if (Date.now() - d.ts > 86400000) { localStorage.removeItem(DRAFT_KEY); return }
    selectedSub.value = d.selectedSub || ''
    selectedSlot.value = d.selectedSlot || null
    if (d.adForm) Object.assign(adForm, d.adForm)
    zipInput.value = d.zipInput || ''
  } catch {}
}

function clearDraft() { try { localStorage.removeItem(DRAFT_KEY) } catch {} }
watch([selectedSub, selectedSlot], saveDraft, { deep: true })

function goPointShop() { saveDraft(); router.push('/dashboard?tab=points') }

async function submitAd() {
  if (!canSubmit.value || submitting.value) return
  if (!hasEnough.value) { const ok = await showConfirm(`포인트 부족 (보유: ${(auth.user?.points || 0).toLocaleString()}P)\n충전?`, '부족'); if (ok) goPointShop(); return }
  submitting.value = true

  // 텍스트 인라인은 별도 엔드포인트 (FormData 없이 JSON)
  if (isTextAd.value) {
    try {
      const { data } = await axios.post('/api/banners/text-apply', {
        title: adForm.title,
        phone: adForm.phone || null,
        description: adForm.description,
        link_url: adForm.link_url || null,
        page: selectedSub.value,
        days: 30, // AdApply 는 월간 경매 기준
        bid_amount: adForm.bid_amount,
      })
      showAlert(data.message, '텍스트 광고 신청')
      selectedSlot.value = null
      Object.assign(adForm, { title: '', link_url: '', bid_amount: 1000, geo_scope: 'county', geo_value: '', phone: '', description: '' })
      selectedSub.value = ''; clearDraft()
      await loadMyAds()
    } catch (e) {
      const m = e.response?.data?.message || '실패'
      if (m.includes('부족')) { const ok = await showConfirm(m + '\n충전?', '부족'); if (ok) goPointShop() } else showAlert(m, '오류')
    }
    submitting.value = false
    return
  }

  // 이미지 광고 (기존)
  const fd = new FormData()
  fd.append('title', adForm.title)
  fd.append('link_url', adForm.link_url || '')
  fd.append('page', selectedSub.value)
  fd.append('target_pages', JSON.stringify([selectedSub.value]))

  // geo 설정
  if (isNationalPage.value) {
    fd.append('geo_scope', 'all')
    fd.append('geo_value', '')
  } else {
    fd.append('geo_scope', adForm.geo_scope)
    fd.append('geo_value', adForm.geo_value)
  }

  fd.append('position', selectedSlot.value.position)
  fd.append('slot_number', selectedSlot.value.slot)
  fd.append('tier', selectedSlot.value.tier)
  fd.append('bid_amount', adForm.bid_amount)
  if (adImage.value) fd.append('image', adImage.value)
  try {
    const { data } = await axios.post('/api/banners/apply', fd)
    showAlert(data.message, '입찰 신청')
    selectedSlot.value = null
    Object.assign(adForm, { title: '', link_url: '', bid_amount: 4000, geo_scope: 'county', geo_value: '', phone: '', description: '' })
    adImage.value = null; imagePreview.value = null; selectedSub.value = ''; clearDraft()
    await loadMyAds()
  } catch (e) {
    const m = e.response?.data?.message || '실패'
    if (m.includes('부족')) { const ok = await showConfirm(m + '\n충전?', '부족'); if (ok) goPointShop() } else showAlert(m, '오류')
  }
  submitting.value = false
}

async function loadMyAds() { try { const { data } = await axios.get('/api/banners/my'); myAds.value = data.data || [] } catch {}; loading.value = false }

async function loadPrices() {
  try {
    const { data } = await axios.get('/api/ad-settings/public')
    if (data.data?.slot_min_prices) basePrices.value = { ...basePrices.value, ...data.data.slot_min_prices }
    // 활성 광고 할인 이벤트 별도 로드
    try {
      const promoRes = await axios.get('/api/pricing-promotions/active')
      adPromotion.value = promoRes.data?.data?.ad || null
    } catch {}
    if (data.data?.geo_markup) geoMarkup.value = { ...geoMarkup.value, ...data.data.geo_markup }
    // 페이지별 슬롯 설정: 서버는 top-level 에 페이지 키 (home, community...) 로 응답하므로
    // 특수 키 제외하고 left_slots/right_slots 구조를 가진 것만 수집
    const SPECIAL = new Set(['slot_min_prices','geo_markup'])
    const cfgs = {}
    Object.entries(data.data || {}).forEach(([k, v]) => {
      if (SPECIAL.has(k)) return
      if (v && typeof v === 'object' && ('left_slots' in v || 'right_slots' in v)) {
        cfgs[k] = v
      }
    })
    if (Object.keys(cfgs).length) pageConfigs.value = cfgs
  } catch {}
}

onMounted(() => { siteStore.load(); loadMyAds(); loadPrices(); loadDraft() })
</script>
<style scoped>
.slide-enter-active,.slide-leave-active{transition:all .3s ease}
.slide-enter-from,.slide-leave-to{opacity:0;transform:translateY(-10px)}
</style>
