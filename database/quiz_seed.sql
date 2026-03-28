-- 채팅방 시드
INSERT INTO chat_rooms (name, slug, type, region, description, icon, is_open, created_at, updated_at) VALUES
('LA 한인 채팅', 'la-korean', 'regional', 'LA', '로스앤젤레스 한인 커뮤니티 채팅방', '🌴', 1, NOW(), NOW()),
('뉴욕 한인 채팅', 'ny-korean', 'regional', 'NY', '뉴욕 한인 커뮤니티 채팅방', '🗽', 1, NOW(), NOW()),
('시카고 한인 채팅', 'chicago-korean', 'regional', 'Chicago', '시카고 한인 커뮤니티 채팅방', '🌆', 1, NOW(), NOW()),
('시애틀 한인 채팅', 'seattle-korean', 'regional', 'Seattle', '시애틀 한인 커뮤니티 채팅방', '☕', 1, NOW(), NOW()),
('24시간 오픈채팅', 'open-chat', 'open', NULL, '누구나 참여하는 24시간 오픈채팅방', '💬', 1, NOW(), NOW()),
('취업/이민 정보', 'jobs-info', 'theme', NULL, '취업, 이민 비자 정보 공유', '💼', 1, NOW(), NOW()),
('음식/맛집', 'food-talk', 'theme', NULL, '맛있는 음식과 한식 레시피 이야기', '🍱', 1, NOW(), NOW()),
('육아/교육', 'parenting', 'theme', NULL, '미국에서 아이 키우기, 교육 정보', '👶', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();

-- 퀴즈 시드 (한인 문화/역사/일상)
INSERT INTO quiz_questions (category, question, option_a, option_b, option_c, option_d, correct_answer, explanation, points, is_active, created_at, updated_at) VALUES
('culture', '한국의 국경일 "광복절"은 몇 월 며칠인가요?', '3월 1일', '8월 15일', '10월 3일', '10월 9일', 'B', '광복절(光復節)은 1945년 8월 15일 일제로부터 해방된 날을 기념하는 국경일입니다.', 10, 1, NOW(), NOW()),
('culture', '한국의 전통 명절 "추석"은 음력 몇 월에 있나요?', '음력 7월', '음력 8월', '음력 9월', '음력 10월', 'B', '추석은 음력 8월 15일로, 한국의 대표적인 추수감사 명절입니다.', 10, 1, NOW(), NOW()),
('language', '한국어로 "감사합니다"를 영어로 올바르게 번역하면?', 'Excuse me', 'I am sorry', 'Thank you', 'Good morning', 'C', '"감사합니다"는 영어로 "Thank you"입니다.', 10, 1, NOW(), NOW()),
('history', '한국의 첫 번째 대통령은 누구인가요?', '박정희', '윤보선', '이승만', '김영삼', 'C', '이승만은 대한민국 초대 대통령으로 1948년부터 1960년까지 재임했습니다.', 10, 1, NOW(), NOW()),
('culture', '한국의 전통 음식 "김치"의 주 재료는 무엇인가요?', '오이', '배추', '무', '양배추', 'B', '가장 일반적인 김치인 배추김치의 주 재료는 배추(Chinese cabbage)입니다.', 10, 1, NOW(), NOW()),
('general', '미국에서 운전면허 필기시험(DMV)은 몇 살부터 볼 수 있나요?', '14세', '15세', '16세', '18세', 'C', '대부분의 주에서 운전 허가증(Learner Permit)은 16세부터 신청할 수 있습니다.', 10, 1, NOW(), NOW()),
('language', '영어 "I miss you"를 한국어로 하면?', '사랑해', '보고 싶어', '좋아해', '행복해', 'B', '"I miss you"는 한국어로 "보고 싶어" 또는 "보고 싶습니다"입니다.', 10, 1, NOW(), NOW()),
('culture', '한국의 전통 혼례에서 신랑신부가 절을 하는 것을 무엇이라 하나요?', '큰절', '교배례', '합근례', '폐백', 'B', '교배례(交拜禮)는 신랑과 신부가 서로 맞절을 하는 전통 혼례 의식입니다.', 10, 1, NOW(), NOW()),
('general', '미국의 사회보장번호(SSN)는 몇 자리 숫자인가요?', '7자리', '8자리', '9자리', '10자리', 'C', 'SSN(Social Security Number)은 XXX-XX-XXXX 형식의 9자리 숫자입니다.', 10, 1, NOW(), NOW()),
('history', '한국이 처음으로 올림픽에 참가한 년도는?', '1932년', '1936년', '1948년', '1952년', 'C', '대한민국은 1948년 런던 올림픽에 처음으로 독립국으로 참가했습니다.', 10, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();
