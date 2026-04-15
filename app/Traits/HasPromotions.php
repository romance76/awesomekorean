<?php

namespace App\Traits;

use App\Support\PromotionSettings;
use App\Support\StateNeighbors;
use Illuminate\Http\Request;

/**
 * 컨트롤러용 상위노출 공통 로직.
 * 사용법:
 *   class MarketController {
 *       use HasPromotions;
 *       protected string $promoResource = 'market';         // 필수: 설정 키
 *       protected string $promoModel = \App\Models\MarketItem::class;  // 필수
 *       protected string $promoCategoryColumn = 'category'; // 선택: 기본 'category'
 *   }
 *
 * 제공 메서드:
 *   handlePromote($id, Request $request)   → 공고/매물/물품에 상위노출 적용
 *   handlePromotionSlots(Request $request) → 슬롯 현황 조회
 *   applyPromotionOrdering($query, $userState, $hasLocation) → 리스트 쿼리에 우선순위
 *   excludeCrossTierPromotion($query, $hasLocation)         → 위치 모드에 따른 티어 제외
 */
trait HasPromotions
{
    protected function promoResourceName(): string
    {
        return property_exists($this, 'promoResource') ? $this->promoResource : 'jobs';
    }

    protected function promoModelClass(): string
    {
        return property_exists($this, 'promoModel') ? $this->promoModel : \App\Models\JobPost::class;
    }

    protected function promoCategoryColumn(): string
    {
        return property_exists($this, 'promoCategoryColumn') ? $this->promoCategoryColumn : 'category';
    }

    /** 리스트 쿼리에 위치 모드별 티어 제외 적용 */
    protected function excludeCrossTierPromotion($query, bool $hasLocation)
    {
        if ($hasLocation) {
            // 내 위치 모드: national 제외 (전국 탭에서만 보임)
            return $query->where('promotion_tier', '!=', 'national');
        }
        // 전국 모드: state_plus, sponsored 제외
        return $query->whereNotIn('promotion_tier', ['state_plus', 'sponsored']);
    }

    /** 리스트 쿼리에 프로모션 우선순위 ORDER BY 추가 */
    protected function applyPromotionOrdering($query, ?string $userState, bool $hasLocation)
    {
        $userState = $userState ? strtoupper(trim($userState)) : null;
        $neighbors = StateNeighbors::neighbors($userState);

        $statePlusCond = 'FALSE';
        if ($neighbors) {
            $parts = [];
            $neighborSqlList = [];
            foreach ($neighbors as $st) {
                if (preg_match('/^[A-Z]{2}$/', $st)) {
                    $parts[] = "JSON_CONTAINS(promotion_states, '\"" . $st . "\"')";
                    $neighborSqlList[] = "'{$st}'";
                }
            }
            if ($parts) {
                $fallback = '(promotion_states IS NULL OR JSON_LENGTH(promotion_states) = 0) AND state IN (' . implode(',', $neighborSqlList) . ')';
                $statePlusCond = '((' . implode(' OR ', $parts) . ') OR (' . $fallback . '))';
            }
        }

        if ($hasLocation && $userState) {
            $query->orderByRaw("CASE WHEN promotion_tier = 'state_plus' AND {$statePlusCond} THEN 1 ELSE 9 END");
        } else {
            $query->orderByRaw("CASE WHEN promotion_tier = 'national' THEN 1 ELSE 9 END");
        }
        return $query;
    }

    /** 만료된 프로모션 자동 해제 (index 앞부분에서 호출) */
    protected function expireStalePromotions(): void
    {
        $model = $this->promoModelClass();
        $model::where('promotion_tier', '!=', 'none')
            ->where('promotion_expires_at', '<', now())
            ->update(['promotion_tier' => 'none', 'promotion_expires_at' => null]);
    }

