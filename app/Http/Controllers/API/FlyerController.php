<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FlyerAd;
use App\Models\FlyerSlot;
use App\Models\Payment;
use App\Support\AdCredit;
use App\Support\DirectPayments;
use App\Support\FlyerSchedule;
use App\Support\FlyerService;
use App\Support\StripeGateway;
use App\Traits\CompressesUploads;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * NEW 전단 광고 — 지역별 게시판 + 라디오 광고처럼 시간대를 골라 사는 상단 노출.
 */
class FlyerController extends Controller
{
    use CompressesUploads;

    private const KINDS = ['open', 'closing', 'sale', 'etc'];

    /** 공개용 필드만 (연락처/링크는 전단의 일부라 공개) */
    private function present(FlyerAd $ad): array
    {
        return [
            'id' => $ad->id,
            'title' => $ad->title,
            'kind' => $ad->kind,
            'description' => $ad->description,
            'phone' => $ad->phone,
            'link_url' => $ad->link_url,
            'image_url' => $ad->image_url,
            'scope' => $ad->scope,
            'region_key' => $ad->region_key,
            'extra_states' => $ad->extra_states ?: [],
        ];
    }

    private function resolveRegion(Request $request): array
    {
        $scope = $request->input('scope') === 'state' ? 'state' : 'national';
        $state = strtoupper((string) $request->input('state'));
        if ($scope === 'state' && !FlyerSchedule::isState($state)) $scope = 'national';
        return [$scope, $scope === 'state' ? $state : null, FlyerSchedule::regionKey($scope, $state)];
    }

    /** 요청의 추가 주 목록 → 올바른 주 코드만, 내 주·중복 제외, 최대 개수까지. 전국이면 비어 있음 */
    private function resolveExtras(Request $request, string $scope, ?string $state): array
    {
        if ($scope !== 'state') return [];
        $raw = $request->input('extra_states', []);
        if (is_string($raw)) $raw = explode(',', $raw);
        $out = [];
        foreach ((array) $raw as $c) {
            $c = strtoupper(trim((string) $c));
            if ($c !== $state && FlyerSchedule::isState($c) && !in_array($c, $out, true)) $out[] = $c;
        }
        return $out;
    }

    /**
     * GET /api/flyers?scope=national|state&state=GA
     * featured = 지금 이 시간(그 지역 현지 시각)에 예약된 전단. 내 지역에 없으면 전국 전단으로 대체.
     */
    public function index(Request $request)
    {
        [$scope, $state, $region] = $this->resolveRegion($request);
        $keys = $scope === 'state' ? [$region, FlyerSchedule::NATIONAL] : [FlyerSchedule::NATIONAL];

        $featured = null;
        foreach ($keys as $key) {
            $now = FlyerSchedule::now($key);
            $slot = FlyerSlot::where('region_key', $key)
                ->where('slot_date', $now->toDateString())
                ->where('slot_hour', $now->hour)
                ->whereHas('flyer', fn($q) => $q->where('status', 'approved'))
                ->with('flyer')->first();
            if ($slot) {
                $featured = $this->present($slot->flyer) + [
                    'live_hour' => $now->hour,
                    'source' => $key === FlyerSchedule::NATIONAL ? 'national' : 'state',
                ];
                break;
            }
        }

        return response()->json(['success' => true, 'data' => [
            'scope' => $scope,
            'state' => $state,
            'tz' => FlyerSchedule::timezone($region),
            'featured' => $featured,
        ]]);
    }

