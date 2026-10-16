<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyCoupon;
use App\Models\Report;
use App\Models\User;
use App\Support\AdminNotifier;
use App\Support\GroupBuyCouponUse;
use App\Support\GroupBuyCoupons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 공동구매 쿠폰.
 *   고객(쿠폰 주인): 목록·상세·사용 확인 응답·되돌리기·신고
 *   개설자:         쿠폰 목록·온라인 사용 요청·유효기간 연장·되돌리기
 *   가게(QR 확인):   개설자 로그인 또는 매장 PIN 으로 확인 → 사용 수락 → 고객 확인(양쪽 동의)
 * 돈은 어썸코리안이 받지 않는다 — 쿠폰은 가격을 보장하는 증표이고 쓸 때 개설자에게 직접 낸다.
 */
class GroupBuyCouponController extends Controller
{
    private const STAFF_ROLES = ['admin', 'super_admin', 'moderator'];

    /** 만료 시각이 지났는데 아직 unused/pending 이면 화면에는 expired 로 보인다(DB 는 점검 작업이 곧 정리) */
    public static function effectiveStatus(GroupBuyCoupon $c): string
    {
        if (in_array($c->status, ['unused', 'pending'], true) && $c->expires_at && $c->expires_at->isPast()) return 'expired';
        // 확인 요청의 응답 시간이 지났으면 다시 미사용으로 본다
        if ($c->status === 'pending' && $c->pending_expires_at && $c->pending_expires_at->isPast()) return 'unused';
        return $c->status;
    }

    private function fail(\DomainException $e, int $code = 422)
    {
        return response()->json(['success' => false, 'message' => $e->getMessage()], $code);
    }

    private function maskName(?User $u): string
    {
        $n = $u ? ($u->nickname ?: $u->name) : '고객';
        $n = (string) $n;
        return mb_strlen($n) <= 1 ? $n : mb_substr($n, 0, 1) . str_repeat('*', max(1, min(3, mb_strlen($n) - 1)));
    }

    // ─── 고객 (쿠폰 주인) ────────────────────────────────────────────

