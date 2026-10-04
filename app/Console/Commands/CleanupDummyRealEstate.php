<?php

namespace App\Console\Commands;

use App\Models\RealEstateListing;
use Illuminate\Console\Command;

/**
 * RealEstateSeeder.php가 초창기에 심어둔 더미 매물 35개를 정리한다 (소프트 운영 전환,
 * 1회성 청소 명령). 더미 시드는 실제 회원 user_id를 빌려 썼기 때문에 user_id로는
 * 구분이 안 되고, 아래 3가지 모두 일치하는 경우에만 삭제 대상으로 본다:
 *   1) 제목이 시더의 고정 35개 문자열 중 하나와 정확히 일치
 *   2) images가 비어있음 (시더가 사진을 전혀 넣지 않음, 실제 회원 매물은 보통 사진 있음)
 *   3) contact_email이 시더 특유의 realestateN@example.com 패턴
 * 기본은 미리보기만 하고, --force를 줘야 실제로 삭제한다.
 */
class CleanupDummyRealEstate extends Command
{
    protected $signature = 'realestate:cleanup-dummy-seed {--force : 실제로 삭제 실행 (기본은 미리보기만)}';
    protected $description = 'RealEstateSeeder가 심어둔 더미 매물 35개를 안전하게 정리 (실제 회원 매물은 건드리지 않음)';

    private const DUMMY_TITLES = [
        '아틀란타 스튜디오 렌트', 'Duluth 1BR 아파트 렌트', 'Suwanee 2BR 타운홈 렌트',
        'Atlanta 3BR 하우스 렌트', 'LA Koreatown 4BR 펜트하우스', 'Irvine 스튜디오 (가구 포함)',
        'Flushing 1BR 렌트 (역세권)', 'Fort Lee 2BR 한인밀집지역', 'Atlanta 룸메이트 구함 (여성)',
        'LA 한인타운 룸메이트 남성', 'Duluth 단기 민박 (월단위)', 'Atlanta 오피스 렌트 500sqft',
        'Koreatown 상가 렌트 (1층)', 'Dallas 소매 공간 렌트', 'Seattle 하우스 지하 렌트',
        'Carrollton 1BR 신축 아파트', 'Atlanta Midtown 2BR 럭셔리', 'Fort Lee 스튜디오 즉시입주',
        'Suwanee 학군좋은 3BR 하우스', 'Atlanta 소규모 건물 렌트',
        'Atlanta 4BR 하우스 매매', 'Suwanee 학군좋은 5BR 하우스', 'LA 3BR 하우스 (한인타운 근처)',
        'Atlanta condo 매매', 'Flushing 2BR 콘도 (신축)', 'Fort Lee 1BR 리버뷰 콘도',
        'Carrollton 듀플렉스 매매', 'Irvine 빌라 매매 (수영장)', 'Duluth 타운하우스 매매',
        'Atlanta Midtown 타운하우스', 'Seattle 유닛 매매', 'Atlanta 오피스 빌딩 매매',
        'Koreatown 상가 매매 (1층)', 'Dallas 소규모 빌딩 매매', 'Atlanta 토지 매매 (0.5에이커)',
    ];

    public function handle(): int
    {
        $query = RealEstateListing::whereIn('title', self::DUMMY_TITLES)
            ->where(function ($q) {
                $q->whereNull('images')->orWhereJsonLength('images', 0);
            })
            ->where('contact_email', 'like', 'realestate%@example.com');

        $matched = $query->get(['id', 'title', 'city', 'contact_email']);

        if ($matched->isEmpty()) {
            $this->info('조건에 맞는 더미 매물이 없습니다 (이미 정리되었거나 원래 없음).');
            return self::SUCCESS;
        }

        $this->info("더미 매물 {$matched->count()}건 발견:");
        foreach ($matched as $row) {
            $this->line("  #{$row->id} {$row->title} ({$row->city}) - {$row->contact_email}");
        }

        if (!$this->option('force')) {
            $this->warn('미리보기만 했습니다. 실제로 삭제하려면 --force 옵션을 붙여 다시 실행하세요.');
            return self::SUCCESS;
        }

        $ids = $matched->pluck('id');
        $deleted = RealEstateListing::whereIn('id', $ids)->delete();
        $this->info("삭제 완료: {$deleted}건");

        return self::SUCCESS;
    }
}