    /** GET /api/flyers/{id} — 승인된 전단은 누구나, 그 외는 작성자/관리자만 */
    public function show($id)
    {
        $ad = FlyerAd::findOrFail($id);
        $viewer = auth('api')->user();
        $isOwner = $viewer && $viewer->id === $ad->user_id;
        $isAdmin = $viewer && in_array($viewer->role, ['admin', 'super_admin', 'moderator'], true);

        if ($ad->status !== 'approved' && !$isOwner && !$isAdmin) {
            return response()->json(['success' => false, 'message' => '존재하지 않는 전단입니다.'], 404);
        }

        $now = FlyerSchedule::now($ad->region_key);
        $schedule = [];
        $live = false;
        foreach (FlyerSlot::where('flyer_ad_id', $ad->id)->where('region_key', $ad->region_key)->orderBy('slot_date')->orderBy('slot_hour')->get() as $s) {
            $d = $s->slot_date->toDateString();
            $schedule[$d][] = $s->slot_hour;
            if ($d === $now->toDateString() && $s->slot_hour === $now->hour && $ad->status === 'approved') $live = true;
        }

        $out = $this->present($ad) + [
            'tz' => FlyerSchedule::timezone($ad->region_key),
            'live' => $live,
            'schedule' => collect($schedule)->map(fn($hours, $date) => ['date' => $date, 'hours' => $hours])->values(),
        ];
        if ($isOwner || $isAdmin) {
            $out += [
                'status' => $ad->status, 'reject_reason' => $ad->reject_reason, 'total_price' => $ad->total_price,
                'view_count' => $ad->view_count, 'click_count' => $ad->click_count,
            ];
        }
        return response()->json(['success' => true, 'data' => $out]);
    }

    /** POST /api/flyers/{id}/view , /click — 승인된 전단만 집계 */
    public function view($id)
    {
        FlyerAd::where('id', $id)->where('status', 'approved')->increment('view_count');
        return response()->json(['success' => true]);
    }

    public function click($id)
    {
        FlyerAd::where('id', $id)->where('status', 'approved')->increment('click_count');
        return response()->json(['success' => true]);
    }

