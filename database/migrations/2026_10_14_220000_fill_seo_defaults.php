<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 구글·네이버 검색용 기본 문구를 관리자 > 사이트 설정(SEO / 회사 정보)에 채운다.
// - 비어 있는 칸(또는 예전 자리표시 기본값)만 채운다. 직접 쓴 값은 절대 덮어쓰지 않는다.
// - 값은 App\Support\SeoHead 가 사이트 <head> 의 제목·설명·키워드·공유 미리보기에 쓴다. 관리자 화면에서 언제든 고칠 수 있다.
// - 여러 번 돌려도 안전(채운 뒤에는 비어 있지 않으므로 건너뜀).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) return;

        $title = '어썸코리안 | 미국 한인 커뮤니티·구인구직·중고장터·부동산';
        $desc = '미국 한인을 위한 커뮤니티 어썸코리안(AwesomeKorean). 구인구직·중고장터·부동산·한인 업소록, 영주권·세금·생활 정보와 맛집 후기, 이웃과의 Q&A·오픈 채팅방까지 한곳에서 만나보세요.';
        $keywords = '미국 한인 커뮤니티, 어썸코리안, AwesomeKorean, 한인 구인구직, 한인 중고장터, 미국 부동산, 한인 업소록, 한인 맛집, 영주권 정보, 미국 이민 정보, 미국 생활 정보, 미국 세금 정보, 한인 Q&A, 한인 오픈 채팅, 애틀랜타 한인, 조지아 한인';

        // [칸 이름, 채울 값, 비어 있는 것으로 보는 예전 기본값]
        $fill = [
            ['seo_meta_title', $title, ['AwesomeKorean - 미국 한인 커뮤니티', 'AwesomeKorean — 미국 한인 커뮤니티']],
            ['seo_meta_description', $desc, []],
            ['meta_description', $desc, []],
            ['meta_keywords', $keywords, ['한인, 커뮤니티, 미국, LA']],
        ];
        foreach ($fill as [$key, $value, $oldDefaults]) {
            $cur = DB::table('site_settings')->where('key', $key)->value('value');
            $isEmpty = $cur === null || trim((string) $cur) === '' || in_array(trim((string) $cur), $oldDefaults, true);
            if ($isEmpty) SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    public function down(): void
    {
        // 설정값이라 되돌릴 때 지우지 않는다
    }
};
