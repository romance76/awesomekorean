<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-800">사이트 설정</h1>
      <span class="text-xs text-gray-400">마지막 로드: {{ lastLoaded }}</span>
    </div>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast.show" :class="[
        'fixed top-5 right-5 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-lg text-white text-sm font-medium',
        toast.type === 'success' ? 'bg-green-500' : 'bg-red-500'
      ]">
        <span>{{ toast.type === 'success' ? '✓' : '✕' }}</span>
        {{ toast.message }}
      </div>
    </Transition>

    <!-- Tab Navigation -->
    <div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-xl overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'flex-shrink-0 px-4 py-2 rounded-lg text-sm font-medium transition-all',
          activeTab === tab.key
            ? 'bg-white text-blue-600 shadow-sm'
            : 'text-gray-500 hover:text-gray-700'
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else>
      <!-- ========================= TAB 1: 회사 정보 ========================= -->
      <div v-show="activeTab === 'company'">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h2 class="text-base font-semibold text-gray-800 mb-6">회사 정보</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">사이트명</label>
              <input v-model="company.site_name" type="text" class="input-field" placeholder="AwesomeKorean" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">사이트 부제목</label>
              <input v-model="company.site_subtitle" type="text" class="input-field" placeholder="미국 한인 커뮤니티" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">회사명</label>
              <input v-model="company.company_name" type="text" class="input-field" placeholder="AwesomeKorean Inc." />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">대표자명</label>
              <input v-model="company.ceo_name" type="text" class="input-field" placeholder="홍길동" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">사업자등록번호</label>
              <input v-model="company.business_number" type="text" class="input-field" placeholder="123-45-67890" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
              <input v-model="company.address" type="text" class="input-field" placeholder="Los Angeles, CA, USA" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">전화번호</label>
              <input v-model="company.phone" type="text" class="input-field" placeholder="+1-213-000-0000" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
              <input v-model="company.email" type="email" class="input-field" placeholder="admin@awesomekorean.com" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">설립일</label>
              <input v-model="company.founded_date" type="date" class="input-field" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Favicon URL</label>
              <input v-model="company.favicon_url" type="text" class="input-field" placeholder="/favicon.ico" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">메타 키워드</label>
              <input v-model="company.meta_keywords" type="text" class="input-field" placeholder="한인, 커뮤니티, 미국, LA" />
            </div>
          </div>

          <!-- Logo URL + Preview -->
          <div class="mt-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">로고 URL</label>
            <div class="flex items-center gap-3">
              <input v-model="company.logo_url" type="text" class="input-field flex-1" placeholder="/images/logo.png" />
              <div class="w-16 h-16 border border-gray-200 rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 flex-shrink-0">
                <img v-if="company.logo_url" :src="company.logo_url" alt="Logo Preview" class="w-full h-full object-contain" @error="logoError = true" />
                <span v-else class="text-xs text-gray-400">미리보기</span>
              </div>
            </div>
          </div>

          <!-- Meta Description -->
          <div class="mt-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">메타 설명 (SEO)</label>
            <textarea v-model="company.meta_description" rows="3" class="input-field resize-none" placeholder="미국 한인 커뮤니티 플랫폼..."></textarea>
          </div>

          <div class="mt-6 flex justify-end">
            <button @click="saveCompany" :disabled="saving" class="btn-primary">
              <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
              저장하기
            </button>
          </div>
        </div>
      </div>

      <!-- ========================= TAB 2: 사이트 설정 ========================= -->
      <div v-show="activeTab === 'site'">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h2 class="text-base font-semibold text-gray-800 mb-6">사이트 설정</h2>

          <!-- 회원 설정 -->
          <div class="mb-6">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">회원 설정</h3>
            <div class="space-y-4">
              <ToggleRow v-model="site.allow_signup" label="회원가입 허용" desc="신규 회원 가입을 허용합니다" />
              <ToggleRow v-model="site.require_email_verify" label="이메일 인증 필수" desc="가입 후 이메일 인증이 필요합니다" />
              <ToggleRow v-model="site.auto_approve" label="자동 승인" desc="가입 즉시 계정을 활성화합니다" />
              <ToggleRow v-model="site.allow_withdrawal" label="회원 탈퇴 허용" desc="회원이 스스로 탈퇴할 수 있습니다" />
              <div class="flex items-center gap-4">
                <label class="text-sm text-gray-700 w-48">최소 비밀번호 길이</label>
                <input v-model.number="site.min_password_length" type="number" min="6" max="32" class="input-field w-24" />
                <span class="text-sm text-gray-400">자리</span>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 pt-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">포인트 설정</h3>
            <div class="space-y-4">
              <ToggleRow v-model="site.enable_points" label="포인트 시스템 활성화" desc="포인트 적립/사용 기능을 활성화합니다" />
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">출석체크 포인트</label>
                  <input v-model.number="site.point_attendance" type="number" min="0" class="input-field" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">가입 포인트</label>
                  <input v-model.number="site.point_signup" type="number" min="0" class="input-field" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">게시글 포인트</label>
                  <input v-model.number="site.point_post" type="number" min="0" class="input-field" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">댓글 포인트</label>
                  <input v-model.number="site.point_comment" type="number" min="0" class="input-field" />
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 pt-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">파일 업로드 설정</h3>
            <div class="flex flex-col gap-4">
              <div class="flex items-center gap-4">
                <label class="text-sm text-gray-700 w-48">최대 업로드 파일 크기</label>
                <input v-model.number="site.max_upload_mb" type="number" min="1" max="100" class="input-field w-24" />
                <span class="text-sm text-gray-400">MB</span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">허용 파일 형식</label>
                <input v-model="site.allowed_file_types" type="text" class="input-field" placeholder="jpg,png,gif,pdf,doc" />
                <p class="text-xs text-gray-400 mt-1">쉼표로 구분하여 입력하세요</p>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 pt-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">점검 모드</h3>
            <ToggleRow v-model="site.maintenance_mode" label="점검 모드" desc="사이트를 점검 모드로 전환합니다" />
            <div v-if="site.maintenance_mode" class="mt-4 space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">점검 안내 문구</label>
                <textarea v-model="site.maintenance_reason" rows="2" class="input-field resize-none" placeholder="현재 시스템 점검 중입니다. 잠시 후 다시 이용해 주세요."></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">점검 종료 예정시각</label>
                <input v-model="site.maintenance_until" type="datetime-local" class="input-field" />
              </div>
            </div>
          </div>

          <div class="border-t border-gray-100 pt-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">외부 연동</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">구글 Analytics ID</label>
                <input v-model="site.google_analytics_id" type="text" class="input-field" placeholder="G-XXXXXXXXXX" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">카카오 API 키</label>
                <input v-model="site.kakao_api_key" type="text" class="input-field" placeholder="카카오 REST API 키" />
              </div>
            </div>
          </div>

          <div class="flex justify-end">
            <button @click="saveSite" :disabled="saving" class="btn-primary">
              <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
              저장하기
            </button>
          </div>
        </div>
      </div>

      <!-- ========================= TAB: 메뉴 관리 ========================= -->
      <div v-if="activeTab === 'menus'">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-base font-semibold text-gray-800">네비게이션 메뉴 관리</h2>
            <p class="text-xs text-gray-400 mt-0.5">순서, 표시여부, 로그인/관리자 권한을 한 곳에서 설정하세요</p>
          </div>
          <button @click="saveMenus" :disabled="menuSaving"
            class="btn-primary">
            {{ menuSaving ? '저장 중...' : '💾 저장' }}
          </button>
        </div>

        <!-- 통계 -->
        <div class="grid grid-cols-3 gap-4 mb-6">
          <div class="bg-blue-50 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-blue-600">{{ menuList.filter(m => m.enabled).length }}</div>
            <div class="text-xs text-blue-400">활성 메뉴</div>
          </div>
          <div class="bg-gray-50 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-gray-500">{{ menuList.filter(m => !m.enabled).length }}</div>
            <div class="text-xs text-gray-400">비활성 메뉴</div>
          </div>
          <div class="bg-red-50 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-red-500">{{ menuList.filter(m => m.login_required).length }}</div>
            <div class="text-xs text-red-400">로그인 필요</div>
          </div>
        </div>

        <!-- 헤더 행 -->
        <div class="flex items-center gap-3 px-4 py-2 text-xs text-gray-400 font-medium border-b border-gray-100 mb-2">
          <span class="w-6 text-center">#</span>
          <span class="w-7"></span>
          <span class="flex-1">메뉴</span>
          <span class="w-14 hidden sm:inline"></span>
          <span class="w-16 text-center">순서</span>
          <span class="w-16 text-center">로그인</span>
          <span class="w-16 text-center">관리자</span>
          <span class="w-14 text-center">뷰</span>
          <span class="w-12 text-center">표시</span>
        </div>

        <div class="space-y-2">
          <div v-for="(item, idx) in menuList" :key="item.key"
            class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-4 py-3 hover:border-blue-200 transition">
            <span class="w-6 text-center text-xs font-bold text-gray-300">{{ idx + 1 }}</span>
            <span class="text-xl w-7 text-center flex-shrink-0">{{ item.icon }}</span>
            <span class="flex-1 text-sm font-semibold text-gray-800">{{ item.label }}</span>
            <span class="text-xs text-gray-400 font-mono bg-gray-50 px-2 py-0.5 rounded hidden sm:inline">{{ item.key }}</span>
            <!-- Up/Down -->
            <div class="flex gap-1">
              <button @click="moveMenu(idx, -1)" :disabled="idx === 0"
                class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-blue-50 hover:text-blue-600 disabled:opacity-20 transition text-xs">▲</button>
              <button @click="moveMenu(idx, 1)" :disabled="idx === menuList.length - 1"
                class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-blue-50 hover:text-blue-600 disabled:opacity-20 transition text-xs">▼</button>
            </div>
            <!-- Login required -->
            <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer" title="로그인 필요">
              <input type="checkbox" v-model="item.login_required" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span class="hidden lg:inline">로그인</span>
            </label>
            <!-- Admin only -->
            <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer" title="관리자만">
              <input type="checkbox" v-model="item.admin_only" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
              <span class="hidden lg:inline">관리자</span>
            </label>
            <!-- 기본 뷰 -->
            <select v-model="item.defaultView" class="text-[10px] border rounded px-1 py-0.5 text-gray-500 w-14" title="기본 보기">
              <option value="list">☰</option>
              <option value="card">⊞</option>
            </select>
            <!-- Enable toggle -->
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="item.enabled" class="sr-only peer" />
              <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </div>
        </div>

        <div class="mt-4 flex justify-between items-center">
          <button @click="resetMenuOrder"
            class="btn-secondary text-xs">
            🔄 기본 순서로 리셋
          </button>
          <button @click="saveMenus" :disabled="menuSaving" class="btn-primary">
            {{ menuSaving ? '저장 중...' : '💾 메뉴 설정 저장' }}
          </button>
        </div>
      </div>

      <!-- ========================= TAB 3: 푸터 편집 ========================= -->
      <div v-show="activeTab === 'footer'">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
          <h2 class="text-base font-semibold text-gray-800 mb-6">푸터 편집</h2>

          <!-- Footer columns -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div v-for="(col, ci) in footer.columns" :key="ci" class="border border-gray-200 rounded-lg p-4">
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
              <button @click="addFooterLink(ci)" class="text-xs text-blue-500 hover:text-blue-700 font-medium">+ 링크 추가</button>
            </div>
          </div>

          <!-- Copyright -->
          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">저작권 문구</label>
            <input v-model="footer.copyright" type="text" class="input-field" placeholder="© 2024 AwesomeKorean Inc. All rights reserved." />
          </div>

          <!-- SNS Links -->
          <div class="mb-5">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">SNS 링크</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 w-24 flex-shrink-0">Facebook</span>
                <input v-model="footer.sns.facebook" type="text" class="input-field text-sm flex-1" placeholder="https://facebook.com/..." />
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 w-24 flex-shrink-0">Instagram</span>
                <input v-model="footer.sns.instagram" type="text" class="input-field text-sm flex-1" placeholder="https://instagram.com/..." />
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 w-24 flex-shrink-0">Twitter/X</span>
                <input v-model="footer.sns.twitter" type="text" class="input-field text-sm flex-1" placeholder="https://twitter.com/..." />
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 w-24 flex-shrink-0">YouTube</span>
                <input v-model="footer.sns.youtube" type="text" class="input-field text-sm flex-1" placeholder="https://youtube.com/..." />
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 w-24 flex-shrink-0">KakaoTalk</span>
                <input v-model="footer.sns.kakao" type="text" class="input-field text-sm flex-1" placeholder="카카오 채널 URL" />
              </div>
            </div>
          </div>

          <!-- Additional Text -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">하단 추가 텍스트</label>
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
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
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
                <span v-if="footer.sns.kakao" class="text-xs hover:text-white cursor-pointer">KakaoTalk</span>
              </div>
            </div>
            <p v-if="footer.additional_text" class="text-xs text-gray-600 mt-4">{{ footer.additional_text }}</p>
          </footer>
        </div>
      </div>

      <!-- ========================= TAB 4: 약관 관리 ========================= -->
      <div v-show="activeTab === 'terms'">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h2 class="text-base font-semibold text-gray-800 mb-5">약관 관리</h2>

          <!-- Sub tabs -->
          <div class="flex gap-1 mb-6 border-b border-gray-200">
            <button
              v-for="st in termsTabs"
              :key="st.key"
              @click="activeTermsTab = st.key"
              :class="[
                'px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
                activeTermsTab === st.key
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              ]"
            >
              {{ st.label }}
            </button>
          </div>

          <div v-for="st in termsTabs" :key="st.key" v-show="activeTermsTab === st.key">
            <!-- Toolbar -->
            <div class="flex items-center gap-1 mb-2 p-1 bg-gray-50 border border-gray-200 rounded-lg">
              <button @click="formatText(st.key, 'bold')" class="toolbar-btn font-bold">B</button>
              <button @click="formatText(st.key, 'italic')" class="toolbar-btn italic">I</button>
              <div class="w-px h-5 bg-gray-300 mx-1"></div>
              <button @click="formatText(st.key, 'h2')" class="toolbar-btn">H2</button>
              <button @click="formatText(st.key, 'h3')" class="toolbar-btn">H3</button>
              <div class="w-px h-5 bg-gray-300 mx-1"></div>
              <button @click="formatText(st.key, 'list')" class="toolbar-btn">&#8801;</button>
              <button @click="formatText(st.key, 'link')" class="toolbar-btn">&#128279;</button>
              <div class="ml-auto text-xs text-gray-400">{{ termsContent[st.key]?.length || 0 }}자</div>
            </div>

            <textarea
              :ref="el => termsRef[st.key] = el"
              v-model="termsContent[st.key]"
              rows="16"
              class="input-field resize-none font-mono text-sm"
              :placeholder="`${st.label} 내용을 입력하세요...`"
            ></textarea>

            <div class="flex items-center justify-between mt-3">
              <span class="text-xs text-gray-400">
                마지막 수정: {{ termsLastUpdated[st.key] || '미정' }}
              </span>
              <button @click="saveTerms(st.key)" :disabled="saving" class="btn-primary">
                <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
                저장하기
              </button>
            </div>

            <!-- Version History -->
            <div class="mt-6 border-t border-gray-100 pt-5">
              <h3 class="text-sm font-semibold text-gray-700 mb-3">버전 히스토리</h3>
              <div v-if="termsHistory[st.key]?.length" class="space-y-2">
                <div
                  v-for="(version, vi) in termsHistory[st.key]"
                  :key="vi"
                  class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg"
                >
                  <div>
                    <span class="text-sm text-gray-700 font-medium">v{{ version.version }}</span>
                    <span class="text-xs text-gray-400 ml-2">{{ version.date }}</span>
                    <span class="text-xs text-gray-500 ml-2">{{ version.summary }}</span>
                  </div>
                  <button @click="restoreVersion(st.key, version)" class="text-xs text-blue-500 hover:text-blue-700 font-medium border border-blue-200 px-3 py-1 rounded-lg hover:bg-blue-50 transition-colors">
                    복원
                  </button>
                </div>
              </div>
              <div v-else class="text-sm text-gray-400 py-4 text-center">버전 히스토리가 없습니다.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================= TAB 5: 결제/알림 설정 ========================= -->
      <div v-show="activeTab === 'notifications'">

        <!-- Stripe 결제 키 설정 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                <span class="text-lg font-bold text-indigo-600">S</span>
              </div>
              <div>
                <h2 class="text-base font-semibold text-gray-800">Stripe API 키 설정</h2>
                <p class="text-xs text-gray-400">결제 처리를 위한 Stripe API 키 관리</p>
              </div>
            </div>
            <span :class="[
              'text-xs font-medium px-3 py-1 rounded-full',
              payment.stripe_publishable_key ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'
            ]">
              {{ payment.stripe_publishable_key ? 'API 키 설정됨' : '미설정' }}
            </span>
          </div>

          <!-- 테스트/라이브 모드 토글 -->
          <div class="flex items-center gap-4 mb-5 p-3 bg-gray-50 rounded-lg">
            <span class="text-sm text-gray-600 font-medium">결제 모드:</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="payment.stripe_test_mode" class="sr-only peer" />
              <div class="w-11 h-6 bg-green-500 peer-checked:bg-yellow-400 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-[2px] peer-checked:after:translate-x-full after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
            </label>
            <span :class="[
              'text-xs font-medium px-2.5 py-1 rounded-full',
              payment.stripe_test_mode ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'
            ]">
              {{ payment.stripe_test_mode ? '테스트 모드' : '라이브 (실결제)' }}
            </span>
          </div>

          <!-- Stripe API 키 입력 -->
          <div class="space-y-4 mb-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Publishable Key</label>
              <input
                v-model="payment.stripe_publishable_key"
                type="text"
                class="input-field font-mono text-sm"
                :placeholder="payment.stripe_test_mode ? 'pk_test_...' : 'pk_live_...'"
              />
              <p class="text-xs text-gray-400 mt-1">프론트엔드에서 사용되는 공개 키입니다</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
              <div class="relative">
                <input
                  v-model="payment.stripe_secret_key"
                  :type="showStripeSecret ? 'text' : 'password'"
                  class="input-field font-mono text-sm pr-20"
                  :placeholder="payment.stripe_test_mode ? 'sk_test_...' : 'sk_live_...'"
                />
                <button
                  type="button"
                  @click="showStripeSecret = !showStripeSecret"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500 hover:text-gray-700 px-2 py-1 rounded"
                >
                  {{ showStripeSecret ? '숨기기' : '보기' }}
                </button>
              </div>
              <p class="text-xs text-gray-400 mt-1">서버에서 사용되는 비밀 키입니다 (절대 노출하지 마세요)</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret <span class="text-gray-400">(선택)</span></label>
              <input
                v-model="payment.stripe_webhook_secret"
                type="password"
                class="input-field font-mono text-sm"
                placeholder="whsec_..."
              />
              <p class="text-xs text-gray-400 mt-1">Webhook 이벤트 검증용 (설정 시 보안 강화)</p>
            </div>
          </div>

          <div class="flex justify-end">
            <button @click="saveStripeKeys" :disabled="savingStripe" class="btn-primary">
              <span v-if="savingStripe" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
              Stripe 키 저장
            </button>
          </div>
        </div>

        <!-- 결제 일반 설정 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-semibold text-gray-800">결제 시스템 연결</h2>
            <span :class="[
              'text-xs font-medium px-3 py-1 rounded-full',
              payment.stripe_connected ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'
            ]">
              {{ payment.stripe_connected ? '연결됨' : '미연결' }}
            </span>
          </div>

          <!-- Stripe 연동 상태 -->
          <div class="border border-gray-200 rounded-lg p-4 mb-5">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                  <span class="text-lg font-bold text-indigo-600">S</span>
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-800">Stripe</p>
                  <p class="text-xs text-gray-400">결제 처리 서비스</p>
                </div>
              </div>
              <a href="/admin/payments" class="inline-flex items-center gap-1 px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium rounded-lg transition-colors">
                {{ payment.stripe_connected ? 'Stripe 설정 변경' : 'Stripe 연동하기' }}
                <span class="text-xs">&rarr;</span>
              </a>
            </div>

            <!-- 결제 모드 -->
            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
              <span class="text-sm text-gray-600">결제 모드:</span>
              <span :class="[
                'text-xs font-medium px-2.5 py-1 rounded-full',
                payment.mode === 'live' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
              ]">
                {{ payment.mode === 'live' ? '라이브 (실결제)' : '테스트 모드' }}
              </span>
            </div>
          </div>

          <!-- 결제 수단 현황 -->
          <div class="mb-5">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">결제 수단 현황</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div class="flex items-center justify-between border border-gray-200 rounded-lg p-3">
                <div class="flex items-center gap-2">
                  <span class="text-lg">&#128179;</span>
                  <span class="text-sm text-gray-700">카드 결제</span>
                </div>
                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full',
                  payment.card_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                  {{ payment.card_enabled ? '활성' : '비활성' }}
                </span>
              </div>
              <div class="flex items-center justify-between border border-gray-200 rounded-lg p-3">
                <div class="flex items-center gap-2">
                  <span class="text-lg">&#127822;</span>
                  <span class="text-sm text-gray-700">Apple Pay</span>
                </div>
                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full',
                  payment.apple_pay_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                  {{ payment.apple_pay_enabled ? '활성' : '비활성' }}
                </span>
              </div>
              <div class="flex items-center justify-between border border-gray-200 rounded-lg p-3">
                <div class="flex items-center gap-2">
                  <span class="text-lg">G</span>
                  <span class="text-sm text-gray-700">Google Pay</span>
                </div>
                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full',
                  payment.google_pay_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                  {{ payment.google_pay_enabled ? '활성' : '비활성' }}
                </span>
              </div>
            </div>
          </div>

          <!-- 최소 결제금액 / 통화 설정 -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">최소 결제금액</label>
              <div class="flex items-center gap-2">
                <input v-model.number="payment.min_amount" type="number" min="0" step="0.01" class="input-field w-32" />
                <span class="text-sm text-gray-500">{{ payment.currency }}</span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">기본 통화</label>
              <select v-model="payment.currency" class="input-field">
                <option value="USD">USD (미국 달러)</option>
                <option value="KRW">KRW (한국 원)</option>
                <option value="EUR">EUR (유로)</option>
                <option value="JPY">JPY (일본 엔)</option>
              </select>
            </div>
          </div>

          <!-- 이번달 결제 요약 -->
          <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">이번달 결제 요약</h3>
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-white rounded-lg p-3 border border-gray-200">
                <p class="text-xs text-gray-500 mb-1">총 결제 금액</p>
                <p class="text-lg font-bold text-gray-800">
                  {{ payment.currency === 'USD' ? '$' : '' }}{{ payment.this_month_total.toLocaleString() }}
                  <span v-if="payment.currency !== 'USD'" class="text-sm font-normal text-gray-500">{{ payment.currency }}</span>
                </p>
              </div>
              <div class="bg-white rounded-lg p-3 border border-gray-200">
                <p class="text-xs text-gray-500 mb-1">결제 성공률</p>
                <p class="text-lg font-bold" :class="payment.this_month_success_rate >= 90 ? 'text-green-600' : payment.this_month_success_rate >= 70 ? 'text-yellow-600' : 'text-red-600'">
                  {{ payment.this_month_success_rate }}%
                </p>
              </div>
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <button @click="savePayment" :disabled="saving" class="btn-primary">
              <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
              결제 설정 저장
            </button>
          </div>
        </div>

        <!-- 시스템 공지사항 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-semibold text-gray-800">시스템 공지사항</h2>
            <button @click="showAnnouncementForm = !showAnnouncementForm" class="btn-secondary text-sm">
              {{ showAnnouncementForm ? '취소' : '+ 새 공지 추가' }}
            </button>
          </div>

          <!-- New Announcement Form -->
          <div v-if="showAnnouncementForm" class="border border-blue-100 bg-blue-50 rounded-xl p-5 mb-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">새 공지사항</h3>
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">제목</label>
                <input v-model="newAnnouncement.title" type="text" class="input-field" placeholder="공지사항 제목" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">내용</label>
                <textarea v-model="newAnnouncement.content" rows="3" class="input-field resize-none" placeholder="공지사항 내용..."></textarea>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">시작 날짜</label>
                  <input v-model="newAnnouncement.start_date" type="date" class="input-field" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">종료 날짜</label>
                  <input v-model="newAnnouncement.end_date" type="date" class="input-field" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">표시 위치</label>
                  <select v-model="newAnnouncement.display_position" class="input-field">
                    <option value="all">전체</option>
                    <option value="home">홈</option>
                    <option value="specific">특정 메뉴</option>
                  </select>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <ToggleRow v-model="newAnnouncement.active" label="활성화" desc="" />
              </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
              <button @click="showAnnouncementForm = false" class="btn-secondary">취소</button>
              <button @click="addAnnouncement" class="btn-primary">추가</button>
            </div>
          </div>

          <!-- Announcements List -->
          <div v-if="notifications.announcements.length" class="space-y-2">
            <div
              v-for="(ann, ai) in notifications.announcements"
              :key="ai"
              class="border border-gray-200 rounded-lg p-4"
            >
              <div v-if="editingAnnouncement === ai">
                <!-- Edit mode -->
                <div class="grid grid-cols-1 gap-3">
                  <input v-model="ann.title" type="text" class="input-field text-sm" />
                  <textarea v-model="ann.content" rows="2" class="input-field resize-none text-sm"></textarea>
                  <div class="grid grid-cols-3 gap-2">
                    <input v-model="ann.start_date" type="date" class="input-field text-xs" />
                    <input v-model="ann.end_date" type="date" class="input-field text-xs" />
                    <select v-model="ann.display_position" class="input-field text-xs">
                      <option value="all">전체</option>
                      <option value="home">홈</option>
                      <option value="specific">특정 메뉴</option>
                    </select>
                  </div>
                  <div class="flex justify-end gap-2">
                    <button @click="editingAnnouncement = null" class="btn-secondary text-xs">취소</button>
                    <button @click="editingAnnouncement = null" class="btn-primary text-xs">저장</button>
                  </div>
                </div>
              </div>
              <div v-else class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span :class="['inline-block w-2 h-2 rounded-full', ann.active ? 'bg-green-400' : 'bg-gray-300']"></span>
                    <span class="text-sm font-medium text-gray-800 truncate">{{ ann.title }}</span>
                    <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-500">{{ positionLabel(ann.display_position) }}</span>
                  </div>
                  <p class="text-xs text-gray-500 truncate">{{ ann.content }}</p>
                  <p class="text-xs text-gray-400 mt-1">{{ ann.start_date }} ~ {{ ann.end_date }}</p>
                </div>
                <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                  <button @click="editingAnnouncement = ai" class="text-xs text-blue-500 hover:text-blue-700">수정</button>
                  <button @click="removeAnnouncement(ai)" class="text-xs text-red-400 hover:text-red-600">삭제</button>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-gray-400 py-6 text-center">등록된 공지사항이 없습니다.</div>
        </div>

        <!-- 이메일 알림 설정 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
          <h2 class="text-base font-semibold text-gray-800 mb-5">이메일 알림 설정</h2>
          <ToggleRow v-model="notifications.email.enabled" label="알림 이메일 활성화" desc="관리자에게 이메일 알림을 전송합니다" class="mb-4" />
          <div :class="['space-y-3 transition-opacity', notifications.email.enabled ? 'opacity-100' : 'opacity-40 pointer-events-none']">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">신규 가입 알림 수신 이메일</label>
              <input v-model="notifications.email.new_user" type="email" class="input-field" placeholder="admin@awesomekorean.com" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">신고 접수 알림 수신 이메일</label>
              <input v-model="notifications.email.report" type="email" class="input-field" placeholder="admin@awesomekorean.com" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">결제 알림 수신 이메일</label>
              <input v-model="notifications.email.payment" type="email" class="input-field" placeholder="billing@awesomekorean.com" />
            </div>
          </div>
        </div>

        <!-- 푸시 알림 설정 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
          <h2 class="text-base font-semibold text-gray-800 mb-5">푸시 알림 설정 (VAPID)</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">VAPID Public Key</label>
              <div class="flex gap-2">
                <input v-model="notifications.push.vapid_public" type="text" class="input-field flex-1 font-mono text-xs" placeholder="BFHtE3h..." readonly />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">VAPID Private Key</label>
              <input
                v-model="notifications.push.vapid_private"
                :type="showVapidPrivate ? 'text' : 'password'"
                class="input-field font-mono text-xs"
                placeholder="••••••••"
                readonly
              />
            </div>
            <div class="flex items-center gap-3">
              <button @click="generateVapidKeys" :disabled="generatingKeys" class="btn-secondary flex items-center gap-2">
                <span v-if="generatingKeys" class="inline-block w-4 h-4 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></span>
                키 생성
              </button>
              <button @click="showVapidPrivate = !showVapidPrivate" class="text-sm text-gray-500 hover:text-gray-700">
                {{ showVapidPrivate ? '숨기기' : '보기' }}
              </button>
              <p class="text-xs text-amber-600">주의: 키를 재생성하면 기존 구독이 모두 해제됩니다.</p>
            </div>
          </div>
        </div>

        <!-- SEO 설정 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
          <h2 class="text-base font-semibold text-gray-800 mb-5">SEO 설정</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">메타 타이틀 기본값</label>
              <input v-model="seo.meta_title" type="text" class="input-field" placeholder="AwesomeKorean - 미국 한인 커뮤니티" />
              <p class="text-xs text-gray-400 mt-1">페이지별 타이틀이 없을 때 사용되는 기본 타이틀</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">메타 설명 기본값</label>
              <textarea v-model="seo.meta_description" rows="2" class="input-field resize-none" placeholder="미국 한인 커뮤니티 플랫폼 AwesomeKorean에서 다양한 정보를 나누세요."></textarea>
              <p class="text-xs text-gray-400 mt-1">검색 결과에 표시되는 사이트 설명 (150자 이내 권장)</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Open Graph 이미지 URL</label>
              <div class="flex items-center gap-3">
                <input v-model="seo.og_image" type="text" class="input-field flex-1" placeholder="https://awesomekorean.com/images/og-image.jpg" />
                <div v-if="seo.og_image" class="w-16 h-10 border border-gray-200 rounded overflow-hidden flex-shrink-0">
                  <img :src="seo.og_image" alt="OG Preview" class="w-full h-full object-cover" @error="() => {}" />
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-1">SNS 공유 시 표시되는 대표 이미지 (1200x630px 권장)</p>
            </div>

            <div class="border-t border-gray-100 pt-4">
              <h3 class="text-sm font-semibold text-gray-600 mb-3">사이트 인증</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Google Site Verification</label>
                  <input v-model="seo.google_verification" type="text" class="input-field" placeholder="google-site-verification=..." />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Naver Site Verification</label>
                  <input v-model="seo.naver_verification" type="text" class="input-field" placeholder="naver-site-verification=..." />
                </div>
              </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">robots.txt 편집</label>
              <textarea v-model="seo.robots_txt" rows="8" class="input-field resize-none font-mono text-xs" placeholder="User-agent: *&#10;Allow: /&#10;Sitemap: https://awesomekorean.com/sitemap.xml"></textarea>
              <p class="text-xs text-gray-400 mt-1">검색 엔진 크롤러 접근 규칙을 설정합니다</p>
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <button @click="saveSeo" :disabled="saving" class="btn-primary">
              <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
              SEO 설정 저장
            </button>
          </div>
        </div>

        <div class="flex justify-end">
          <button @click="saveNotifications" :disabled="saving" class="btn-primary">
            <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
            알림 설정 저장
          </button>
        </div>
      </div>
    </div>

    <!-- API 키 관리 -->
    <div v-if="activeTab === 'api'" class="space-y-6">

      <!-- Firebase Cloud Messaging 설정 -->
      <div class="bg-white rounded-xl border p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold">🔔 Firebase Push Notification</h3>
          <span :class="firebaseStatus ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'" class="text-xs px-3 py-1 rounded-full font-medium">
            {{ firebaseStatus ? '✅ 연결됨' : '⚠️ 미설정' }}
          </span>
        </div>
        <p class="text-xs text-gray-400 mb-4">안심통화 수신 알림, 백그라운드 푸시, 벨소리에 사용됩니다.</p>

        <div class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-gray-500 mb-1 block">API Key</label>
              <input v-model="firebase.apiKey" type="text" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" placeholder="AIzaSy..." />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">Project ID</label>
              <input v-model="firebase.projectId" type="text" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" placeholder="awesomekorean-xxxxx" />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">Messaging Sender ID</label>
              <input v-model="firebase.messagingSenderId" type="text" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" placeholder="123456789" />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">App ID</label>
              <input v-model="firebase.appId" type="text" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" placeholder="1:123:web:abc..." />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">Auth Domain</label>
              <input v-model="firebase.authDomain" type="text" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" placeholder="xxx.firebaseapp.com" />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">Storage Bucket</label>
              <input v-model="firebase.storageBucket" type="text" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" placeholder="xxx.appspot.com" />
            </div>
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">VAPID Key (웹 푸시 인증서)</label>
            <input v-model="firebase.vapidKey" type="text" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" placeholder="BA93MT..." />
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">서비스 계정 JSON (백엔드용 — 서버에 직접 업로드)</label>
            <div class="flex items-center gap-2">
              <code class="text-xs bg-gray-100 px-3 py-2 rounded flex-1 font-mono text-gray-600">{{ firebase.credentialsPath || '/storage/app/firebase-service-account.json' }}</code>
              <span :class="firebase.credentialsExists ? 'text-green-600' : 'text-red-500'" class="text-xs font-medium">{{ firebase.credentialsExists ? '✅ 파일 존재' : '❌ 파일 없음' }}</span>
            </div>
          </div>
          <button @click="saveFirebase" :disabled="savingFirebase" class="w-full py-2.5 bg-orange-500 text-white rounded-lg text-sm font-bold hover:bg-orange-600 disabled:opacity-50 mt-2">
            {{ savingFirebase ? '저장 중...' : '🔔 Firebase 설정 저장' }}
          </button>
        </div>
      </div>

      <!-- 기존 API 키 관리 -->
      <div class="bg-white rounded-xl border p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold">🔑 API 키 관리</h3>
          <button @click="showAddApiKey = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700">+ 새 API 키</button>
        </div>
        
        <div class="space-y-3">
          <div v-for="key in apiKeys" :key="key.id" class="border rounded-xl p-4 flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-gray-800">{{ key.name }}</span>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ key.service }}</span>
                <span :class="key.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="text-xs px-2 py-0.5 rounded-full">{{ key.is_active ? '활성' : '비활성' }}</span>
              </div>
              <p v-if="key.description" class="text-xs text-gray-500 mt-1">{{ key.description }}</p>
              <div class="flex items-center gap-2 mt-2">
                <code class="text-xs bg-gray-100 px-2 py-1 rounded font-mono">{{ key.showFull ? key.fullKey : key.masked_key }}</code>
                <button @click="toggleReveal(key)" class="text-xs text-blue-600 hover:underline">{{ key.showFull ? '숨기기' : '보기' }}</button>
                <button @click="copyKey(key)" class="text-xs text-gray-500 hover:underline">복사</button>
              </div>
            </div>
            <div class="flex gap-2">
              <button @click="toggleApiKeyActive(key)" :class="key.is_active ? 'text-orange-600' : 'text-green-600'" class="text-xs hover:underline">{{ key.is_active ? '비활성화' : '활성화' }}</button>
              <button @click="deleteApiKey(key.id)" class="text-xs text-red-600 hover:underline">삭제</button>
            </div>
          </div>
          <div v-if="!apiKeys.length" class="text-center py-8 text-gray-400 text-sm">등록된 API 키가 없습니다</div>
        </div>
      </div>

      <!-- 새 API 키 추가 모달 -->
      <div v-if="showAddApiKey" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showAddApiKey = false">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md">
          <h3 class="font-bold text-lg mb-4">🔑 새 API 키 등록</h3>
          <div class="space-y-3">
            <div>
              <label class="text-xs text-gray-500 mb-1 block">서비스 이름</label>
              <input v-model="newApiKey.name" placeholder="예: YouTube Data API v3" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">서비스 코드</label>
              <input v-model="newApiKey.service" placeholder="예: youtube, stripe, openai" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">API 키</label>
              <input v-model="newApiKey.api_key" placeholder="키 입력..." class="w-full border rounded-lg px-3 py-2 text-sm font-mono" />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">설명 (선택)</label>
              <input v-model="newApiKey.description" placeholder="용도 설명" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div class="flex gap-2 pt-2">
              <button @click="showAddApiKey = false" class="flex-1 py-2 bg-gray-100 rounded-lg text-sm font-semibold">취소</button>
              <button @click="saveApiKey" class="flex-1 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">등록</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import axios from 'axios'