    /**
     * GET /api/flyers/availability?scope&state&start_date&days
     * 신청 화면용 — 시간별 가격표 + 선택한 날짜 범위에서 이미 예약된 시간.
     */
    public function availability(Request $request)
    {
        FlyerService::purgeStale();
        [$scope, $state, $region] = $this->resolveRegion($request);
        $now = FlyerSchedule::now($region);
        $days = max(1, min(FlyerSchedule::maxDays(), (int) $request->input('days', 7)));

        // 시작일은 내일부터 — 승인/준비 시간이 필요하고, 그래서 첫 신청자는 모든 시간대를 고를 수 있음
        $minStart = $now->copy()->startOfDay()->addDay();
        try {
            $start = Carbon::parse($request->input('start_date', $minStart->toDateString()), $now->timezone)->startOfDay();
        } catch (\Throwable $e) {
            $start = $minStart->copy();
        }
        if ($start->lt($minStart)) $start = $minStart->copy();

        $dates = [];
        for ($i = 0; $i < $days; $i++) $dates[] = $start->copy()->addDays($i)->toDateString();

        $extras = array_slice($this->resolveExtras($request, $scope, $state), 0, FlyerSchedule::maxExtraStates());
        $regions = array_merge([$region], $extras);

        // 주마다 이미 예약된 시간 — $booked 는 내 주 기준(기존 화면 호환), 칸의 "예약됨"은 고른 모든 주를 본다
        $bookedBy = [];   // region => hour => [dates]
        foreach (FlyerSlot::whereIn('region_key', $regions)->whereIn('slot_date', $dates)->get(['region_key', 'slot_date', 'slot_hour']) as $s) {
            $bookedBy[$s->region_key][$s->slot_hour][] = $s->slot_date->toDateString();
        }
        $booked = $bookedBy[$region] ?? [];

        // 블록(몇 시간씩 묶은 칸) — 칸 안의 어느 시간이든 선택한 날짜 중 하루라도 (고른 주 중 어느 한 곳에서든) 예약돼 있으면 그 칸은 "예약됨"
        $prices = FlyerSchedule::priceTable($region);
        $natPrices = FlyerSchedule::priceTable(FlyerSchedule::NATIONAL);
        // 할인 전 정가(하루, 센트) — 신청 화면이 "정가 → 오픈 이벤트 할인 → 광고 금액" 계산 내역을 보여 주기 위함
        $listDay = FlyerSchedule::dayPrices($region);
        $listExtraDay = array_map(fn($x) => FlyerSchedule::dayPrices($x, FlyerSchedule::extraStatePct() / 100), $extras);
        $blocks = array_map(function ($b) use ($prices, $natPrices, $regions, $extras, $bookedBy, $listDay, $listExtraDay) {
            $blockedIn = [];
            foreach ($regions as $r) {
                if (array_filter($b['hours'], fn($h) => !empty($bookedBy[$r][$h]))) $blockedIn[] = $r;
            }
            return [
                'start' => $b['start'], 'end' => $b['end'], 'hours' => $b['hours'],
                'price' => array_sum(array_map(fn($h) => $prices[$h], $b['hours'])),   // 하루 기준(센트) — 내 주
                'extra_price' => array_sum(array_map(fn($h) => array_sum(array_map(fn($x) => FlyerSchedule::extraHourPrice($x, $h), $extras)), $b['hours'])),   // 추가 주 합계(하루)
                'list_price' => array_sum(array_map(fn($h) => $listDay[$h], $b['hours'])),   // 정가(할인 전, 하루) — 내 주
                'list_extra_price' => array_sum(array_map(fn($h) => array_sum(array_map(fn($d) => $d[$h], $listExtraDay)), $b['hours'])),   // 정가(할인 전, 하루) — 추가 주 합계
                'national_price' => array_sum(array_map(fn($h) => $natPrices[$h], $b['hours'])),   // 전국이었다면(하루)
                'booked' => (bool) $blockedIn,
                'booked_states' => $blockedIn,
            ];
        }, FlyerSchedule::blocks());

        return response()->json(['success' => true, 'data' => [
            'scope' => $scope,
            'state' => $state,
            'region_key' => $region,
            'blocks' => $blocks,
            'peak' => FlyerSchedule::peakRange(),
            'night_end' => FlyerSchedule::nightEnd(),
            'tz' => FlyerSchedule::timezone($region),
            'now' => ['date' => $now->toDateString(), 'hour' => $now->hour],
            'min_date' => $minStart->toDateString(),
            'max_date' => $now->copy()->startOfDay()->addDays(FlyerSchedule::windowDays())->toDateString(),
            'dates' => $dates,
            'prices' => FlyerSchedule::priceTable($region),
            'booked' => (object) $booked,
            'max_days' => FlyerSchedule::maxDays(),
            'window_days' => FlyerSchedule::windowDays(),
            'min_order_cents' => $this->cardMinCents(),
            // 추가 주 — 고른 주(검증된 것), 주변 주(빠른 선택), 최대 개수, 추가 주 가격 비율(%)
            'extra_states' => $extras,
            'neighbor_states' => $scope === 'state' ? FlyerSchedule::neighborStates($state) : [],
            'credit_cents' => ($u = auth('api')->user()) ? AdCredit::balance($u->id) : 0,
            // 주별 한 달(24시간) 정가 — 신청 화면에서 주를 고를 때 가격을 보여 주기 위함 (할인 전, 센트)
            'state_month_cents' => collect(FlyerSchedule::states())->mapWithKeys(fn($st) => [$st => FlyerSchedule::stateMonthCents($st)])->all(),
            'state_tiers' => collect(FlyerSchedule::states())->mapWithKeys(fn($st) => [$st => FlyerSchedule::tierOf($st)])->all(),
            'max_extra_states' => FlyerSchedule::maxExtraStates(),
            'extra_pct' => FlyerSchedule::extraStatePct(),
            // 오픈 기념 이벤트의 전단 광고(달러 결제) 할인율(%) — 진행 중이 아니면 0
            'event_discount_pct' => \App\Support\OpenEvent::discountPct('flyer_usd'),
        ]]);
    }

    /** 카드 결제 최소 금액(센트) — 관리자 결제 설정의 "최소 결제금액"(기본 $5). 오픈 이벤트 할인으로 합계가 작아져도 이 기준은 그대로 */
    private function cardMinCents(): int
    {
        return \App\Support\CardLimits::minCents();
    }

