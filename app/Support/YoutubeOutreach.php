<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * 한인 유튜버에게 보낼 안내 메시지(한글·영어 × DM·이메일 = 4종)를 관리자가 고쳐 쓰고 저장하는 곳.
 * 저장 위치: site_settings.youtuber_outreach (JSON). 값이 없으면 아래 기본 문구를 쓴다.
 * 문구 안의 {채널명} {채널주소} {보내는이름} {연락이메일} (영어 문구는 {channel} {url} {name} {email})은 보낼 때 채널 정보로 바뀐다.
 */
class YoutubeOutreach
{
    public const KEY = 'youtuber_outreach';
    public const TYPES = ['ko_dm', 'ko_email', 'en_dm', 'en_email'];

    public static function defaults(): array
    {
        $koDm = <<<'TXT'
안녕하세요, {채널명} 채널 운영자님! 미국 한인 커뮤니티 '어썸코리안(awesomekorean.com)'을 운영하는 {보내는이름}입니다.

저희 사이트의 "한인 유튜버" 코너에 {채널명}을 소개해 두었어요.
👉 {채널주소}

채널 정보와 영상은 유튜브 공식 임베드로만 보여 드리고, 영상을 내려받거나 다시 올리지 않습니다. 등록과 이용은 모두 무료예요.

채널 주인이시라면 무료로 "주인 인증"을 하실 수 있고, 인증하면
• ✔ 인증 배지
• 사이트에서 바로 "구독하기" 버튼
• 영상·쇼츠 이전/다음 보기
• 목록 맨 위로 끌어올리기 (하루 1번)
• 소개 문구 직접 수정
을 쓰실 수 있습니다.

인증은 채널 페이지의 "내 채널 인증하기"를 누르고, 받은 코드를 유튜브 채널 '설명'에 붙여 넣은 뒤 "인증 확인하기"를 누르면 끝이에요. (비밀번호 필요 없음)

혹시 소개를 원치 않으시면 이 메시지에 답장만 주세요. 바로 내리겠습니다. 감사합니다 😊
TXT;
        $koEmail = <<<'TXT'
안녕하세요, {채널명} 운영자님.
미국 한인 커뮤니티 어썸코리안(https://awesomekorean.com)을 운영하는 {보내는이름}입니다.

저희는 미국에 사는 한인들이 생활 정보를 얻고 서로 연결되는 커뮤니티 사이트이고, 최근 "한인 유튜버" 코너를 열었습니다. 미국에서 활동하시는 {채널명}이 좋아 보여 먼저 소개해 두었습니다.

▶ 소개된 페이지: {채널주소}

[어떻게 보이나요]
• 채널 이름, 프로필, 구독자 수, 최근 영상과 쇼츠를 보여 드립니다.
• 영상은 유튜브 공식 임베드로 재생되어, 조회수는 그대로 채널에 쌓입니다. 영상을 내려받거나 다시 올리지 않습니다.
• 등록과 이용은 모두 무료입니다.

[채널 주인이시라면 — 무료 "주인 인증"]
인증하시면 아래를 쓰실 수 있습니다.
1) ✔ 인증 배지 (목록과 채널 페이지)
2) 사이트에서 바로 "구독하기" 버튼 (인증 전에는 "유튜브에서 보기" 링크만 있습니다)
3) 영상·쇼츠 이전/다음 보기
4) 목록 맨 위로 끌어올리기 (하루 1번)
5) 소개 문구와 분야를 직접 수정

[인증 방법 — 약 3분]
1. 위 채널 페이지에서 "내 채널 인증하기"를 누릅니다. (어썸코리안 회원가입과 이메일 인증이 필요합니다)
2. 안내되는 인증 코드를 복사합니다.
3. 유튜브 스튜디오 → 맞춤설정 → 기본 정보 → '설명' 맨 위에 코드를 붙여 넣고 "게시"를 누릅니다.
4. 사이트로 돌아와 "인증 확인하기"를 누릅니다. 인증 후에는 코드를 지우셔도 됩니다.
채널 설명은 주인만 고칠 수 있기 때문에, 비밀번호나 로그인 정보 없이 주인 확인이 됩니다.

소개를 원치 않으시거나 수정할 내용이 있으면 이 메일에 답장해 주세요. 바로 반영하겠습니다.

감사합니다.
{보내는이름} / 어썸코리안
{연락이메일}
TXT;
        $enDm = <<<'TXT'
Hi {channel} team! I run AwesomeKorean (awesomekorean.com), a community site for Korean Americans in the U.S.

We've featured your channel in our new "Korean YouTubers" section:
👉 {url}

We only show your channel info and videos through YouTube's official embed — nothing is downloaded or re-uploaded, and views count toward your channel. It's free.

If you own the channel, you can verify it for free and get:
• ✔ a verified badge
• a "Subscribe" button right on our page
• prev / next navigation for your videos and Shorts
• a daily "bump" to the top of the list
• the ability to edit your intro text

