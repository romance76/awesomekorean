<?php

namespace App\Traits;

/**
 * Detail 응답에 prev/next id·title 주입 공통 트레이트.
 * Kay 요청: 모든 상세 페이지 하단 "이전글 / 목록 / 다음글" 공통 노출.
 */
trait HasAdjacent
{
    /**
     * @param  string $modelClass      Eloquent 모델 (예: \App\Models\RealEstateListing::class)
     * @param  int    $currentId
     * @param  string $titleCol        표시할 타이틀 컬럼 (기본 'title')
     * @param  array  $scope           [컬럼 => 값] 같은 카테고리 내에서만 prev/next (선택)
     * @return array                   ['prev' => [...], 'next' => [...]]
     */
    protected function adjacentPair(string $modelClass, int $currentId, string $titleCol = 'title', array $scope = []): array
    {
        $base = $modelClass::query();
        foreach ($scope as $col => $val) {
            if ($val !== null && $val !== '') $base->where($col, $val);
        }
        // 활성 상태 자동 반영 (is_active 컬럼 있을 때)
        $model = new $modelClass;
        if (method_exists($model, 'getAttributes') && array_key_exists('is_active', $model->getAttributes() ?: [])) {
            $base->where('is_active', true);
        }

        $prev = (clone $base)->where('id', '<', $currentId)
            ->orderBy('id', 'desc')->first(['id', $titleCol]);
        $next = (clone $base)->where('id', '>', $currentId)
            ->orderBy('id', 'asc')->first(['id', $titleCol]);

        return [
            'prev' => $prev ? ['id' => $prev->id, 'title' => $prev->$titleCol ?? ''] : null,
            'next' => $next ? ['id' => $next->id, 'title' => $next->$titleCol ?? ''] : null,
        ];
    }
}