    private function usd(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    /** GET /api/flyers/my */
    public function my(Request $request)
    {
        $ads = FlyerAd::where('user_id', $request->user()->id)->where('status', '!=', 'awaiting_payment')->orderByDesc('id')->limit(50)->get();
        return response()->json(['success' => true, 'data' => $ads]);
    }

    /**
     * POST /api/flyers (multipart) — 전단 + 시간대 예약 + 포인트 결제를 한 번에.
     *   hours[] = 매일 방송할 시간(0~23, 현지), start_date + days = 며칠 동안
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:80',
            'kind' => 'required|in:' . implode(',', self::KINDS),
            'description' => 'nullable|string|max:400',
            'phone' => 'nullable|string|max:30',
            'link_url' => 'nullable|url:http,https|max:300',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:6144',
            'scope' => 'required|in:national,state',
            'state' => 'required_if:scope,state|nullable|string|size:2',
            'start_date' => 'required|date_format:Y-m-d',
            'days' => 'required|integer|min:1',
            'hours' => 'required|array|min:1|max:24',
            'hours.*' => 'integer|min:0|max:23',
            'extra_states' => 'nullable|array|max:10',
            'extra_states.*' => 'string|size:2',
        ], [
            'image.required' => '전단 이미지를 올려주세요.',
            'image.max' => '이미지는 6MB 이하로 올려주세요.',
            'hours.required' => '방송할 시간을 하나 이상 골라주세요.',
        ]);

        $scope = $data['scope'];
        if ($scope === 'national') {
            return response()->json(['success' => false, 'message' => '전국 광고는 더 이상 판매하지 않아요. 노출할 지역(주)을 골라 주세요.'], 422);
        }
        $state = $scope === 'state' ? strtoupper($data['state']) : null;
        if ($scope === 'state' && !FlyerSchedule::isState($state)) {
            return response()->json(['success' => false, 'message' => '올바른 주(State)를 선택해주세요.'], 422);
        }
        $region = FlyerSchedule::regionKey($scope, $state);
        $now = FlyerSchedule::now($region);
        $today = $now->copy()->startOfDay();

        $days = (int) $data['days'];
        if ($days > FlyerSchedule::maxDays()) {
            return response()->json(['success' => false, 'message' => '한 번에 최대 ' . FlyerSchedule::maxDays() . '일까지 신청할 수 있어요.'], 422);
        }
        $start = Carbon::createFromFormat('Y-m-d', $data['start_date'], $now->timezone)->startOfDay();
        if ($start->lte($today)) {
            return response()->json(['success' => false, 'message' => '시작일은 내일부터 선택할 수 있어요.'], 422);
        }
        if ($start->gt($today->copy()->addDays(FlyerSchedule::windowDays()))) {
            return response()->json(['success' => false, 'message' => '시작일은 오늘부터 ' . FlyerSchedule::windowDays() . '일 이내로 선택해주세요.'], 422);
        }

        $hours = array_values(array_unique(array_map('intval', $data['hours'])));
        sort($hours);
        if (!FlyerSchedule::isWholeBlocks($hours)) {
            return response()->json(['success' => false, 'message' => '시간은 칸 단위(예: 오전 11시~오후 2시)로 선택해주세요.'], 422);
        }

        // 추가 주: 내 주와 같은 시간 칸을 그 주에서도 함께 산다 (시간은 각 주의 현지 시각). 가격은 그 주 가격의 extraStatePct %
        $extras = $this->resolveExtras($request, $scope, $state);
        if (count($extras) > FlyerSchedule::maxExtraStates()) {
            return response()->json(['success' => false, 'message' => '추가할 수 있는 주는 최대 ' . FlyerSchedule::maxExtraStates() . '개예요.'], 422);
        }
        if ($request->filled('extra_states') && $scope !== 'state') {
            return response()->json(['success' => false, 'message' => '전국 광고에는 주를 추가할 수 없어요.'], 422);
        }

        $rows = [];
        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            foreach ($hours as $h) {
                $price = FlyerSchedule::hourPrice($region, $h);
                $total += $price;
                $rows[] = ['region_key' => $region, 'slot_date' => $d->toDateString(), 'slot_hour' => $h, 'price' => $price];
                foreach ($extras as $x) {
                    $xp = FlyerSchedule::extraHourPrice($x, $h);
                    $total += $xp;
                    $rows[] = ['region_key' => $x, 'slot_date' => $d->toDateString(), 'slot_hour' => $h, 'price' => $xp];
                }
            }
        }
        $homeCount = $days * count($hours);

        $user = $request->user();

        // ── 결제 금액 ──
        //  1) 광고 잔액(use_credit, 기본 사용)으로 먼저 낸다.
        //  2) 남은 금액(cardNeed)은 카드로. 카드는 최소 결제 금액($5) 미만으로는 받지 않으므로, 모자라면 먼저 "최소 금액으로 결제하시겠어요?"를 묻고
        //     동의(pay_min)하면 최소 금액을 결제한다. 사용자가 더 많이 충전하려면 extra_topup_cents 로 더한다.
        //  3) 광고 금액보다 더 결제한 금액(topup)은 환불되지 않고, 승인되어 청구되면 광고 잔액으로 쌓여 다음 신청에 쓸 수 있다.
        $creditUse = $request->boolean('use_credit', true) ? min(AdCredit::balance($user->id), $total) : 0;
        $cardNeed = $total - $creditUse;
        $min = $this->cardMinCents();
        $charge = 0;   // 카드로 결제할 금액 (0 = 광고 잔액만으로 충분)
        $topup = 0;
        if ($cardNeed > 0) {
            $base = max($cardNeed, $min);
            if ($base > $cardNeed && !$request->boolean('pay_min')) {
                return response()->json([
                    'success' => false, 'code' => 'below_min', 'min_cents' => $min, 'total_cents' => $cardNeed,
                    'message' => '카드 결제는 최소 ' . $this->usd($min) . ' 이상부터 가능해요 (지금 ' . $this->usd($cardNeed) . '). ' . $this->usd($min) . '으로 결제하시거나, 시간·일수·지역(주)을 늘려 주세요.',
                ], 422);
            }
            $extraTopup = max(0, min(100000, (int) $request->input('extra_topup_cents', 0)));
            $charge = $base + $extraTopup;
            $topup = $charge - $cardNeed;
            $gateway = app(StripeGateway::class);
            if (!$gateway->configured()) {
                return response()->json(['success' => false, 'message' => '카드 결제 설정이 아직 준비되지 않았어요. 관리자에게 문의해주세요.'], 503);
            }
        }

        // 카드 입력만 하다 떠난 신청이 잡고 있던 시간 정리 → 사전 점검 (최종 보루는 DB UNIQUE)
        FlyerService::purgeStale();
        $taken = FlyerSlot::whereIn('region_key', array_merge([$region], $extras))
            ->whereIn('slot_date', array_unique(array_column($rows, 'slot_date')))
            ->whereIn('slot_hour', $hours)->get(['region_key', 'slot_date', 'slot_hour']);
        if ($taken->isNotEmpty()) {
            $where = $taken->pluck('region_key')->unique()->values()->all();
            return response()->json([
                'success' => false,
                'message' => '이미 다른 광고가 예약한 시간이 있어요' . ($extras ? ' (' . implode(', ', $where) . ')' : '') . '. 다른 시간이나 주를 골라주세요.',
                'conflicts' => $taken->map(fn($s) => ['state' => $s->region_key, 'date' => $s->slot_date->toDateString(), 'hour' => $s->slot_hour])->values(),
            ], 409);
        }

        $imageUrl = $this->storeCompressedImage($request->file('image'), 'flyers', 1600, 85);
        $cleanupImage = fn() => Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $imageUrl), '/'));

        // 1) 전단 + 시간 슬롯을 먼저 "결제 대기(awaiting_payment)"로 잡는다 (카드 입력하는 동안 다른 광고주가 못 가져가게)
        try {
            $ad = DB::transaction(function () use ($user, $data, $scope, $region, $extras, $start, $days, $rows, $total, $imageUrl, $homeCount, $charge, $topup, $creditUse) {
                $ad = FlyerAd::create([
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'kind' => $data['kind'],
                    'description' => $data['description'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'link_url' => $data['link_url'] ?? null,
                    'image_url' => $imageUrl,
                    'scope' => $scope,
                    'region_key' => $region,
                    'extra_states' => $extras ?: null,
                    'status' => $charge > 0 ? 'awaiting_payment' : 'pending',   // 카드를 안 쓰면(잔액만) 바로 승인 대기
                    'total_price' => $total,            // 카드·잔액 건은 센트 (광고 자체 금액)
                    'payment_method' => $charge > 0 ? 'card' : 'credit',
                    'topup_cents' => $topup,
                    'credit_used_cents' => $creditUse,
                    'hours_count' => $homeCount,        // 시간 수는 내 주 기준 (추가 주는 같은 시간을 함께 사는 것)
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
                ]);
                $stamp = now();
                FlyerSlot::insert(array_map(fn($r) => $r + [
                    'flyer_ad_id' => $ad->id, 'created_at' => $stamp, 'updated_at' => $stamp,
                ], $rows));
                // 광고 잔액 차감 — 그 사이 잔액이 줄었으면 전체를 되돌린다
                if ($creditUse > 0 && !AdCredit::spend($user->id, $creditUse, "전면광고 신청에 광고 잔액 사용: {$ad->title}", FlyerAd::class, $ad->id)) {
                    throw new \DomainException('credit_changed');
                }
                return $ad;
            });
        } catch (\DomainException $e) {
            $cleanupImage();
            return response()->json(['success' => false, 'message' => '광고 잔액이 바뀌었어요. 화면을 새로 고친 뒤 다시 신청해 주세요.'], 409);
        } catch (\Illuminate\Database\QueryException $e) {
            $cleanupImage();
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return response()->json(['success' => false, 'message' => '방금 다른 광고가 같은 시간을 예약했어요. 다시 확인해주세요.'], 409);
            }
            throw $e;
        }

        // 잔액만으로 충분한 신청: 카드 없이 바로 승인 대기로 접수
        if ($charge <= 0) {
            \App\Support\AdminNotifier::send(2, 'flyer_submitted', '새 NEW 전단 광고 신청이 도착했습니다', "'{$ad->title}' 전단이 승인을 기다리고 있어요.", '/admin/flyers?status=pending', ['flyer_id' => $ad->id]);
            return response()->json([
                'success' => true,
                'message' => '신청이 접수됐어요. 광고 잔액 ' . $this->usd($creditUse) . '를 사용했고, 관리자 승인 후 예약한 시간에 방송돼요. 승인 전에 취소하면 잔액이 돌아와요.',
                'data' => ['flyer_id' => $ad->id, 'credit_only' => true, 'credit_used_cents' => $creditUse, 'hours_count' => $ad->hours_count],
            ], 201);
        }

        // 2) 카드 보류용 결제 생성 (실제 청구는 관리자가 승인할 때)
        try {
            [$payment, $clientSecret] = app(DirectPayments::class)->begin(
                $user, Payment::KIND_FLYER, FlyerAd::class, $ad->id, $charge,
                "NEW 전면광고: {$ad->title} ({$ad->hours_count}시간)"
            );
            $ad->update(['payment_id' => $payment->id]);
        } catch (\Throwable $e) {
            \Log::error('전면광고 결제 생성 실패: ' . $e->getMessage());
            FlyerSlot::where('flyer_ad_id', $ad->id)->delete();
            if ($creditUse > 0) AdCredit::add($user->id, $creditUse, 'restore', "전면광고 결제를 시작하지 못해 광고 잔액 반환: {$ad->title}", FlyerAd::class, $ad->id);
            $ad->delete();
            $cleanupImage();
            // 카드 최소 결제 금액 미만이면 쉬운 안내 문구(422), 그 밖의 오류는 기존 안내(502)
            if ($e instanceof \DomainException) return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            return response()->json(['success' => false, 'message' => '결제를 시작하지 못했어요. 잠시 후 다시 시도해주세요.'], 502);
        }

        return response()->json([
            'success' => true,
            'message' => '카드를 확인해주세요. 지금은 청구되지 않고, 관리자가 승인하면 그때 청구돼요.',
            'data' => ['flyer_id' => $ad->id, 'client_secret' => $clientSecret, 'total_cents' => $charge, 'topup_cents' => $topup, 'credit_used_cents' => $creditUse, 'hours_count' => $ad->hours_count],
        ], 201);
    }

    /**
     * POST /api/flyers/{id}/confirm-payment — 카드 확인(보류)이 끝났음을 알림.
     * 클라이언트 말만 믿지 않고 Stripe 에서 실제 보류 상태를 조회해 검증한 뒤 승인 대기(pending)로 넘김.
     */
    public function confirmPayment(Request $request, $id)
    {
        $ad = FlyerAd::where('user_id', $request->user()->id)->findOrFail($id);
        if ($ad->status === 'pending') {
            return response()->json(['success' => true, 'message' => '이미 접수된 신청이에요.', 'data' => $ad]);
        }
        $payment = FlyerService::payment($ad);
        if ($ad->status !== 'awaiting_payment' || !$payment) {
            return response()->json(['success' => false, 'message' => '결제를 진행 중인 신청이 아니에요. 처음부터 다시 신청해주세요.'], 422);
        }
        try {
            app(DirectPayments::class)->markAuthorized($payment);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('전면광고 결제 확인 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '결제 확인 중 오류가 났어요. 잠시 후 다시 시도해주세요.'], 502);
        }
        $ad->update(['status' => 'pending']);
        // 관리자에게 승인 요청 알림 — 누르면 관리자 "NEW 전단 광고"의 승인 대기 목록이 열린다 (실패해도 신청은 정상 접수)
        \App\Support\AdminNotifier::send(2, 'flyer_submitted', '새 NEW 전단 광고 신청이 도착했습니다', "'{$ad->title}' 전단이 승인을 기다리고 있어요.", '/admin/flyers?status=pending', ['flyer_id' => $ad->id]);
        return response()->json([
            'success' => true,
            'message' => '신청이 접수됐어요. 관리자 승인 후 예약한 시간에 방송되고, 그때 카드에 ' . FlyerService::money($ad, (int) $ad->total_price) . '가 청구돼요.',
            'data' => $ad,
        ]);
    }

    /** POST /api/flyers/{id}/cancel — 승인 전(결제 대기/승인 대기)에만. 카드는 청구 없이 보류 해제, 포인트 건은 전액 환불 */
    public function cancel(Request $request, $id)
    {
        $ad = FlyerAd::where('user_id', $request->user()->id)->findOrFail($id);
        if (!in_array($ad->status, ['pending', 'awaiting_payment'], true)) {
            return response()->json(['success' => false, 'message' => '승인 대기 중인 신청만 취소할 수 있어요.'], 422);
        }
        $hadCredit = (int) $ad->credit_used_cents;   // release 가 되돌리고 0 으로 만들기 전 값
        $amount = FlyerService::release($ad, false, "NEW 전단 광고 신청 취소: {$ad->title}");
        $ad->update(['status' => 'cancelled']);
        $msg = FlyerService::isCard($ad)
            ? '취소되었어요. 카드에는 청구되지 않았습니다.' . ($hadCredit > 0 ? ' 쓰신 광고 잔액은 돌려드렸어요.' : '')
            : (FlyerService::isCredit($ad) ? '취소되었어요. 광고 잔액 ' . FlyerService::money($ad, $amount) . '가 돌아왔어요.' : '취소되었어요. ' . FlyerService::money($ad, $amount) . '가 환불되었습니다.');
        return response()->json(['success' => true, 'message' => $msg]);
    }
}
