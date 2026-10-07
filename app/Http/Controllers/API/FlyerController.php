<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FlyerAd;
use App\Models\FlyerSlot;
use App\Models\Payment;
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
        ];
    }

    private function resolveRegion(Request $request): array
    {
        $scope = $request->input('scope') === 'state' ? 'state' : 'national';
        $state = strtoupper((string) $request->input('state'));
        if ($scope === 'state' && !FlyerSchedule::isState($state)) $scope = 'national';
        return [$scope, $scope === 'state' ? $state : null, FlyerSchedule::regionKey($scope, $state)];
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
        foreach (FlyerSlot::where('flyer_ad_id', $ad->id)->orderBy('slot_date')->orderBy('slot_hour')->get() as $s) {
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

        $booked = [];
        foreach (FlyerSlot::where('region_key', $region)->whereIn('slot_date', $dates)->get(['slot_date', 'slot_hour']) as $s) {
            $booked[$s->slot_hour][] = $s->slot_date->toDateString();
        }

        // 블록(몇 시간씩 묶은 칸) — 칸 안의 어느 시간이든 선택한 날짜 중 하루라도 예약돼 있으면 그 칸은 "예약됨"
        $prices = FlyerSchedule::priceTable($region);
        $blocks = array_map(fn($b) => [
            'start' => $b['start'], 'end' => $b['end'], 'hours' => $b['hours'],
            'price' => array_sum(array_map(fn($h) => $prices[$h], $b['hours'])),   // 하루 기준(센트)
            'booked' => (bool) array_filter($b['hours'], fn($h) => !empty($booked[$h])),
        ], FlyerSchedule::blocks());

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
            'min_order_cents' => FlyerSchedule::minOrderCents(),
        ]]);
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
        ], [
            'image.required' => '전단 이미지를 올려주세요.',
            'image.max' => '이미지는 6MB 이하로 올려주세요.',
            'hours.required' => '방송할 시간을 하나 이상 골라주세요.',
        ]);

        $scope = $data['scope'];
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

        $rows = [];
        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            foreach ($hours as $h) {
                $price = FlyerSchedule::hourPrice($region, $h);
                $total += $price;
                $rows[] = ['region_key' => $region, 'slot_date' => $d->toDateString(), 'slot_hour' => $h, 'price' => $price];
            }
        }

        $user = $request->user();

        // 달러 카드 결제 — 최소 결제 금액 (카드 수수료 때문에 아주 작은 건은 받지 않음)
        $min = FlyerSchedule::minOrderCents();
        if ($total < $min) {
            return response()->json(['success' => false, 'message' => '신청 합계가 최소 결제 금액 $' . number_format($min / 100, 2) . ' 보다 작아요. 시간이나 기간을 늘려주세요.'], 422);
        }
        $gateway = app(StripeGateway::class);
        if (!$gateway->configured()) {
            return response()->json(['success' => false, 'message' => '카드 결제 설정이 아직 준비되지 않았어요. 관리자에게 문의해주세요.'], 503);
        }

        // 카드 입력만 하다 떠난 신청이 잡고 있던 시간 정리 → 사전 점검 (최종 보루는 DB UNIQUE)
        FlyerService::purgeStale();
        $taken = FlyerSlot::where('region_key', $region)
            ->whereIn('slot_date', array_unique(array_column($rows, 'slot_date')))
            ->whereIn('slot_hour', $hours)->get(['slot_date', 'slot_hour']);
        if ($taken->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '이미 다른 광고가 예약한 시간이 있어요. 다른 시간을 골라주세요.',
                'conflicts' => $taken->map(fn($s) => ['date' => $s->slot_date->toDateString(), 'hour' => $s->slot_hour])->values(),
            ], 409);
        }

        $imageUrl = $this->storeCompressedImage($request->file('image'), 'flyers', 1600, 85);
        $cleanupImage = fn() => Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $imageUrl), '/'));

        // 1) 전단 + 시간 슬롯을 먼저 "결제 대기(awaiting_payment)"로 잡는다 (카드 입력하는 동안 다른 광고주가 못 가져가게)
        try {
            $ad = DB::transaction(function () use ($user, $data, $scope, $region, $start, $days, $rows, $total, $imageUrl) {
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
                    'status' => 'awaiting_payment',
                    'total_price' => $total,            // 카드 결제 건은 센트
                    'payment_method' => 'card',
                    'hours_count' => count($rows),
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
                ]);
                $stamp = now();
                FlyerSlot::insert(array_map(fn($r) => $r + [
                    'flyer_ad_id' => $ad->id, 'created_at' => $stamp, 'updated_at' => $stamp,
                ], $rows));
                return $ad;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $cleanupImage();
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return response()->json(['success' => false, 'message' => '방금 다른 광고가 같은 시간을 예약했어요. 다시 확인해주세요.'], 409);
            }
            throw $e;
        }

        // 2) 카드 보류용 결제 생성 (실제 청구는 관리자가 승인할 때)
        try {
            [$payment, $clientSecret] = app(DirectPayments::class)->begin(
                $user, Payment::KIND_FLYER, FlyerAd::class, $ad->id, $total,
                "NEW 전면광고: {$ad->title} ({$ad->hours_count}시간)"
            );
            $ad->update(['payment_id' => $payment->id]);
        } catch (\Throwable $e) {
            \Log::error('전면광고 결제 생성 실패: ' . $e->getMessage());
            FlyerSlot::where('flyer_ad_id', $ad->id)->delete();
            $ad->delete();
            $cleanupImage();
            return response()->json(['success' => false, 'message' => '결제를 시작하지 못했어요. 잠시 후 다시 시도해주세요.'], 502);
        }

        return response()->json([
            'success' => true,
            'message' => '카드를 확인해주세요. 지금은 청구되지 않고, 관리자가 승인하면 그때 청구돼요.',
            'data' => ['flyer_id' => $ad->id, 'client_secret' => $clientSecret, 'total_cents' => $total, 'hours_count' => $ad->hours_count],
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
        $amount = FlyerService::release($ad, false, "NEW 전단 광고 신청 취소: {$ad->title}");
        $ad->update(['status' => 'cancelled']);
        $msg = FlyerService::isCard($ad)
            ? '취소되었어요. 카드에는 청구되지 않았습니다.'
            : '취소되었어요. ' . FlyerService::money($ad, $amount) . '가 환불되었습니다.';
        return response()->json(['success' => true, 'message' => $msg]);
    }
}