    public function index()
    {
        $rows = GroupBuyCoupon::where('user_id', auth()->id())
            ->with(['groupBuy:id,title,images,use_mode,use_place,user_id,coupon_extended_at', 'groupBuy.user:id,name,nickname'])
            ->orderByRaw("FIELD(status, 'pending', 'unused', 'used', 'expired', 'cancelled')")->orderBy('expires_at')->orderBy('id')
            ->limit(200)->get();

        $data = $rows->map(fn (GroupBuyCoupon $c) => [
            'id' => $c->id,
            'status' => self::effectiveStatus($c),
            'display_code' => $c->display_code,
            'seq' => (int) $c->seq,
            'expires_at' => $c->expires_at,
            'used_at' => $c->used_at,
            'title' => $c->groupBuy->title ?? '(삭제된 공동구매)',
            'image' => ($c->groupBuy->images[0] ?? null),
            'use_mode' => $c->groupBuy->use_mode ?? null,
            'use_place' => $c->groupBuy->use_place ?? null,
            'opener' => $c->groupBuy && $c->groupBuy->user ? ($c->groupBuy->user->nickname ?: $c->groupBuy->user->name) : null,
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show($id)
    {
        $c = GroupBuyCoupon::where('user_id', auth()->id())->with(['groupBuy', 'groupBuy.user:id,name,nickname'])->findOrFail($id);
        $c = GroupBuyCouponUse::refreshState($c);
        $gb = $c->groupBuy;
        $status = self::effectiveStatus($c);

        $pending = null;
        if ($status === 'pending' && $c->pending_expires_at) {
            $pending = [
                'mode' => $c->pending_mode, 'place' => $c->pending_mode === 'online' ? null : $gb->use_place,
                'expires_at' => $c->pending_expires_at, 'seconds_left' => max(0, (int) now()->diffInSeconds($c->pending_expires_at, false)),
            ];
        }
        $undo = null;
        if ($status === 'used') {
            $left = GroupBuyCouponUse::undoWindowLeft($c);
            $undo = ['requested_by' => $c->undo_requested_by, 'can_request' => $left > 0 && !$c->undo_requested_by, 'minutes_left' => $left];
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $c->id,
            'status' => $status,
            'code' => $c->code,
            'display_code' => $c->display_code,
            'confirm_code' => $c->confirm_code,                 // 고객 확인 코드 4자리 — 쿠폰 주인에게만
            'qr_url' => url('/groupbuy/coupon/' . $c->code),    // 가게가 일반 카메라로 찍으면 열리는 주소
            'seq' => (int) $c->seq,
            'expires_at' => $c->expires_at,
            'used_at' => $c->used_at,
            'used_place' => $c->used_mode === 'online' ? '온라인' : $gb->use_place,
            'extended' => (bool) $gb->coupon_extended_at,
            'group_buy' => [
                'id' => $gb->id, 'title' => $gb->title, 'image' => $gb->images[0] ?? null, 'price' => $gb->current_price,
                'opener' => $gb->user ? ($gb->user->nickname ?: $gb->user->name) : null,
                'use_mode' => $gb->use_mode, 'use_place' => $gb->use_place, 'use_url' => $gb->use_url, 'use_instructions' => $gb->use_instructions,
                'promo_code' => $gb->promo_code,                // 개설자 할인 코드 — 쿠폰을 가진 사람에게만 보임
            ],
            'pending' => $pending,
            'undo' => $undo,
        ]]);
    }

    /** POST /api/coupons/{id}/respond — 가게·개설자의 사용 확인 요청에 "맞아요 / 아니에요" */
    public function respond(Request $request, $id)
    {
        $request->validate(['accept' => 'required|boolean', 'reason' => 'nullable|string|max:200']);
        $c = GroupBuyCoupon::where('user_id', auth()->id())->with('groupBuy.user')->findOrFail($id);
        try {
            GroupBuyCouponUse::respond($c, $request->boolean('accept'), $request->input('reason'));
        } catch (\DomainException $e) {
            return $this->fail($e);
        }
        return response()->json(['success' => true, 'message' => $request->boolean('accept') ? '사용이 확인됐어요.' : '사용을 거절했어요. 쿠폰은 그대로 남아 있어요.']);
    }

    /** POST /api/coupons/{id}/undo {action: request|accept|decline} — 쿠폰 주인 쪽 되돌리기 */
    public function undo(Request $request, $id)
    {
        $request->validate(['action' => 'required|in:request,accept,decline']);
        $c = GroupBuyCoupon::where('user_id', auth()->id())->with('groupBuy')->findOrFail($id);
        try {
            $msg = GroupBuyCouponUse::undo($c, 'holder', $request->action);
        } catch (\DomainException $e) {
            return $this->fail($e);
        }
        return response()->json(['success' => true, 'message' => $msg]);
    }

    /** POST /api/coupons/{id}/report — 허위 사용 처리 / 약속을 안 지킴 / 도용 의심 신고. 관리자 신고 화면에 접수된다 */
    public function report(Request $request, $id)
    {
        $request->validate(['type' => 'required|in:fake_use,not_fulfilled,code_theft,other', 'detail' => 'nullable|string|max:500']);
        $c = GroupBuyCoupon::where('user_id', auth()->id())->with('groupBuy')->findOrFail($id);
        $gb = $c->groupBuy;
        $labels = ['fake_use' => '쿠폰 허위 사용 처리', 'not_fulfilled' => '개설자 미이행', 'code_theft' => '쿠폰 도용 의심', 'other' => '쿠폰 관련 기타'];
        return $this->createReport($c, $gb, GroupBuy::class, $gb->id, $labels[$request->type], $request->input('detail'), 'holder');
    }

    // ─── 개설자 ──────────────────────────────────────────────────────

    private function ownedGroupBuy($id): GroupBuy
    {
        return GroupBuy::where('user_id', auth()->id())->findOrFail($id);
    }

    /** GET /api/groupbuys/{id}/coupons — 내 공동구매의 쿠폰 전체(참가자 이름 포함) */
    public function ownerIndex($id)
    {
        $gb = $this->ownedGroupBuy($id);
        $list = GroupBuyCoupon::where('group_buy_id', $gb->id)->with('holder:id,name,nickname')->orderBy('id')->limit(500)->get();
        $data = $list->map(function (GroupBuyCoupon $c) {
            $c = GroupBuyCouponUse::refreshState($c);
            $left = GroupBuyCouponUse::undoWindowLeft($c);
            return [
                'id' => $c->id, 'display_code' => $c->display_code, 'status' => self::effectiveStatus($c),
                'holder' => $c->holder ? ($c->holder->nickname ?: $c->holder->name) : '-',
                'expires_at' => $c->expires_at, 'used_at' => $c->used_at,
                'undo' => $c->status === 'used' ? ['requested_by' => $c->undo_requested_by, 'can_request' => $left > 0 && !$c->undo_requested_by, 'minutes_left' => $left] : null,
            ];
        })->values();
        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /api/groupbuys/{id}/coupons/use {code, mode:online} — 개설자(로그인)가 쿠폰 번호로 사용 요청 */
    public function ownerUse(Request $request, $id)
    {
        $request->validate(['code' => 'required|string|max:20', 'mode' => 'nullable|in:store,online']);
        $gb = $this->ownedGroupBuy($id)->load('user');
        $code = GroupBuyCoupons::normalizeCode($request->code);
        $c = GroupBuyCoupon::where('group_buy_id', $gb->id)->where('code', $code)->first();
        if (!$c) return response()->json(['success' => false, 'message' => '이 공동구매의 쿠폰 번호가 아니에요. 번호를 다시 확인해 주세요.'], 404);
        try {
            GroupBuyCouponUse::requestUse($c, $gb, 'login', $request->input('mode', 'online'), auth()->id());
        } catch (\DomainException $e) {
            return $this->fail($e);
        }
        $mode = $request->input('mode', 'online');
        return response()->json(['success' => true, 'message' => '손님에게 확인을 요청했어요. ' . ($mode === 'online' ? '24시간 안에' : '3분 안에') . ' 손님이 "맞아요"를 누르면 사용 완료돼요.']);
    }

    /** POST /api/groupbuys/{id}/coupons/extend {days} — 유효기간 연장(한 번만) */
    public function ownerExtend(Request $request, $id)
    {
        $request->validate(['days' => 'required|integer|min:1|max:' . GroupBuyCouponUse::MAX_EXTEND_DAYS]);
        $gb = $this->ownedGroupBuy($id);
        try {
            $n = GroupBuyCouponUse::extend($gb, (int) $request->days);
        } catch (\DomainException $e) {
            return $this->fail($e);
        }
        return response()->json(['success' => true, 'message' => "쿠폰 {$n}장의 유효기간을 {$request->days}일 연장했어요."]);
    }

    /** POST /api/groupbuys/coupons/{couponId}/undo — 개설자 쪽 되돌리기 */
    public function ownerUndo(Request $request, $couponId)
    {
        $request->validate(['action' => 'required|in:request,accept,decline']);
        $c = GroupBuyCoupon::with('groupBuy')->findOrFail($couponId);
        if (!$c->groupBuy || (int) $c->groupBuy->user_id !== (int) auth()->id()) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }
        try {
            $msg = GroupBuyCouponUse::undo($c, 'opener', $request->action);
        } catch (\DomainException $e) {
            return $this->fail($e);
        }
        return response()->json(['success' => true, 'message' => $msg]);
    }

    // ─── 가게 쪽 확인 (QR 을 일반 카메라로 찍으면 열리는 페이지) ───────────

    /**
     * 가게 접근 확인: 개설자 로그인 또는 매장 PIN. PIN 은 틀리면 시도 제한(5번 틀리면 15분 잠금)이 걸린다.
     * @return array{0:string,1:?int}|\Illuminate\Http\JsonResponse [via, actorId]
     */
    private function storeAccess(Request $request, GroupBuyCoupon $c)
    {
        $gb = $c->groupBuy;
        $user = auth('api')->user();
        if ($user && (int) $user->id === (int) $gb->user_id) return ['login', (int) $user->id];

        $key = GroupBuyCouponUse::pinLimitKey((int) $gb->id, $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['success' => false, 'locked' => true, 'message' => 'PIN을 여러 번 틀려서 잠시 잠겼어요. 15분 뒤에 다시 시도해 주세요.'], 429);
        }
        $pin = (string) $request->input('pin', '');
        if ($pin === '') {
            return response()->json(['success' => false, 'need_pin' => true, 'message' => '가게 확인 PIN(숫자 4자리)을 입력해 주세요.'], 401);
        }
        if (!GroupBuyCouponUse::checkPin($gb, $pin)) {
            RateLimiter::hit($key, 900);
            $left = max(0, 5 - RateLimiter::attempts($key));
            return response()->json(['success' => false, 'need_pin' => true, 'message' => 'PIN이 맞지 않아요.' . ($left > 0 ? " (남은 시도 {$left}번)" : ''), 'attempts_left' => $left], 403);
        }
        RateLimiter::clear($key);
        return ['pin', null];
    }

