<?php

namespace App\Services;

/**
 * 욕설·금칙어 필터
 *
 * - 한글 욕설 + 영어 욕설 + 우회 패턴 (숫자/특수문자 끼워넣기) 대응
 * - 채팅 / 댓글 / 게시글 전반에서 사용
 * - contains(): 금칙어 포함 여부
 * - firstMatch(): 첫 매칭 금칙어 반환 (UI 에 보여주기 용)
 * - clean(): 금칙어 마스킹 (선택적 사용)
 */
class BadWordFilter
{
    /** 한글 욕설 (원형) */
    private const KOREAN = [
        '시발','씨발','씨팔','시팔','쌍놈','쌍년','개새','개새끼','개년','개놈','개자식',
        '병신','븅신','빙신','미친놈','미친년','미친새끼','미친개','미쳣','미친것',
        '존나','졸라','좆','좃','좇','씹','씨1발','ㅅㅂ','ㅆㅂ','ㅄ','ㅂㅅ','ㅈㄹ','ㅈ같','ㅄ같',
        '좆같','좃같','좇같','엿같','엿먹','꺼져','뒤져','뒤질','뒈져','뒈질','죽여','죽일',
        '창녀','창놈','갈보','걸레','호로','호로새끼','후장','애미','애비','니애미','니애비',
        '니엄마','니아빠','느금마','느금빠','늬미','늬애미','ㄴㄱㅁ','패드립','패듀립',
        '보지','자지','좆물','니미','지랄','지럴','지럴하','좃밥','좆밥','염병','염통','니미럴',
        '개같','개한','개빡','빡침','빡치','쥐랄','개수작','수작부리','꼰대','호구','홍어',
        '노무현','이명박','박근혜','문재앙','좌좀','우좀','일베','이기야','한남','한녀',
        '김치녀','된장녀','군무새','맘충','급식충','틀딱','개독','개슬람','짱개','쪽바리','쪽발이',
        '섹스','쎅스','ㅅㅅ','야동','섹시','야설','자위','꼴리','꼴려','야한','성기',
    ];

    /** 영어 욕설 */
    private const ENGLISH = [
        'fuck','fck','fuk','f*ck','f u c k','sh!t','shit','bitch','b!tch','btch',
        'asshole','a$$hole','ahole','dick','cock','pussy','cunt','damn','bastard',
        'motherfucker','mf','wtf','stfu','fml','gtfo','retard','nigger','nigga','faggot','fag',
        'whore','slut','jerk','douche','prick','twat',
    ];

    /** 우회 패턴용 치환: 숫자/특수문자 → 유사 한글·영문 */
    private const NORMALIZE_MAP = [
        '1' => 'ㅣ','!' => 'ㅣ','l' => 'ㅣ',
        '0' => 'ㅇ','o' => 'ㅇ',
        '@' => 'a','$' => 's','3' => 'e','5' => 's','7' => 't',
    ];

    /**
     * 텍스트를 정규화 — 공백/특수문자 제거, 소문자화, 우회 문자 치환
     */
    public static function normalize(string $text): string
    {
        $t = mb_strtolower($text);
        // 우회 문자 치환
        $t = strtr($t, self::NORMALIZE_MAP);
        // 공백·탭·개행·반복되는 특수문자 제거 (한글 사이 끼워넣기 우회 대응)
        $t = preg_replace('/[\s\*\.\-\_\~\^\#\+\=\(\)\[\]\{\}\<\>\/\\\\\|\?\,\;\:\"\'\`]/u', '', $t);
        return $t ?? '';
    }

    /**
     * 금칙어 포함 여부
     */
    public static function contains(string $text): bool
    {
        return self::firstMatch($text) !== null;
    }

    /**
     * 첫 번째 매칭된 금칙어 반환 (없으면 null)
     */
    public static function firstMatch(string $text): ?string
    {
        if (trim($text) === '') return null;

        $normalized = self::normalize($text);

        foreach (self::KOREAN as $word) {
            $n = self::normalize($word);
            if ($n !== '' && mb_strpos($normalized, $n) !== false) {
                return $word;
            }
        }

        // 영어는 단어 경계로 부분매칭 — "class" 가 "ass" 에 걸리지 않도록
        $lowerText = mb_strtolower($text);
        foreach (self::ENGLISH as $word) {
            $n = self::normalize($word);
            if ($n === '') continue;
            // 정규화 된 텍스트에서 매칭 체크 — 짧은 영단어는 원본에서 단어경계로만
            if (mb_strlen($n) <= 4) {
                if (preg_match('/\b' . preg_quote($n, '/') . '\b/u', $lowerText)) {
                    return $word;
                }
            } else {
                if (mb_strpos($normalized, $n) !== false) {
                    return $word;
                }
            }
        }

        return null;
    }

    /**
     * 금칙어를 **** 로 마스킹 (선택 기능 — 현재는 사용 안 함, 하드 차단 방식)
     */
    public static function clean(string $text): string
    {
        $out = $text;
        foreach (array_merge(self::KOREAN, self::ENGLISH) as $word) {
            $mask = str_repeat('*', mb_strlen($word));
            $out = str_ireplace($word, $mask, $out);
        }
        return $out;
    }
}
