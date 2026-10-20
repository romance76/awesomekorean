# 정보 글 "공식 안내(출처)" 보강 기록 — #28

시작: 2026-10-19. 출처 링크(외부)가 하나도 없는 공개 글을 10편씩 보강한다. 글 본문은 건드리지 않고, 본문 끝(또는 "자주 묻는 질문" 앞)에 한 줄을 추가한다.

## 방법
- 대상 찾기: 관리자 API `GET /api/admin/info-posts` 에서 본문에 외부 링크(awesomekorean.com·creativecommons.org 제외)가 없는 공개 글. 10/19 기준 **53편**(10/14 점검의 54편에서 1편 줄어듦).
- 형식(기존 글과 같게): `<p>공식 안내: <a href="주소">기관, 제목</a>, ...</p>`. 수정은 `PUT /api/admin/info-posts/{id}` 로 body 만 바꿈.
- 링크 확인: 모두 curl 로 열어 200 확인. 사이트가 로봇 접속을 막아 403 이 나오는 정부 사이트(FCC·NHTSA·SSA·State Dept 등)는 웹 아카이브(archive.org)에 같은 주소의 200 기록이 있는 것만 사용. 404·시간 초과·아카이브 기록 없음은 제외(FTC 3개, SBA 구조 변경, GA PSC/DCA, SOS 등).
- 되돌리기: 추가한 `<p>공식 안내: …</p>` 한 줄만 지우면 원래대로.

## 1차 (10편, 2026-10-19) — 적용·공개 화면 확인 완료
| 글 번호 | 제목 | 붙인 링크 |
|---|---|---|
| 133 | 2026년 10월 비자 공고 | State Dept Visa Bulletin, USCIS Green Card |
| 135 | 메디케어 Part B 보험료 + COLA | Medicare.gov Medicare costs |
| 134 | 2027 ACA 오픈 엔롤먼트 | HealthCare.gov Dates and deadlines, Georgia Access |
| 132 | H-1B 10만 달러 수수료 | USCIS H-1B Specialty Occupations |
| 137 | EIN 무료 발급 | IRS Apply for an EIN online, IRS Get an EIN |
| 125 | SSN 신청과 은행 계좌 | SSA Social Security Number and Card, CFPB Bank accounts |
| 49 | 프리랜서 분기별 예상세 | IRS Estimated taxes, Form 1040-ES, Direct Pay |
| 47 | LLC vs S-Corp vs C-Corp | IRS LLC, IRS S corporations |
| 116 | 401(k)와 IRA | IRS IRAs, IRS 401(k) plans |
| 117 | 세금 신고 기초 | IRS Standard deduction, About Form W-2 |

## 2차 (10편, 2026-10-19) — 적용·공개 화면 확인 완료
| 글 번호 | 제목 | 붙인 링크 |
|---|---|---|
| 120 | 조지아 자동차 보험 가입 | 조지아 보험·안전화재 커미셔너(OCI), 조지아 DDS |
| 122 | 미국 건강보험 기초 | HealthCare.gov Get coverage, Medicare.gov, Georgia Access |
| 118 | 조지아 재산세 감면(Homestead) | 조지아 세무국 Property Tax Homestead Exemptions |
| 121 | 조지아 부동산 구입 절차 | CFPB Owning a Home, HUD Buying a Home |
| 123 | 조지아 렌트 계약 | HUD Georgia, HUD Rental Assistance |
| 124 | 신용점수 만드는 방법 | CFPB Credit reports and scores, AnnualCreditReport.com |
| 110 | 은행 계좌 종류 비교 | CFPB Bank accounts, FDIC Deposit insurance, MyCreditUnion.gov |
| 130 | 조지아 운전면허(DDS) | 조지아 DDS |
| 136 | 저소득층 인터넷 지원(ACP) 종료 | FCC ACP, Lifeline Support |
| 138 | 겨울 한파 대비 | Ready.gov Winter Weather, NWS Cold safety |

## 3차 (10편, 2026-10-19) — 적용·공개 화면 확인 완료
| 글 번호 | 제목 | 붙인 링크 |
|---|---|---|
| 22 | 커뮤니티칼리지에서 4년제 편입 | University System of Georgia, Technical College System of Georgia, Federal Student Aid |
| 41 | 홈 인스펙션 | HUD Buying a Home, CFPB Owning a Home |
| 42 | 지진 대비 | Ready.gov Earthquakes, USGS Earthquake Hazards Program |
| 43 | 정전 대비 | Ready.gov Power Outages |
| 44 | 공립학교 ESL | 교육부 NCELA, Georgia Department of Education |
| 45 | AP 과목 | College Board AP Students, AP credit and placement policies |
| 46 | 모바일 핫스팟 | FCC Broadband Labels |
| 48 | 델라웨어 사업자 등록 | Delaware Division of Corporations, IRS LLC |
| 50 | 자급제폰 | FCC Cell Phone Unlocking |
| 51 | 우회전 신호(Right on Red) | Georgia DDS (구체 규정 페이지는 확인 못 해 메인만) |

