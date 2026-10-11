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
            // 내 위치 모드: 전국구(national)는 돈 내고 "전 지역 노출"을 산 것이므로
            // 반경 밖이어도 포함 — 정렬은 applyPromotionOrdering 에서 내 주(state_plus)
            // 다음 순위. 로컬 상위노출이 없으면 맨 위로 올라옴.
            // (nearby() 의 HAVING distance < r 에 OR 로 붙임 — HAVING 이 없으면 건드리지 않음.
            //  ONLY_FULL_GROUP_BY 는 HAVING 에서 일반 컬럼을 거부하므로 distance 처럼 별칭으로 계산)
            if (!empty($query->getQuery()->havings)) {
                $query->addSelect(\Illuminate\Support\Facades\DB::raw("(promotion_tier = 'national') AS promo_national"))
                      ->orHaving('promo_national', '=', 1);
            }
            return $query;
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
            $query->orderByRaw("CASE WHEN promotion_tier = 'state_plus' AND {$statePlusCond} THEN 1 WHEN promotion_tier = 'national' THEN 2 ELSE 9 END");
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
     * 결제 방법(Kay 결정 2026-10-11): pay_with = points(기본, 바로 적용) | card(카드 확인 후 바로 청구 → 적용, 환불 없음)
     *   card 는 2단계: ① 이 함수가 카드 보류를 만들고 client_secret 을 돌려줌(needs_card) → ② handlePromoteCardConfirm 이 청구·적용
     */
    protected function handlePromote(\Illuminate\Database\Eloquent\Model $item, Request $request)
    {
        $request->validate([
            'tier' => 'required|in:national,state_plus,sponsored',
            'days' => 'required|integer|min:1|max:90',
            'pay_with' => 'nullable|in:points,card',
        ]);

        if ($deny = $this->promotionActiveError($item)) return $deny;

        $resource = $this->promoResourceName();
        $tier = $request->tier;
        $days = (int) $request->days;
        $dailyCost = PromotionSettings::pricePerDay($tier, $resource);
        $totalCost = $dailyCost * $days;

        [$deny, $autoStates] = $this->promotionSlotCheck($item, $tier, $resource);
        if ($deny) return $deny;

        $user = auth()->user();

        // ── 카드 결제: 보류만 만들고 화면에서 카드를 확인하게 한다 (청구·적용은 confirm 에서) ──
        if ($request->input('pay_with') === 'card') {
            $cents = \App\Support\AdPayments::cents($totalCost);   // 가격 숫자 = 센트 (100 = $1.00), Stripe 최소 $0.50
            try {
                [$payment, $secret] = app(\App\Support\DirectPayments::class)->begin(
                    $user, \App\Models\Payment::KIND_PROMOTION, get_class($item), (int) $item->getKey(), $cents,
                    $this->promotionLabel($resource, $tier, $days)
                );
            } catch (\Throwable $e) {
                \Log::error('상위노출 카드 결제 생성 실패: ' . $e->getMessage());
                return response()->json(['success' => false, 'message' => '카드 결제를 시작하지 못했어요. 잠시 후 다시 시도해 주세요.'], 502);
            }
            return response()->json([
                'success' => true,
                'message' => '카드를 확인하면 바로 청구되고 상위노출이 적용돼요. 카드 결제는 환불되지 않아요.',
                'data' => ['needs_card' => true, 'payment_id' => $payment->id, 'client_secret' => $secret, 'total_cents' => $cents, 'tier' => $tier, 'days' => $days],
            ]);
        }

        // ── 포인트 결제 (기존 그대로) ──
        if ($user->points < $totalCost) {
            return response()->json(['success' => false, 'message' => "포인트 부족. 필요: {$totalCost}P, 보유: {$user->points}P"], 422);
        }
        $user->addPoints(-$totalCost, "{$resource} 상위노출 ({$tier}, {$days}일)", "{$resource}_promotion");   // (사유, 종류) 순서

        return $this->activatePromotion($item, $tier, $days, $autoStates, 'points');
    }

    /**
     * 카드 결제 상위노출 ② — 카드 확인(보류)이 끝난 뒤 호출. 슬롯을 다시 확인하고 바로 청구한 다음 적용한다.
     * 요청: payment_id, tier, days (금액이 지금 가격과 정확히 같아야 함 — 싸게 결제하고 비싼 노출을 받는 것 방지)
     */
    protected function handlePromoteCardConfirm(\Illuminate\Database\Eloquent\Model $item, Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer',
            'tier' => 'required|in:national,state_plus,sponsored',
            'days' => 'required|integer|min:1|max:90',
        ]);
        $user = auth()->user();
        $payment = \App\Models\Payment::where('id', (int) $request->payment_id)->where('user_id', $user->id)
            ->where('kind', \App\Models\Payment::KIND_PROMOTION)->where('ref_type', get_class($item))->where('ref_id', $item->getKey())->first();
        if (!$payment) return response()->json(['success' => false, 'message' => '결제 기록을 찾지 못했어요. 처음부터 다시 신청해 주세요.'], 404);
        if ($payment->status === 'captured') {
            return response()->json(['success' => true, 'message' => '이미 결제되고 적용된 상위노출이에요.', 'data' => ['tier' => $item->promotion_tier, 'expires_at' => $item->promotion_expires_at]]);
        }

        $resource = $this->promoResourceName();
        $tier = $request->tier;
        $days = (int) $request->days;
        $expected = \App\Support\AdPayments::cents(PromotionSettings::pricePerDay($tier, $resource) * $days);
        $dp = app(\App\Support\DirectPayments::class);

        if ((int) round($payment->amount * 100) !== $expected) {
            $dp->release($payment);
            return response()->json(['success' => false, 'message' => '가격이 바뀌어 결제를 취소했어요(청구 없음). 다시 신청해 주세요.'], 409);
        }
        try {
            if ($payment->status === 'pending') $dp->markAuthorized($payment);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // 카드 확인하는 사이에 다른 사람이 자리를 가져갔으면 청구하지 않고 보류만 푼다
        if ($deny = $this->promotionActiveError($item)) { $dp->release($payment); return $deny; }
        [$deny, $autoStates] = $this->promotionSlotCheck($item, $tier, $resource);
        if ($deny) { $dp->release($payment); return $deny; }

        try {
            $dp->capture($payment);
        } catch (\Throwable $e) {
            \Log::warning('상위노출 카드 청구 실패: ' . $e->getMessage());
            $dp->release($payment);
            return response()->json(['success' => false, 'message' => '카드 청구에 실패했어요. 카드에는 청구되지 않았어요. 다시 시도해 주세요.'], 422);
        }

        return $this->activatePromotion($item, $tier, $days, $autoStates, 'card');
    }

    private function promotionLabel(string $resource, string $tier, int $days): string
    {
        $names = ['jobs' => '구인구직', 'market' => '중고장터', 'realestate' => '부동산', 'business' => '업소록', 'businesses' => '업소록', 'clubs' => '동호회'];
        $tiers = ['national' => '전국', 'state_plus' => '주(State)', 'sponsored' => '스폰서'];
        return ($names[$resource] ?? $resource) . ' 상위노출 · ' . ($tiers[$tier] ?? $tier) . ' · ' . $days . '일';
    }

    // 이미 활성 중이면 차단 (만료 후 재신청)
    private function promotionActiveError(\Illuminate\Database\Eloquent\Model $item)
    {
        if ($item->promotion_tier && $item->promotion_tier !== 'none'
            && $item->promotion_expires_at && $item->promotion_expires_at->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => '이미 상위노출이 활성 중입니다 (' . $item->promotion_tier . '). '
                    . $item->promotion_expires_at->format('Y-m-d H:i') . ' 이후 다시 신청할 수 있습니다.',
                'data' => [
                    'already_active' => true,
                    'current_tier' => $item->promotion_tier,
                    'expires_at' => $item->promotion_expires_at,
                ],
            ], 422);
        }
        return null;
    }

    /** 슬롯·주 정보 확인. 반환 [오류 응답|null, state_plus 자동 확장 주 목록|null] */
    private function promotionSlotCheck(\Illuminate\Database\Eloquent\Model $item, string $tier, string $resource): array
    {
        $catCol = $this->promoCategoryColumn();
        $model = $this->promoModelClass();

        // state_plus 자동 주 확장
        $autoStates = null;
        if ($tier === 'state_plus') {
            if (!$item->state) {
                return [response()->json([
                    'success' => false,
                    'message' => '주(State) 정보가 없어 state_plus 상위노출을 적용할 수 없습니다. 근무/매물/업소 위치의 State 를 먼저 입력해주세요.'
                ], 422), null];
            }
            $autoStates = StateNeighbors::neighbors($item->state);
        }

        // 슬롯 체크 (카테고리별)
        if ($tier !== 'sponsored') {
            if (!$item->{$catCol}) {
                return [response()->json(['success' => false, 'message' => '카테고리/유형 정보가 필요합니다'], 422), null];
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
                return [response()->json([
                    'success' => false,
                    'message' => "{$tierLabel} - '{$item->{$catCol}}' 카테고리 슬롯이 모두 사용 중입니다."
                        . ($when ? " {$when} 이후 가능합니다." : ''),
                    'data' => [
                        'is_full' => true,
                        'next_slot_time' => $nextSlot?->promotion_expires_at,
                        'used' => $used,
                        'max_slots' => $max,
                    ],
                ], 422), null];
            }
        }
        return [null, $autoStates];
    }

    private function activatePromotion(\Illuminate\Database\Eloquent\Model $item, string $tier, int $days, ?array $autoStates, string $paidWith)
    {
        $expiresAt = now()->addDays($days);
        $item->update([
            'promotion_tier' => $tier,
            'promotion_expires_at' => $expiresAt,
            'promotion_states' => $autoStates,
        ]);

        return response()->json([
            'success' => true,
            'message' => '상위노출이 적용되었습니다'
                . ($autoStates ? ' (노출 주: ' . implode(', ', $autoStates) . ')' : '')
                . ($paidWith === 'card' ? ' · 카드 결제 완료(환불 불가), 인보이스는 결제 내역에서 볼 수 있어요' : ''),
            'data' => ['tier' => $tier, 'states' => $autoStates, 'expires_at' => $expiresAt, 'paid_with' => $paidWith],
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