// ─── Sub-component: Toggle Row ────────────────────────────────────────────────
const ToggleRow = {
  props: ['modelValue', 'label', 'desc'],
  emits: ['update:modelValue'],
  template: `
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-700">{{ label }}</p>
        <p v-if="desc" class="text-xs text-gray-400">{{ desc }}</p>
      </div>
      <button
        @click="$emit('update:modelValue', !modelValue)"
        :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors flex-shrink-0',
          modelValue ? 'bg-blue-500' : 'bg-gray-200']"
      >
        <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
          modelValue ? 'translate-x-6' : 'translate-x-1']"></span>
      </button>
    </div>
  `
}

// ─── State ────────────────────────────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const generatingKeys = ref(false)
const showVapidPrivate = ref(false)
const showAnnouncementForm = ref(false)
const editingAnnouncement = ref(null)
const activeTab = ref('company')
const activeTermsTab = ref('terms')
const logoError = ref(false)
const lastLoaded = ref('—')
const showStripeSecret = ref(false)
const savingStripe = ref(false)

const tabs = [
  { key: 'company', label: '회사 정보' },
  { key: 'site', label: '사이트 설정' },
  { key: 'menus', label: '메뉴 관리' },
  { key: 'footer', label: '푸터 편집' },
  { key: 'terms', label: '약관 관리' },
  { key: 'notifications', label: '결제/알림 설정' },
  { key: 'api', label: '🔑 API 키 관리' },
]