    private function storeCoupon(string $code): ?GroupBuyCoupon
    {
        return GroupBuyCoupon::where('code', GroupBuyCoupons::normalizeCode($code))->with(['groupBuy.user', 'holder:id,name,nickname'])->first();
    }

    private function storePayload(GroupBuyCoupon $c): array
    {
        $c = GroupBuyCouponUse::refreshState($c);
        $gb = $c->groupBuy;
        $st = self::effectiveStatus($c);
        return [
            'title' => $gb->title, 'status' => $st, 'display_code' => $c->display_code,
            'holder' => $this->maskName($c->holder), 'expires_at' => $c->expires_at, 'used_at' => $c->used_at,
            'use_mode' => $gb->use_mode, 'use_place' => $gb->use_place, 'price' => $gb->current_price,
            'seconds_left' => $st === 'pending' && $c->pending_expires_at ? max(0, (int) now()->diffInSeconds($c->pending_expires_at, false)) : null,
            'can_accept' => $st === 'unused' && $gb->use_mode !== 'online',
        ];
    }

    /** POST /api/coupon-check/{code}/lookup {pin?} — 쿠폰 정보 보기 (미사용인지 등) */
    public function storeLookup(Request $request, string $code)
    {
        $c = $this->storeCoupon($code);
        if (!$c || !$c->groupBuy) return response()->json(['success' => false, 'message' => '알 수 없는 쿠폰이에요.'], 404);
        $acc = $this->storeAccess($request, $c);
        if ($acc instanceof \Illuminate\Http\JsonResponse) return $acc;
        return response()->json(['success' => true, 'data' => $this->storePayload($c), 'via' => $acc[0]]);
    }