## 4차 (10편, 2026-10-19) — 적용·공개 화면 확인 완료
| 글 번호 | 제목 | 붙인 링크 |
|---|---|---|
| 52 | 특수교육 IEP·504 | 교육부 IDEA, 조지아 교육부 |
| 53 | 아파트·타운하우스·단독주택 렌트 | HUD Rental Assistance, HUD Georgia |
| 55 | 토네이도 경보 | NWS Tornado safety, Ready.gov Tornadoes |
| 57 | 어린이 카시트 | NHTSA Car Seats, 조지아 고속도로안전국(GOHS) |
| 58 | 번호 이동(포팅) | FCC Porting |
| 59 | 가족 요금제 비교 | FCC Broadband Labels |
| 60 | 사업용 신용카드 | CFPB Credit cards |
| 61 | 고속도로 통행료 | Georgia Peach Pass, E-ZPass Group |
| 68 | 렌터카 CDW·LDW 보험 | 조지아 보험 커미셔너(OCI) |
| 111 | 조지아 폭염·허리케인 | Ready.gov Extreme Heat, NHC Prepare, NWS Heat safety |

## 5차 (13편, 2026-10-19) — 적용·공개 화면 확인 완료, 마지막
| 글 번호 | 제목 | 붙인 링크 |
|---|---|---|
| 112 | 휴대폰 요금제 고르는 법 | FCC Broadband Labels |
| 113 | MARTA 이용 가이드 | MARTA, Breeze Card |
| 114 | 529 플랜 | Investor.gov 529, IRS 529 Q&A |
| 115 | 여름 캠프·방과후 | 조지아 DECAL, 애틀랜타시 |
| 119 | LLC 설립 절차 | IRS LLC, IRS EIN 안내 |
| 126 | 공립학교 등록·학군 | 조지아 교육부 |
| 127 | 전기세 절약 | 에너지부 Energy Saver |
| 128 | 이사 후 행정 처리 | USPS 주소 변경, 조지아 DDS |
| 129 | 한인 밀집 지역 Best 5 | Census QuickFacts(귀넷) |
| 131 | 조지아 이사 가이드 | Georgia.gov, 조지아 DDS |
| 139 | Common App 마감일 | Common App |
| 140 | 자동차 리스 vs 구매 | CFPB Auto loans |
| 141 | 추수감사절 여행 | TSA, Georgia 511 |

## 결과
- 53편 모두 완료. 같은 방법으로 다시 세어 보니 공개 282편 중 **외부 출처 링크가 없는 글 0편**.
- 약한 링크(넓은 페이지만 붙인 글): 51(우회전), 46·59·112(FCC 브로드밴드 라벨), 123(HUD), 126·127·129·131·128·130·120 등 기관 메인 위주. 더 구체적인 공식 페이지를 찾으면 바꾸기.

## 아직 하지 않은 것
- 겹치는 글 합치기(H-1B 3편, 분기별 추정세 2편, 메디케어 Part B 2편, 비자 공고 2편): 별도 승인 필요(301 이동).
- 링크가 글 내용과 정확히 맞는지는 제목·주제 기준으로 고른 것. 세율·한도 같은 숫자가 맞는지는 이 작업이 확인하지 않음.

## 겹치는 글 합치기 (2026-10-19) — 적용·확인 완료
같은 주제로 겹친 글 5편을 비공개로 돌리고, 옛 주소는 남긴 글로 301(영구 이동) 되게 했다. (`config/info_redirects.php` + `InfoPublicController::show`)
| 내린 글 | → 남긴 글 | 이유 |
|---|---|---|
| 132 H-1B $10만 수수료 일시 중단 정리 | 40 H-1B 10만 달러 수수료, 연방법원이 또 막았다 | 같은 주제 3편 중 가장 긴 글 |
| 30 H-1B … 또 막았다 (2026년 10월 최신) | 40 | 위와 같음 |
| 49 프리랜서 분기별 예상세 | 17 자영업자 분기별 추정세 | 같은 주제, 더 긴 글 |
| 135 메디케어 Part B 인상 + COLA | 27 메디케어 Part B 202.90달러 인상 | 같은 주제, 더 긴 글(COLA 설명도 포함) |
| 133 2026년 10월 비자 공고 | 39 2026년 10월 비자 벌러틴 | 39 가 구체적인 우선일을 담음 |
- 확인: 옛 주소 5개 모두 301, 남긴 글 200, 사이트맵에서 옛 주소 빠짐, 다른 글에서 옛 글로 가는 내부 링크 없음.
- 되돌리기: 관리자 > 정보 관리에서 글을 다시 공개 + `config/info_redirects.php` 에서 그 줄 삭제.

## 약한 링크 교체 (2026-10-19)
기관 메인만 붙였던 글 5편을 확인된 구체 페이지로 교체: 51(DDS Manuals), 130(DDS New License + Manuals), 120(OCI Auto insurance), 131(Georgia.gov Moving to Georgia + DDS New License), 128(DDS Update or Replace License). 모두 200 과 페이지 제목 확인.
아직 넓은 링크인 글: 46·59·112(FCC 브로드밴드 라벨), 123(HUD Georgia), 126(GaDOE 메인), 127(DOE Energy Saver 메인), 129(Census) — 사이트가 스크립트로 그려져 구체 페이지를 찾지 못함.

## 새 글 점검 (2026-10-19)
자동 생성 글이 출처 없이 공개되지 않게 `InfoIngestController::store` 에 검사를 넣음: 공개 요청이어도 본문에 외부 출처 링크(awesomekorean.com·creativecommons.org 제외)가 없으면 **초안으로 저장**하고 응답에 `published:false, held_reason:no_source_link` 를 돌려줌. 관리자 > 정보 관리에서 출처를 붙인 뒤 공개.