To verify: tap "Verify my channel" on your page, paste the code we give you into your YouTube channel description, then tap "Confirm." No password needed.

If you'd rather not be listed, just reply and we'll remove it right away. Thanks! 😊
TXT;
        $enEmail = <<<'TXT'
Hi {channel} team,

I'm {name} from AwesomeKorean (https://awesomekorean.com), a community site for Korean Americans in the U.S. We recently launched a "Korean YouTubers" section and added your channel because we think our members would enjoy it.

Your page: {url}

What we show
• Your channel name, profile picture, subscriber count, and recent videos and Shorts.
• Videos play through YouTube's official embed, so views count toward your channel. We never download or re-upload your content.
• Listing and use are free.

Verify ownership (free) to get
1) A ✔ verified badge
2) A "Subscribe" button on our page (unverified channels only show a "Watch on YouTube" link)
3) Previous / next navigation for videos and Shorts
4) A daily "bump" to the top of the list
5) The ability to edit your intro text and category

How to verify (about 3 minutes)
1. Open your page above and tap "Verify my channel." (A free AwesomeKorean account with a confirmed email is required.)
2. Copy the verification code shown.
3. In YouTube Studio go to Customization → Basic info, paste the code at the top of your Description, and tap Publish.
4. Come back and tap "Confirm." You can delete the code afterward.
Only the channel owner can edit the description, so ownership is confirmed without any password.

If you'd prefer not to be listed, or want anything changed, just reply to this email and we'll take care of it right away.

Thank you,
{name} / AwesomeKorean
{email}
TXT;
        return [
            'sender_name' => '',
            'contact_email' => '',
            'templates' => [
                'ko_dm' => ['label' => '한글 DM', 'subject' => '', 'body' => $koDm],
                'ko_email' => ['label' => '한글 이메일', 'subject' => '{채널명} 채널을 어썸코리안 "한인 유튜버" 코너에 소개했습니다', 'body' => $koEmail],
                'en_dm' => ['label' => 'English DM', 'subject' => '', 'body' => $enDm],
                'en_email' => ['label' => 'English Email', 'subject' => 'We featured {channel} in AwesomeKorean\'s "Korean YouTubers" section', 'body' => $enEmail],
            ],
        ];
    }

    /** 저장된 값(없으면 기본값). 빠진 칸은 기본값으로 채운다. */
    public static function get(): array
    {
        $d = self::defaults();
        $raw = SiteSetting::where('key', self::KEY)->value('value');
        $saved = $raw ? json_decode($raw, true) : null;
        if (!is_array($saved)) return $d;
        $out = $d;
        foreach (['sender_name', 'contact_email'] as $k) if (isset($saved[$k]) && is_string($saved[$k])) $out[$k] = $saved[$k];
        foreach (self::TYPES as $t) {
            foreach (['subject', 'body'] as $f) {
                if (isset($saved['templates'][$t][$f]) && is_string($saved['templates'][$t][$f])) $out['templates'][$t][$f] = $saved['templates'][$t][$f];
            }
        }
        return $out;
    }

    /** 관리자가 보낸 값을 검사해 저장. 실패하면 [null, '쉬운 안내 문구'] */
    public static function save(array $in): array
    {
        $cur = self::get();
        $name = trim((string) ($in['sender_name'] ?? ''));
        $email = trim((string) ($in['contact_email'] ?? ''));
        if (mb_strlen($name) > 60) return [null, '보내는 사람 이름은 60자까지 쓸 수 있어요.'];
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return [null, '연락 이메일 형식이 올바르지 않아요.'];
        $cur['sender_name'] = $name;
        $cur['contact_email'] = $email;
        foreach (self::TYPES as $t) {
            $subject = (string) ($in['templates'][$t]['subject'] ?? $cur['templates'][$t]['subject']);
            $body = (string) ($in['templates'][$t]['body'] ?? $cur['templates'][$t]['body']);
            if (mb_strlen($subject) > 200) return [null, $cur['templates'][$t]['label'] . ' 제목은 200자까지 쓸 수 있어요.'];
            if (trim($body) === '' || mb_strlen($body) > 6000) return [null, $cur['templates'][$t]['label'] . ' 내용은 1~6000자로 써 주세요.'];
            $cur['templates'][$t]['subject'] = $subject;
            $cur['templates'][$t]['body'] = $body;
        }
        SiteSetting::updateOrCreate(['key' => self::KEY], ['value' => json_encode(['sender_name' => $cur['sender_name'], 'contact_email' => $cur['contact_email'],
            'templates' => array_map(fn ($x) => ['subject' => $x['subject'], 'body' => $x['body']], $cur['templates'])], JSON_UNESCAPED_UNICODE)]);
        Cache::forget('site_settings_public');
        return [self::get(), null];
    }
}