    /** POST /api/coupon-check/{code}/accept {pin?} — 가게가 사용 수락 → 손님에게 "맞나요?" */
    public function storeAccept(Request $request, string $code)
    {
        $c = $this->storeCoupon($code);
        if (!$c || !$c->groupBuy) return response()->json(['success' => false, 'message' => '알 수 없는 쿠폰이에요.'], 404);
        $acc = $this->storeAccess($request, $c);
        if ($acc instanceof \Illuminate\Http\JsonResponse) return $acc;
        try {
            GroupBuyCouponUse::requestUse($c, $c->groupBuy, $acc[0], 'store', $acc[1]);
        } catch (\DomainException $e) {
            return $this->fail($e);
        }
        return response()->json(['success' => true, 'message' => '손님 휴대폰에 확인을 보냈어요. 3분 안에 손님이 "맞아요"를 누르면 사용 완료돼요.', 'data' => $this->storePayload($c->fresh(['groupBuy.user', 'holder']))]);
    }

    /** POST /api/coupon-check/{code}/status {pin?} — 손님 응답을 기다리는 동안 상태 확인 */
    public function storeStatus(Request $request, string $code)
    {
        $c = $this->storeCoupon($code);
        if (!$c || !$c->groupBuy) return response()->json(['success' => false, 'message' => '알 수 없는 쿠폰이에요.'], 404);
        $acc = $this->storeAccess($request, $c);
        if ($acc instanceof \Illuminate\Http\JsonResponse) return $acc;
        return response()->json(['success' => true, 'data' => $this->storePayload($c)]);
    }