const termsTabs = [
  { key: 'terms', label: '이용약관' },
  { key: 'privacy', label: '개인정보처리방침' },
]

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = reactive({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.message = message
  toast.type = type
  toast.show = true
  toastTimer = setTimeout(() => { toast.show = false }, 3000)
}

// ─── Form Data ────────────────────────────────────────────────────────────────
const company = reactive({
  site_name: 'AwesomeKorean',
  site_subtitle: '미국 한인 커뮤니티',
  company_name: 'AwesomeKorean Inc.',
  ceo_name: '',
  business_number: '',
  address: 'Los Angeles, CA, USA',
  phone: '',
  email: 'admin@awesomekorean.com',
  founded_date: '',
  logo_url: '',
  favicon_url: '',
  meta_description: '',
  meta_keywords: '',
})

const site = reactive({
  allow_signup: true,
  require_email_verify: true,
  auto_approve: false,
  allow_withdrawal: true,
  min_password_length: 8,
  enable_points: true,
  point_attendance: 10,
  point_signup: 100,
  point_post: 5,
  point_comment: 2,
  max_upload_mb: 10,
  allowed_file_types: 'jpg,png,gif,pdf,doc',
  maintenance_mode: false,
  maintenance_reason: '',
  maintenance_until: '',
  google_analytics_id: '',
  kakao_api_key: '',
})

const footer = reactive({
  columns: [
    { title: '브랜드 소개', links: [{ label: '회사 소개', url: '/about' }, { label: '팀 소개', url: '/team' }] },
    { title: '서비스', links: [{ label: '커뮤니티', url: '/community' }, { label: '비즈니스', url: '/business' }] },
    { title: '커뮤니티', links: [{ label: '자유게시판', url: '/board' }, { label: '정보 공유', url: '/info' }] },
    { title: '정보', links: [{ label: '이용약관', url: '/terms' }, { label: '개인정보처리방침', url: '/privacy' }] },
  ],
  copyright: '© 2024 AwesomeKorean Inc. All rights reserved.',
  sns: {
    facebook: '',
    instagram: '',
    twitter: '',
    youtube: '',
    kakao: '',
  },
  additional_text: '',
})

const termsContent = reactive({ terms: '', privacy: '' })
const termsLastUpdated = reactive({ terms: '', privacy: '' })
const termsHistory = reactive({
  terms: [],
  privacy: [],
})
const termsRef = reactive({ terms: null, privacy: null })

const notifications = reactive({
  announcements: [],
  email: {
    enabled: true,
    new_user: 'admin@awesomekorean.com',
    report: 'admin@awesomekorean.com',
    payment: 'billing@awesomekorean.com',
  },
  push: {
    vapid_public: '',
    vapid_private: '',
  },
})

const newAnnouncement = reactive({
  title: '',
  content: '',
  start_date: '',
  end_date: '',
  display_position: 'all',
  active: true,
})

// ─── Payment Data ─────────────────────────────────────────────────────────────
const payment = reactive({
  stripe_connected: false,
  stripe_publishable_key: '',
  stripe_secret_key: '',
  stripe_webhook_secret: '',
  stripe_test_mode: true,
  mode: 'test',
  card_enabled: true,
  apple_pay_enabled: false,
  google_pay_enabled: false,
  min_amount: 1.00,
  currency: 'USD',
  this_month_total: 2340.00,
  this_month_success_rate: 92.5,
})

// ─── SEO Data ─────────────────────────────────────────────────────────────────
const seo = reactive({
  meta_title: 'AwesomeKorean - 미국 한인 커뮤니티',
  meta_description: '',
  og_image: '',
  google_verification: '',
  naver_verification: '',
  robots_txt: 'User-agent: *\nAllow: /\nSitemap: https://awesomekorean.com/sitemap.xml',
})

// ─── Load All Settings ────────────────────────────────────────────────────────
async function loadSettings() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/settings/all')
    applySettings(data)
    lastLoaded.value = new Date().toLocaleTimeString('ko-KR')
  } catch {
    // Use hardcoded defaults — already set above
    lastLoaded.value = '기본값 (서버 연결 실패)'
    // Seed sample history
    termsHistory.terms = [
      { version: 3, date: '2024-03-01', summary: '제3조 수정' },
      { version: 2, date: '2024-01-15', summary: '전면 개정' },
      { version: 1, date: '2023-06-01', summary: '최초 등록' },
    ]
    termsHistory.privacy = [
      { version: 2, date: '2024-02-20', summary: '수집 항목 추가' },
      { version: 1, date: '2023-06-01', summary: '최초 등록' },
    ]
    termsLastUpdated.terms = '2024-03-01'
    termsLastUpdated.privacy = '2024-02-20'
  } finally {
    loading.value = false
  }
}

