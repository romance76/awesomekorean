<?php

namespace App\Support;

/**
 * 숏츠 업로더(채널) 이름으로 한국어·영어권이 아닌 채널을 걸러낸다.
 * (2026-10-09 Kay 요청: 인도·방글라데시·중국 등 외국인 이름 채널은 아예 가져오지 않기)
 * 수집(FetchYoutubeShorts)과 저장된 영상 점검(shorts:audit-lang)이 같은 기준을 쓴다.
 * 한글이 들어간 채널명은 항상 통과.
 */
class ShortsChannelFilter
{
    // 남아시아·중동·동남아 이름/호칭 (Md, vai, Mia 등은 방글라데시식 이름에 흔한 말)
    private const NAME_TOKENS = 'md|mia|vai|bhai|bro|bd|hossain|hossian|hasan|islam|mondol|mandal|khan|ahmed|ahamed|uddin|sarkar|sorker|talukdar|ujjal|uzzal|samad|foysal|faisal|shamim|nabi|salman|sultan|abir|arif|azam|akram|tufael|kamal|korim|najmul|akikul|tanbir|masum|alamin|amin|yusuf|taher|abu|mukles|jahid|nizam|ruhul|mahtab|nasreen|saeed|chaudhry|shaikh|singh|kumar|sharma|patel|thakur|shivam|yash|kartik|priya|fatima|aabida|nazish|amna|jannat|afsha|ishaa|vaishali|harnam|krishna|ajay|brajkishor|gyani|baba|dhaba|rahul|rahulmalodia|sudeendra|pranathi|suriya|vinod|vinoddhaner|nuthu|pearle|maaney|dada|sultana|begum|bibi|anari|banglar|bangla|bengali|desi|kobiraz|nguyen|kelly vo|mkize|rubasha|otmane|svetlana|isa silva|jiang|sichuan|wai yan tun|shree|pagla|bandu|dinu|anay|anushka|meesho|nasim|sazia|moderato';
    // 번호 붙여 대량 생산하는 조회수용 채널에 흔한 이름
    private const FARM_WORDS = 'funny video|fun video|fun family|funny short|unlimited fun|unlimited funny|normal video|unique funny|funny adda|village (entertainment|shorts|cooking)|virul|virul entertainment|comedy box|comedy funny|shorts viral|viral jahid|video random|usa funny|usafunny|girls r funny|ns entertainment';

    public static function blocked(?string $channel): bool
    {
        $c = trim((string) $channel);
        if ($c === '') return false;
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $c)) return false; // 한글 채널명은 통과

        // 한자·그리스·키릴·아랍·인도계·태국 등 비한국 문자
        if (preg_match('/[\x{4E00}-\x{9FFF}]|[\x{0370}-\x{03FF}]|[\x{0400}-\x{04FF}]|[\x{0600}-\x{06FF}]|[\x{0900}-\x{0DFF}]|[\x{0E00}-\x{0E7F}]|[\x{1000}-\x{109F}]|[\x{3040}-\x{30FF}]|[\x{11000}-\x{1107F}]/u', $c)) return true;

        // 이름 뒤에 번호가 붙은 채널(대량 생산 계정): "Ajay sarkar345", "Fun Family 900", "Official 138"
        if (preg_match('/[A-Za-z]\s?[\d.]{2,}\s*["”]?\s*$/u', $c)) return true;
        if (preg_match('/\b(official|vai|bro|mia)\s*\d+/i', $c)) return true;

        if (preg_match('/(?<![A-Za-z])(' . self::NAME_TOKENS . ')(?![A-Za-z])/i', $c)) return true;
        if (preg_match('/(' . self::FARM_WORDS . ')/i', $c)) return true;
        return false;
    }
}