    /**
     * 공통 promote 로직.
     * $item 은 소유권 확인이 끝난 모델 인스턴스.
     */
    protected function handlePromote(\Illuminate\Database\Eloquent\Model $item, Request $request)
    {
        $request->validate([
            'tier' => 'required|in:national,state_plus,sponsored',
            'days' => 'required|integer|min:1|max:90',
        ]);

        $resource = $this->promoResourceName();
        $tier = $request->tier;
        $days = (int) $request->days;
        $dailyCost = PromotionSettings::pricePerDay($tier, $resource);
        $totalCost = $dailyCost * $days;
        $catCol = $this->promoCategoryColumn();
        $model = $this->promoModelClass();

        // state_plus 자동 주 확장
        $autoStates = null;
        if ($tier === 'state_plus') {
            if (!$item->state) {
                return response()->json([
                    'success' => false,
                    'message' => '주(State) 정보가 없어 state_plus 상위노출을 적용할 수 없습니다. 근무/매물/업소 위치의 State 를 먼저 입력해주세요.'
                ], 422);
            }
            $autoStates = StateNeighbors::neighbors($item->state);
        }

        // 슬롯 체크 (카테고리별)
        if ($tier !== 'sponsored') {
            if (!$item->{$catCol}) {
                return response()->json(['success' => false, 'message' => '카테고리/유형 정보가 필요합니다'], 422);
            }
            $slotQuery = $model::where('promotion_tier', $tier)
                ->where($catCol, $item->{$catCol})
                ->where('promotion_expires_at', '>', now());
            if ($tier === 'state_plus') {
                $stUp = strtoupper($item->state);
                $slotQuery->where(function ($q) use ($stUp) {
                    $q->whereJsonContains('promotion_states', $stUp)
                      ->orWhere(function ($inner) use ($stUp) {
                          $inner->where(function ($x) {
                              $x->whereNull('promotion_states')
                                ->orWhereRaw('JSON_LENGTH(promotion_states) = 0');
                          })->where('state', $stUp);
                      });
                });
            }
            $used = $slotQuery->count();
            $max = PromotionSettings::maxSlots($tier, $resource);
            if ($used >= $max) {
                $nextSlot = $slotQuery->orderBy('promotion_expires_at')->first();
                $when = $nextSlot?->promotion_expires_at?->format('Y-m-d H:i');
                $tierLabel = $tier === 'national' ? '전국 상위노출' : '주(State) 상위노출';
                return response()->json([
                    'success' => false,
                    'message' => "{$tierLabel} - '{$item->{$catCol}}' 카테고리 슬롯이 모두 사용 중입니다."
                        . ($when ? " {$when} 이후 가능합니다." : ''),
                    'data' => [
                        'is_full' => true,
                        'next_slot_time' => $nextSlot?->promotion_expires_at,
                        'used' => $used,
                        'max_slots' => $max,
                    ],
                ], 422);
            }
        }

        // 포인트 차감
        $user = auth()->user();
        if ($user->points < $totalCost) {
            return response()->json(['success' => false, 'message' => "포인트 부족. 필요: {$totalCost}P, 보유: {$user->points}P"], 422);
        }
        $user->addPoints(-$totalCost, "{$resource}_promotion", "{$resource} 상위노출 ({$tier}, {$days}일)");

        $expiresAt = now()->addDays($days);
        $item->update([
            'promotion_tier' => $tier,
            'promotion_expires_at' => $expiresAt,
            'promotion_states' => $autoStates,
        ]);

        return response()->json([
            'success' => true,
            'message' => '상위노출이 적용되었습니다'
                . ($autoStates ? ' (노출 주: ' . implode(', ', $autoStates) . ')' : ''),
            'data' => ['tier' => $tier, 'states' => $autoStates, 'expires_at' => $expiresAt],
        ]);
    }

    /** 슬롯 현황 조회 (카테고리/주 기준) */
    protected function handlePromotionSlots(Request $request)
    {
        $resource = $this->promoResourceName();
        $tier = $request->tier ?: 'national';
        $category = $request->category;
        $state = $request->state;
        $catCol = $this->promoCategoryColumn();
        $model = $this->promoModelClass();

        if (!$category) {
            return response()->json(['success' => false, 'message' => 'category 파라미터가 필요합니다'], 422);
        }

        $query = $model::where('promotion_tier', $tier)
            ->where($catCol, $category)
            ->where('promotion_expires_at', '>', now());

        if ($tier === 'state_plus') {
            if (!$state || !preg_match('/^[A-Z]{2}$/i', $state)) {
                return response()->json(['success' => false, 'message' => 'state_plus 는 state 가 필요합니다'], 422);
            }
            $stUp = strtoupper($state);
            $query->where(function ($q) use ($stUp) {
                $q->whereJsonContains('promotion_states', $stUp)
                  ->orWhere(function ($inner) use ($stUp) {
                      $inner->where(function ($x) {
                          $x->whereNull('promotion_states')
                            ->orWhereRaw('JSON_LENGTH(promotion_states) = 0');
                      })->where('state', $stUp);
                  });
            });
        }

        $active = $query->orderBy('promotion_expires_at')->get(['id', 'promotion_expires_at']);
        $used = $active->count();
        $max = PromotionSettings::maxSlots($tier, $resource);
        return response()->json([
            'success' => true,
            'data' => [
                'tier' => $tier,
                'category' => $category,
                'state' => $tier === 'state_plus' ? strtoupper($state) : null,
                'max_slots' => $max,
                'used' => $used,
                'available' => max($max - $used, 0),
                'is_full' => $used >= $max,
                'daily_cost' => PromotionSettings::pricePerDay($tier, $resource),
                'next_slot_time' => $used >= $max ? $active->first()?->promotion_expires_at : null,
            ],
        ]);
    }
}