function applySettings(data) {
  if (data.company) Object.assign(company, data.company)
  if (data.site) Object.assign(site, data.site)
  if (data.footer) {
    if (data.footer.columns) footer.columns = data.footer.columns
    if (data.footer.copyright) footer.copyright = data.footer.copyright
    if (data.footer.sns) Object.assign(footer.sns, data.footer.sns)
    if (data.footer.additional_text) footer.additional_text = data.footer.additional_text
  }
  if (data.terms) {
    termsContent.terms = data.terms.terms?.content || ''
    termsContent.privacy = data.terms.privacy?.content || ''
    termsLastUpdated.terms = data.terms.terms?.updated_at || ''
    termsLastUpdated.privacy = data.terms.privacy?.updated_at || ''
    termsHistory.terms = data.terms.terms?.history || []
    termsHistory.privacy = data.terms.privacy?.history || []
  }
  if (data.notifications) {
    if (data.notifications.announcements) notifications.announcements = data.notifications.announcements
    if (data.notifications.email) Object.assign(notifications.email, data.notifications.email)
    if (data.notifications.push) Object.assign(notifications.push, data.notifications.push)
  }
  if (data.payment) Object.assign(payment, data.payment)
  // Stripe 키 로드 (site_settings에서 직접)
  if (data.stripe_publishable_key) payment.stripe_publishable_key = data.stripe_publishable_key
  if (data.stripe_secret_key) payment.stripe_secret_key = data.stripe_secret_key
  if (data.stripe_webhook_secret) payment.stripe_webhook_secret = data.stripe_webhook_secret
  if (data.stripe_test_mode !== undefined) payment.stripe_test_mode = data.stripe_test_mode === '1' || data.stripe_test_mode === true
  if (data.seo) Object.assign(seo, data.seo)
}