    /** POST /api/coupon-check/{code}/confirm-code {pin?, confirm_code} — 손님 폰이 안 될 때 확인 코드 4자리로 확인 */
    public function storeConfirmCode(Request $request, string $code)
    {
        $request->validate(['confirm_code' => ['required', 'regex:/^\d{4}$/']], ['confirm_code.regex' => '확인 코드는 숫자 4자리예요.']);
        $c = $this->storeCoupon($code);
        if (!$c || !$c->groupBuy) return response()->json(['success' => false, 'message' => '알 수 없는 쿠폰이에요.'], 404);
        $acc = $this->storeAccess($request, $c);
        if ($acc instanceof \Illuminate\Http\JsonResponse) return $acc;

        // 확인 코드(4자리)도 무작정 대입을 막는다: 쿠폰마다 5번 틀리면 이 쿠폰의 사용 요청을 취소하고 15분 잠금
        $key = 'gb-code:' . $c->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['success' => false, 'message' => '확인 코드를 여러 번 틀렸어요. 잠시 뒤에 다시 시도해 주세요.'], 429);
        }
        try {
            GroupBuyCouponUse::confirmByCode($c, (string) $request->confirm_code, $acc[0], $acc[1]);
        } catch (\DomainException $e) {
            if (str_contains($e->getMessage(), '맞지 않아요')) RateLimiter::hit($key, 900);
            return $this->fail($e);
        }
        RateLimiter::clear($key);
        return response()->json(['success' => true, 'message' => '확인 코드로 사용 처리됐어요.', 'data' => $this->storePayload($c->fresh(['groupBuy.user', 'holder']))]);
    }

    // ─── 신고 공통 ───────────────────────────────────────────────────

    /** 관리자 "보안/신고" 화면에 접수. 대상: 고객이 신고하면 공동구매, 개설자가 신고하면 쿠폰 주인(회원). */
    private function createReport(GroupBuyCoupon $c, GroupBuy $gb, string $class, int $targetId, string $reason, ?string $detail, string $who)
    {
        $dup = Report::where('reporter_id', auth()->id())->where('reportable_type', $class)->where('reportable_id', $targetId)
            ->where('reason', $reason)->where('status', 'pending')->exists();
        if ($dup) return response()->json(['success' => false, 'message' => '이미 신고하셨어요. 관리자가 확인 중이에요.'], 409);

        $content = "쿠폰 {$c->display_code} · 공동구매 '{$gb->title}' (#{$gb->id})" . ($detail ? "\n" . $detail : '');
        $report = Report::create(['reporter_id' => auth()->id(), 'reportable_type' => $class, 'reportable_id' => $targetId, 'reason' => $reason, 'content' => $content]);
        $report->addLog('created', null, 'pending', null, $reason, optional(auth()->user())->nickname ?: optional(auth()->user())->name);
        GroupBuyCoupons::log($c, 'reported', auth()->id(), $who, $reason);
        AdminNotifier::send(1, 'report_submitted', '새 신고가 접수되었습니다', "사유: {$reason}", '/admin/security?tab=reports&status=pending', ['report_id' => $report->id]);
        return response()->json(['success' => true, 'message' => '신고가 접수됐어요. 관리자가 확인할게요.']);
    }
}