// ─── Save Functions ───────────────────────────────────────────────────────────
async function saveCompany() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/company', { ...company })
    showToast('회사 정보가 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveSite() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/site', { ...site })
    showToast('사이트 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveFooter() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/footer', {
      columns: footer.columns,
      copyright: footer.copyright,
      sns: { ...footer.sns },
      additional_text: footer.additional_text,
    })
    showToast('푸터 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveTerms(type) {
  saving.value = true
  try {
    await axios.post(`/api/admin/settings/terms/${type}`, { content: termsContent[type] })
    termsLastUpdated[type] = new Date().toLocaleDateString('ko-KR')
    showToast(type === 'terms' ? '이용약관이 저장되었습니다.' : '개인정보처리방침이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveNotifications() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/notifications', {
      announcements: notifications.announcements,
      email: { ...notifications.email },
      push: { ...notifications.push },
    })
    showToast('알림 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveStripeKeys() {
  savingStripe.value = true
  try {
    await axios.post('/api/admin/settings/stripe', {
      stripe_publishable_key: payment.stripe_publishable_key,
      stripe_secret_key: payment.stripe_secret_key,
      stripe_webhook_secret: payment.stripe_webhook_secret,
      stripe_test_mode: payment.stripe_test_mode,
    })
    showToast('Stripe 키가 저장되었습니다.')
  } catch {
    showToast('Stripe 키 저장에 실패했습니다.', 'error')
  } finally {
    savingStripe.value = false
  }
}

async function savePayment() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/payment-gateway', {
      min_amount: payment.min_amount,
      currency: payment.currency,
    })
    showToast('결제 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveSeo() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/seo', { ...seo })
    showToast('SEO 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

// ─── Footer helpers ───────────────────────────────────────────────────────────
function addFooterLink(colIndex) {
  footer.columns[colIndex].links.push({ label: '', url: '' })
}

function removeFooterLink(colIndex, linkIndex) {
  footer.columns[colIndex].links.splice(linkIndex, 1)
}

// ─── Terms helpers ────────────────────────────────────────────────────────────
function formatText(type, format) {
  const el = termsRef[type]
  if (!el) return
  const start = el.selectionStart
  const end = el.selectionEnd
  const selected = termsContent[type].substring(start, end)
  let replacement = selected

  switch (format) {
    case 'bold': replacement = `**${selected}**`; break
    case 'italic': replacement = `*${selected}*`; break
    case 'h2': replacement = `\n## ${selected || '제목'}`; break
    case 'h3': replacement = `\n### ${selected || '소제목'}`; break
    case 'list': replacement = `\n- ${selected || '항목'}`; break
    case 'link': {
      const url = window.prompt('URL을 입력하세요:', 'https://')
      if (url) replacement = `[${selected || '링크 텍스트'}](${url})`
      break
    }
  }

  termsContent[type] =
    termsContent[type].substring(0, start) +
    replacement +
    termsContent[type].substring(end)
}

function restoreVersion(type, version) {
  if (confirm(`v${version.version} (${version.date}) 버전으로 복원하시겠습니까?`)) {
    // In production, would fetch version content from API
    showToast(`v${version.version} 버전으로 복원 요청되었습니다.`)
  }
}

// ─── Announcement helpers ─────────────────────────────────────────────────────
function addAnnouncement() {
  if (!newAnnouncement.title.trim()) {
    showToast('제목을 입력해주세요.', 'error')
    return
  }
  notifications.announcements.unshift({ ...newAnnouncement })
  Object.assign(newAnnouncement, {
    title: '', content: '', start_date: '', end_date: '',
    display_position: 'all', active: true,
  })
  showAnnouncementForm.value = false
}

function removeAnnouncement(index) {
  if (confirm('이 공지사항을 삭제하시겠습니까?')) {
    notifications.announcements.splice(index, 1)
  }
}

function positionLabel(pos) {
  const map = { all: '전체', home: '홈', specific: '특정 메뉴' }
  return map[pos] || pos
}

// ─── VAPID Key Generation ─────────────────────────────────────────────────────
async function generateVapidKeys() {
  generatingKeys.value = true
  try {
    const { data } = await axios.post('/api/admin/settings/generate-vapid')
    notifications.push.vapid_public = data.public_key
    notifications.push.vapid_private = data.private_key
    showToast('VAPID 키가 생성되었습니다.')
  } catch {
    // Simulate key generation for demo
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
    const randomStr = (n) => Array.from({ length: n }, () => chars[Math.floor(Math.random() * chars.length)]).join('')
    notifications.push.vapid_public = 'B' + randomStr(86) + '='
    notifications.push.vapid_private = randomStr(43) + '='
    showToast('VAPID 키가 생성되었습니다 (데모).')
  } finally {
    generatingKeys.value = false
  }
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
// ─── Menu Management ─────────────────────────────────────────────────────────
const menuList = ref([])
const menuSaving = ref(false)

const allMenuDefs = [
  { key: 'home',      label: '홈',       label_en: 'Home',       icon: '🏠', path: '/' },
  { key: 'community', label: '커뮤니티', label_en: 'Community',  icon: '💬', path: '/community' },
  { key: 'qa',        label: 'Q&A',      label_en: 'Q&A',        icon: '❓', path: '/qa' },
  { key: 'jobs',      label: '구인구직', label_en: 'Jobs',       icon: '💼', path: '/jobs' },
  { key: 'market',    label: '중고장터', label_en: 'Market',     icon: '🛒', path: '/market', hasCardView: true },
  { key: 'directory', label: '업소록',   label_en: 'Directory',  icon: '🏪', path: '/directory', hasCardView: true },
  { key: 'realestate',label: '부동산',   label_en: 'Real Estate',icon: '🏠', path: '/realestate', hasCardView: true },
  { key: 'events',    label: '이벤트',   label_en: 'Events',     icon: '🎉', path: '/events', hasCardView: true },
  { key: 'news',      label: '뉴스',     label_en: 'News',       icon: '📰', path: '/news' },
  { key: 'recipes',   label: '레시피',   label_en: 'Recipes',    icon: '🍳', path: '/recipes', hasCardView: true },
  { key: 'clubs',     label: '동호회',   label_en: 'Clubs',      icon: '👥', path: '/clubs' },
  { key: 'games',     label: '게임',     label_en: 'Games',      icon: '🎮', path: '/games' },
  { key: 'shorts',    label: '숏츠',     label_en: 'Shorts',     icon: '📱', path: '/shorts' },
  { key: 'music',     label: '음악듣기', label_en: 'Music',      icon: '🎵', path: '/music' },
  { key: 'groupbuy',  label: '공동구매', label_en: 'Group Buy',  icon: '🤝', path: '/groupbuy' },
  { key: 'shopping',  label: '쇼핑',     label_en: 'Shopping',   icon: '🛍️', path: '/shopping' },
  { key: 'chat',      label: '채팅',     label_en: 'Chat',       icon: '💭', path: '/chat' },
  { key: 'friends',   label: '친구',     label_en: 'Friends',    icon: '👫', path: '/friends' },
  { key: 'elder',     label: '안심서비스',label_en: 'Elder Care', icon: '💙', path: '/elder' },
]

async function loadMenus() {
  try {
    const { data } = await axios.get('/api/admin/settings/menus')
    const saved = Array.isArray(data?.data || data) ? (data?.data || data) : []
    if (saved.length > 0) {
      const ordered = []
      saved.forEach(m => {
        const def = allMenuDefs.find(d => d.key === m.key)
        if (def) ordered.push({ ...def, ...m, enabled: m.enabled !== false, login_required: m.login_required || false, admin_only: m.admin_only || false, defaultView: m.defaultView || 'list', order: m.order ?? 999 })
      })
      allMenuDefs.forEach(def => {
        if (!ordered.find(m => m.key === def.key)) {
          ordered.push({ ...def, enabled: true, login_required: false, admin_only: false, defaultView: 'list', order: 999 })
        }
      })
      menuList.value = ordered.sort((a, b) => a.order - b.order)
    } else {
      menuList.value = allMenuDefs.map((d, i) => ({ ...d, enabled: true, login_required: false, admin_only: false, defaultView: 'list', order: i }))
    }
  } catch {
    menuList.value = allMenuDefs.map((d, i) => ({ ...d, enabled: true, login_required: false, admin_only: false, defaultView: 'list', order: i }))
  }
}

function moveMenu(idx, dir) {
  const newIdx = idx + dir
  if (newIdx < 0 || newIdx >= menuList.value.length) return
  const list = [...menuList.value]
  const [item] = list.splice(idx, 1)
  list.splice(newIdx, 0, item)
  menuList.value = list
}

function resetMenuOrder() {
  menuList.value = allMenuDefs.map((d, i) => ({ ...d, enabled: true, login_required: false, admin_only: false, order: i }))
}

async function saveMenus() {
  menuSaving.value = true
  try {
    const menus = menuList.value.map((item, i) => ({
      key: item.key,
      label: item.label,
      label_en: item.label_en,
      icon: item.icon,
      path: item.path,
      enabled: item.enabled,
      order: i,
      login_required: item.login_required || false,
      admin_only: item.admin_only || false,
      defaultView: item.defaultView || 'list',
      hasCardView: item.hasCardView || false,
    }))
    await axios.post('/api/admin/settings/menus/batch', { menus })
    showToast('메뉴 설정이 저장되었습니다.')
  } catch {
    showToast('저장 실패', 'error')
  } finally {
    menuSaving.value = false
  }
}

// ─── Firebase 설정 ───────────────────────────────────────────────────────────
const savingFirebase = ref(false)
const firebaseStatus = ref(false)
const firebase = reactive({
  apiKey: '',
  authDomain: '',
  projectId: '',
  storageBucket: '',
  messagingSenderId: '',
  appId: '',
  vapidKey: '',
  credentialsPath: '',
  credentialsExists: false,
})

async function loadFirebase() {
  try {
    const { data } = await axios.get('/api/admin/firebase')
    Object.assign(firebase, data)
    firebaseStatus.value = !!(data.apiKey && data.projectId && data.vapidKey && data.credentialsExists)
  } catch (e) { console.warn('loadFirebase:', e) }
}

async function saveFirebase() {
  savingFirebase.value = true
  try {
    await axios.post('/api/admin/firebase', {
      apiKey: firebase.apiKey,
      authDomain: firebase.authDomain,
      projectId: firebase.projectId,
      storageBucket: firebase.storageBucket,
      messagingSenderId: firebase.messagingSenderId,
      appId: firebase.appId,
      vapidKey: firebase.vapidKey,
    })
    showToast('Firebase 설정이 저장되었습니다')
    firebaseStatus.value = !!(firebase.apiKey && firebase.projectId && firebase.vapidKey)
  } catch (e) {
    showToast(e.response?.data?.message || 'Firebase 저장 실패', 'error')
  } finally {
    savingFirebase.value = false
  }
}

// ─── API 키 관리 ──────────────────────────────────────────────────────────────
const apiKeys = ref([])
const showAddApiKey = ref(false)
const newApiKey = ref({ name: '', service: '', api_key: '', description: '' })

async function loadApiKeys() {
  try {
    const { data } = await axios.get('/api/admin/api-keys')
    const list = data.data || data || []
    apiKeys.value = list.map(k => ({ ...k, showFull: false, fullKey: '' }))
  } catch (e) { console.error('loadApiKeys error:', e) }
}

async function saveApiKey() {
  try {
    await axios.post('/api/admin/api-keys', newApiKey.value)
    showAddApiKey.value = false
    newApiKey.value = { name: '', service: '', api_key: '', description: '' }
    loadApiKeys()
  } catch (e) { alert(e.response?.data?.message || '등록 실패') }
}

async function deleteApiKey(id) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  await axios.delete('/api/admin/api-keys/' + id)
  loadApiKeys()
}

async function toggleApiKeyActive(key) {
  await axios.put('/api/admin/api-keys/' + key.id, { is_active: !key.is_active })
  loadApiKeys()
}

async function toggleReveal(key) {
  if (!key.showFull) {
    const { data } = await axios.get('/api/admin/api-keys/' + key.id + '/reveal')
    key.fullKey = data.data?.key || data.key || key.api_key || ''
  }
  key.showFull = !key.showFull
}

function copyKey(key) {
  const text = key.showFull ? key.fullKey : key.masked_key
  navigator.clipboard.writeText(key.fullKey || text)
  alert('복사되었습니다!')
}

onMounted(loadSettings)
onMounted(loadMenus)
onMounted(loadApiKeys)
onMounted(loadFirebase)

watch(() => activeTab.value, (tab) => {
  if (tab === 'api') loadApiKeys()
})

</script>

<style scoped>
.input-field {
  @apply w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-400 transition-colors bg-white;
}

.btn-primary {
  @apply inline-flex items-center px-5 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-secondary {
  @apply inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors;
}

.toolbar-btn {
  @apply px-2.5 py-1 text-sm text-gray-600 hover:bg-gray-200 rounded font-medium transition-colors;
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px) scale(0.95);
}
</style>
