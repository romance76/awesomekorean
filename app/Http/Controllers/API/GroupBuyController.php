<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use App\Models\Notification;
use App\Support\DirectPayments;
use App\Support\GroupBuyCompletion;
use App\Support\GroupBuyDeposit;
use App\Support\GroupBuyTier;
use App\Support\UsStates;
use App\Events\NewNotification;
use App\Traits\AdminAuthorizes;
use App\Traits\CompressesUploads;
use App\Traits\HasAdjacent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GroupBuyController extends Controller
{
    use AdminAuthorizes, CompressesUploads, HasAdjacent;

    // 공동구매 승인/거절/완료 결과를 주최자가 통지받지 못하던 문제 수정 —
    // MessageController와 동일한 패턴.
    private function notify(int $userId, string $type, string $title, string $content, array $data = []): void
    {
        Notification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'content' => $content, 'data' => $data]);
        $unread = Notification::where('user_id', $userId)->whereNull('read_at')->count();
        try { broadcast(new NewNotification($userId, $unread, $title))->toOthers(); } catch (\Exception $e) {}
    }

    public function index(Request $request)
    {
        // 관리자 화면(admin=1 + is_approved=0)은 승인 대기 목록을 봐야 하는데 예전에는 이 조건이 무시돼 승인된 글만 나왔다(사업자 공동구매는 승인이 필요하므로 반드시 필요)
        $staff = auth('api')->user();
        $adminView = $request->boolean('admin') && $staff && in_array($staff->role, ['admin', 'super_admin', 'moderator'], true);
        $query = GroupBuy::with('user:id,name,nickname,avatar,lifetime_points')
            ->when($adminView && $request->has('is_approved'), function ($q) use ($request) {
                $q->where('is_approved', $request->boolean('is_approved'))->where('status', '!=', 'awaiting_deposit');   // 카드 결제를 마치지 않은 임시 글은 승인 대상이 아님
                if (!$request->boolean('is_approved')) $q->whereNull('rejection_reason');   // 거절된 것은 대기 목록에서 제외
            }, fn($q) => $q->approved())
            // '모집중'은 마감일이 안 지난 것만, '마감'(ended)은 모집중인데 마감일이 지난 것
            ->when($request->status, function ($q, $v) {
                if ($v === 'ended') return $q->deadlinePassed();
                $q->where('status', $v);
                if ($v === 'recruiting') $q->stillOpen();
            })
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            // 내 지역: 전국 공동구매 + 내 주가 보일 주에 들어 있는 지역 공동구매(+ 예전 글처럼 보일 주가 정해지지 않은 것). scope=national 이면 전국만
            ->when($request->scope === 'national', fn($q) => $q->where('scope', 'national'))
            ->when($request->scope === 'mine' && UsStates::isState($request->state), function ($q) use ($request) {
                $st = UsStates::normalize($request->state);
                $q->where(fn($w) => $w->where('scope', 'national')->orWhereNull('target_states')->orWhereJsonContains('target_states', $st));
            })
            ->when($request->search, fn($q, $v) => $q->where(function($q2) use ($v) {
                $q2->where('title', 'like', "%{$v}%")->orWhere('content', 'like', "%{$v}%");
            }));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50)
                ->orderBy('distance');
        } else {
            $query->orderByDesc('created_at');
        }

        $items = $query->paginate($request->per_page ?? 20);
        $items->getCollection()->transform(function($item) {
            $item->append(['current_discount', 'current_price']);
            return $item;
        });

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * 내가 참여한 공동구매 ID 목록
     */
    public function myJoined()
    {
        $ids = GroupBuyParticipant::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'paid'])
            ->pluck('group_buy_id')
            ->toArray();

        return response()->json(['success' => true, 'data' => $ids]);
    }

    // 사업자등록증 열람 — 올린 본인과 관리자·운영자만. 파일은 비공개 디스크에 있고(예전 것은 공개 디스크), 주소로 직접 열 수 없다.
    public function businessDoc($id)
    {
        $gb = GroupBuy::findOrFail($id);
        $user = auth()->user();
        $isStaff = in_array($user->role ?? '', ['admin', 'super_admin', 'moderator'], true);
        if (!$isStaff && $gb->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }
        $path = ltrim(preg_replace('#^/storage/#', '', (string) ($gb->getAttributes()['business_doc'] ?? '')), '/');
        if ($path === '') {
            return response()->json(['success' => false, 'message' => '등록된 사업자 등록증이 없습니다.'], 404);
        }
        $disk = Storage::disk('local')->exists($path) ? 'local' : (Storage::disk('public')->exists($path) ? 'public' : null);
        if (!$disk) {
            return response()->json(['success' => false, 'message' => '파일을 찾을 수 없습니다.'], 404);
        }
        return Storage::disk($disk)->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    // 내가 연 공동구매 관리(대시보드): 진행 상태·참가자 현황. 참가자 연락처(전화·이메일)는 완료·수락 + 수수료 결제 기능이 생기기 전까지 열지 않는다.
    public function myOpened()
    {
        $rows = GroupBuy::where('user_id', auth()->id())->where('status', '!=', 'awaiting_deposit')->orderByDesc('id')->limit(50)->get();
        $payments = \App\Models\Payment::whereIn('id', $rows->pluck('deposit_payment_id')->filter())->get()->keyBy('id');
        $parts = GroupBuyParticipant::whereIn('group_buy_id', $rows->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->with('user:id,name,nickname,phone,email')
            ->orderByDesc('id')->get()->groupBy('group_buy_id');
        $couponCounts = \App\Models\GroupBuyCoupon::whereIn('group_buy_id', $rows->pluck('id'))
            ->selectRaw('group_buy_id, status, COUNT(*) c')->groupBy('group_buy_id', 'status')->get()->groupBy('group_buy_id');

        $data = $rows->map(function ($gb) use ($parts, $payments, $couponCounts) {
            $list = $parts->get($gb->id, collect());
            $sold = (int) $list->whereIn('status', ['pending', 'paid'])->sum('quantity');
            // 참가자 연락처(이름·전화·이메일)는 완료·수락 + 수수료 결제가 끝난 공동구매에서만 연다 (참가자가 참여할 때 제공에 동의함)
            $contactsOpen = $gb->status === 'completed' && $gb->accepted_at;
            $counts = $couponCounts->get($gb->id, collect())->pluck('c', 'status');
            $accept = null;
            if (in_array($gb->status, ['recruiting', 'confirmed'], true) && $gb->is_approved && !$gb->accepted_at) {
                $why = GroupBuyCompletion::ineligible($gb);
                $t = GroupBuyCompletion::totals($gb);
                $accept = ['can_accept' => $why === null, 'blocked_reason' => $why, 'total_cents' => $t['total_cents'], 'fee_cents' => $t['fee_cents'], 'deposit_cents' => $t['deposit_cents'], 'due_cents' => $t['due_cents']];
            }
            $stage = $gb->rejection_reason ? 'rejected'
                : (!$gb->is_approved ? 'pending_approval'
                : ($gb->display_status === 'ended' ? 'ended' : $gb->status));
            return [
                'id' => $gb->id,
                'title' => $gb->title,
                'stage' => $stage,   // pending_approval / rejected / recruiting / confirmed / completed / cancelled / ended
                'rejection_reason' => $gb->rejection_reason,
                'tier' => GroupBuyTier::tier($gb->group_price, $gb->original_price, $gb->max_participants, $gb->total_quantity, $gb->scope ?: 'local'),
                'scope' => $gb->scope ?: 'local',
                'fulfillment' => $gb->fulfillment,
                'target_states' => $gb->target_states,
                'deposit_cents' => (int) $gb->deposit_cents,
                // 디파짓 상태: held = 카드 보류 중(승인 때 청구) / charged = 청구됨 / released = 청구 없이 풀림 / null = 디파짓 없음
                'deposit_state' => ($pay = $payments->get($gb->deposit_payment_id)) ? ['authorized' => 'held', 'captured' => 'charged', 'released' => 'released'][$pay->status] ?? $pay->status : null,
                'price' => $gb->current_price,
                'deadline' => $gb->deadline,
                'current_participants' => (int) $gb->current_participants,
                'min_participants' => (int) $gb->min_participants,
                'max_participants' => $gb->max_participants,
                'total_quantity' => $gb->total_quantity,
                'sold_quantity' => $sold,
                'accept' => $accept,
                'closed_reason' => $gb->closed_reason,
                'total_cents' => $gb->total_cents,
                'fee_cents' => $gb->fee_cents,
                'contacts_open' => (bool) $contactsOpen,
                'coupon' => $gb->fulfillment === 'coupon' ? [
                    'use_mode' => $gb->use_mode, 'use_place' => $gb->use_place, 'use_url' => $gb->use_url,
                    'valid_days' => (int) $gb->coupon_valid_days, 'extended' => (bool) $gb->coupon_extended_at,
                    'counts' => ['unused' => (int) ($counts['unused'] ?? 0), 'pending' => (int) ($counts['pending'] ?? 0), 'used' => (int) ($counts['used'] ?? 0), 'expired' => (int) ($counts['expired'] ?? 0)],
                ] : null,
                'participants' => $list->values()->map(fn ($p, $i) => array_filter([
                    'id' => $p->id,
                    // 이름은 완료·수수료 결제 전에는 보여 주지 않는다(참가자 1, 참가자 2 …)
                    'name' => $contactsOpen ? ($p->user->nickname ?: $p->user->name) : '참가자 ' . ($i + 1),
                    'quantity' => (int) $p->quantity,
                    'status' => $p->status,
                    'joined_at' => $p->created_at,
                    'contact' => $contactsOpen ? ['name' => $p->user->name, 'phone' => $p->user->phone, 'email' => $p->user->email] : null,
                ], fn ($v) => $v !== null))->values(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show($id)
    {
        $gb = GroupBuy::with(['user:id,name,nickname,avatar,lifetime_points', 'participants' => function($q) {
            $q->where('status', '!=', 'cancelled')->with('user:id,name,nickname,avatar,lifetime_points');
        }])->findOrFail($id);

        $gb->append(['current_discount', 'current_price']);

        $progress = $gb->max_participants
            ? round(($gb->current_participants / $gb->max_participants) * 100, 1)
            : round(($gb->current_participants / $gb->min_participants) * 100, 1);

        $timeRemaining = null;
        if ($gb->deadline && $gb->deadline->isFuture()) {
            $timeRemaining = now()->diff($gb->deadline)->format('%dd %hh %im');
        }

        $nextTier = null;
        if ($gb->discount_tiers) {
            $next = collect($gb->discount_tiers)
                ->sortBy('min_people')
                ->first(fn($t) => $t['min_people'] > $gb->current_participants);
            if ($next) {
                $nextTier = [
                    'people_needed' => $next['min_people'] - $gb->current_participants,
                    'discount_pct' => $next['discount_pct'],
                ];
            }
        }

        // 내 참여 정보
        $myParticipation = null;
        if (auth('api')->check()) {
            $myParticipation = $gb->participants
                ->where('user_id', auth('api')->id())
                ->whereIn('status', ['pending', 'paid'])
                ->first();
        }

        // 참여자 신원(이름·아이디)은 공개하지 않는다 — 개설자에게도 공동구매를 완료하고 수수료를 결제한 뒤에야 제공된다(참여할 때 그렇게 동의받음). 본인과 관리자만 자기 줄/전체를 본다.
        $this->maskParticipants($gb->participants);

        $adj = $this->adjacentPair(GroupBuy::class, $id, 'title', ['category' => $gb->category]);
        return response()->json([
            'success' => true,
            'data' => array_merge($gb->toArray(), [
                'progress' => min($progress, 100),
                'time_remaining' => $timeRemaining,
                'next_tier' => $nextTier,
                'participants_count' => $gb->participants->count(),
                'my_participation' => $myParticipation,
            ]),
            'prev' => $adj['prev'],
            'next' => $adj['next'],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'original_price' => 'required|numeric|min:0',
            'min_participants' => 'required|integer|min:2',
            'max_participants' => 'nullable|integer|min:2',
            'total_quantity' => 'required|integer|min:1|max:100000',
            'scope' => 'required|in:local,national',          // local = 내 주 + 인접 주, national = 전국
            'fulfillment' => 'required|in:delivery,coupon',   // delivery = 택배, coupon = 쿠폰
            'category' => 'required|string',
            'end_type' => 'required|in:target_met,time_limit,flexible',
            'payment_method' => 'required|in:point,none',   // 참가자는 카드로 결제하지 않는다(2026-10-16)
            'images.*' => 'nullable|image|max:5120',
            'business_doc' => 'nullable|file|max:10240',
            'discount_tiers' => 'nullable|json',
            'deadline' => 'nullable|date|after:now',
        ]);

        // 등급: 예상 최대 총액 $500 미만이면 소액(자동 승인, 인원 50명·기간 2주 이하), 그 이상이면 사업자(등록증 + 관리자 승인, 기간 1개월 이하)
        $isStaff = in_array(auth()->user()->role ?? '', ['admin', 'super_admin', 'moderator'], true);
        $scope = $request->scope;
        // 전국 공동구매는 우선 쿠폰용만 연다(택배용 전국은 아직 열지 않음, 2026-10-16)
        if ($scope === GroupBuyTier::SCOPE_NATIONAL && $request->fulfillment !== 'coupon') {
            return response()->json(['success' => false, 'message' => '전국 공동구매는 우선 쿠폰용만 열 수 있어요. 택배용은 기본(내 주 + 인접 주) 범위로 열어 주세요.'], 422);
        }
        // 쿠폰형은 쿠폰을 어떻게 쓰는지(매장/온라인), 사용 장소·주소·방법, 매장 PIN, 유효기간을 개설자가 정한다
        $useData = $this->couponUseData($request, $scope);
        if ($useData instanceof \Illuminate\Http\JsonResponse) return $useData;

        // 기본(지역) 범위 = 작성자 주 + 인접 주. 주를 알아야 하므로 지역 공동구매는 주(State)가 필요하다. 전국은 어디서나 보인다.
        $targetStates = null;
        if ($scope === GroupBuyTier::SCOPE_LOCAL) {
            $targetStates = UsStates::region($request->state);
            if (!$targetStates) {
                return response()->json(['success' => false, 'message' => '지역 공동구매는 주(State)를 정확히 입력해 주세요. 예: GA'], 422);
            }
        }
        $tier = GroupBuyTier::tier($request->group_price, $request->original_price, $request->max_participants, $request->total_quantity, $scope);
        if (!$isStaff) {
            // 개설자는 동시에 하나만: 승인 대기·모집 중인 공동구매가 있으면 새로 열 수 없다(거절·취소·마감된 것은 제외)
            $hasOpen = GroupBuy::where('user_id', auth()->id())->where('status', 'recruiting')->whereNull('rejection_reason')->stillOpen()->exists();
            if ($hasOpen) {
                return response()->json(['success' => false, 'message' => '진행 중인 공동구매가 있어요. 끝나거나 취소된 뒤에 새로 열 수 있어요. (개설자는 동시에 1개)'], 422);
            }
            if (!$request->deadline) {
                return response()->json(['success' => false, 'message' => '마감일을 정해 주세요. (소액 공동구매는 최대 ' . GroupBuyTier::SMALL_MAX_DAYS . '일, 그 이상은 최대 ' . GroupBuyTier::BUSINESS_MAX_DAYS . '일)'], 422);
            }
            if (\Carbon\Carbon::parse($request->deadline)->gt(now()->addDays(GroupBuyTier::maxDays($tier))->endOfDay())) {
                $msg = $tier === GroupBuyTier::SMALL
                    ? '소액 공동구매(총액 $' . GroupBuyTier::SMALL_TOTAL_LIMIT . ' 미만)는 마감일을 오늘부터 ' . GroupBuyTier::SMALL_MAX_DAYS . '일 안으로 정해 주세요.'
                    : '마감일은 오늘부터 ' . GroupBuyTier::BUSINESS_MAX_DAYS . '일(1개월) 안으로 정해 주세요.';
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            if ($tier === GroupBuyTier::BUSINESS && !$request->hasFile('business_doc')) {
                return response()->json(['success' => false, 'message' => '예상 총액이 $' . GroupBuyTier::SMALL_TOTAL_LIMIT . ' 이상이면 사업자 등록증을 첨부해야 합니다.'], 422);
            }
        }
        $autoApprove = $isStaff || $tier === GroupBuyTier::SMALL;
        // 관리자·운영자는 디파짓을 내지 않는다. 그 밖의 개설자는 카드로 디파짓을 내야 글이 열린다(결제 전에는 임시 글 awaiting_deposit)
        $needsDeposit = !$isStaff;
        if ($needsDeposit) GroupBuyDeposit::purgeStale(auth()->id());   // 이전에 카드 입력만 하다 떠난 임시 글 정리

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $this->storeCompressedImageRaw($file, 'groupbuys', 1200, 80);
            }
        }

        $businessDoc = null;
        if ($request->hasFile('business_doc')) {
            $businessDoc = $this->storePrivateDocument($request->file('business_doc'), 'groupbuys/docs');   // 비공개 디스크: 주소로 직접 열 수 없음
        }

        $gb = GroupBuy::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'content' => $request->content,
            'images' => $images,
            'product_url' => $request->product_url,
            'business_doc' => $businessDoc,
            'original_price' => $request->original_price,
            'group_price' => $request->group_price,
            'discount_tiers' => $request->discount_tiers ? json_decode($request->discount_tiers, true) : null,
            'min_participants' => $request->min_participants,
            'max_participants' => $request->max_participants,
            'total_quantity' => $request->total_quantity,
            'current_participants' => 0,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'city' => $request->city,
            'state' => $request->state ? UsStates::normalize($request->state) : null,
            'scope' => $scope,
            'fulfillment' => $request->fulfillment,
            'target_states' => $targetStates,
            ...$useData,
            'status' => $needsDeposit ? 'awaiting_deposit' : 'recruiting',
            'end_type' => $request->end_type,
            'payment_method' => $request->payment_method,
            'is_approved' => !$needsDeposit && $autoApprove,
            'approved_at' => (!$needsDeposit && $autoApprove) ? now() : null,
            'category' => $request->category,
            // 날짜만 고르므로 "그날 밤(미국 동부 기준)까지"로 저장한다. 그대로 저장하면 서버(UTC) 자정이라 미국에서는 하루 일찍 마감돼 보인다.
            'deadline' => $request->deadline ? \Carbon\Carbon::parse($request->deadline, 'America/New_York')->endOfDay()->utc() : null,
        ]);

        $days = $gb->deadline ? max(1, (int) ceil(($gb->deadline->getTimestamp() - time()) / 86400)) : 1;
        $deposit = GroupBuyTier::deposit($tier, $scope, $days);

        if ($needsDeposit) {
            // 카드 보류용 결제 생성 — 사용자가 카드를 확인하면 /deposit/confirm 으로 마무리한다
            try {
                [, $clientSecret] = GroupBuyDeposit::begin($gb, auth()->user(), $deposit * 100);
            } catch (\DomainException $e) {
                GroupBuyDeposit::purge($gb);
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            } catch (\Throwable $e) {
                \Log::error('공동구매 디파짓 결제 생성 실패: ' . $e->getMessage());
                GroupBuyDeposit::purge($gb);
                return response()->json(['success' => false, 'message' => '결제를 시작하지 못했어요. 잠시 후 다시 시도해주세요.'], 502);
            }
            return response()->json([
                'success' => true,
                'message' => '카드를 확인해 주세요.',
                'needs_card' => true,
                'tier' => $tier, 'scope' => $scope, 'deposit' => $deposit,
                'data' => ['group_buy_id' => $gb->id, 'client_secret' => $clientSecret, 'deposit_cents' => $deposit * 100, 'charge_now' => $tier === GroupBuyTier::SMALL],
            ], 201);
        }

        return response()->json(['success' => true, 'tier' => $tier, 'scope' => $scope, 'deposit' => $deposit, 'auto_approved' => $autoApprove, 'data' => $gb->load('user:id,name,nickname')], 201);
    }

    /**
     * 쿠폰형 공동구매의 사용 방식 입력을 검사해 저장할 칸으로 바꾼다. 택배형은 빈 값. 잘못되면 422 응답을 돌려준다.
     * 전국은 온라인 사용만(매장 사용은 추후). 매장 사용이 있으면 매장 PIN(숫자 4자리)이 필요하다.
     * @return array|\Illuminate\Http\JsonResponse
     */
    private function couponUseData(Request $request, string $scope)
    {
        if ($request->fulfillment !== 'coupon') return ['use_mode' => null, 'coupon_valid_days' => 30];

        $mode = (string) $request->input('use_mode', $scope === GroupBuyTier::SCOPE_NATIONAL ? 'online' : 'store');
        if (!in_array($mode, ['store', 'online', 'both'], true)) {
            return response()->json(['success' => false, 'message' => '쿠폰 사용 방식(매장/온라인)을 골라 주세요.'], 422);
        }
        if ($scope === GroupBuyTier::SCOPE_NATIONAL && $mode !== 'online') {
            return response()->json(['success' => false, 'message' => '전국 쿠폰 공동구매는 우선 온라인 사용만 열 수 있어요. 매장 사용은 추후 열려요.'], 422);
        }

        $rules = [
            'coupon_valid_days' => 'nullable|integer|min:7|max:365',
            'promo_code' => 'nullable|string|max:100',
        ];
        $messages = [
            'coupon_valid_days.min' => '쿠폰 유효기간은 7일 이상으로 정해 주세요.', 'coupon_valid_days.max' => '쿠폰 유효기간은 365일까지 정할 수 있어요.',
            'use_place.required' => '매장 사용 장소(가게 이름·주소)를 입력해 주세요.', 'store_pin.required' => '매장 확인 PIN(숫자 4자리)을 정해 주세요.',
            'store_pin.regex' => '매장 확인 PIN은 숫자 4자리예요.', 'use_url.required' => '온라인 사용 주소를 입력해 주세요.', 'use_url.url' => '온라인 사용 주소는 https:// 로 시작하는 주소여야 해요.',
            'use_instructions.required' => '온라인 사용 방법을 적어 주세요.',
        ];
        if ($mode !== 'online') { $rules['use_place'] = 'required|string|max:200'; $rules['store_pin'] = ['required', 'regex:/^\d{4}$/']; }
        if ($mode !== 'store') { $rules['use_url'] = ['required', 'url', 'max:500', 'regex:#^https?://#i']; $rules['use_instructions'] = 'required|string|max:1000'; }
        $request->validate($rules, $messages);

        return [
            'use_mode' => $mode,
            'use_place' => $mode !== 'online' ? trim((string) $request->use_place) : null,
            'use_url' => $mode !== 'store' ? trim((string) $request->use_url) : null,
            'use_instructions' => $mode !== 'store' ? trim((string) $request->use_instructions) : null,
            'promo_code' => $request->promo_code ? trim((string) $request->promo_code) : null,
            'store_pin_hash' => $mode !== 'online' ? \Illuminate\Support\Facades\Hash::make((string) $request->store_pin) : null,
            'coupon_valid_days' => (int) ($request->coupon_valid_days ?: 30),
        ];
    }

    // ─── 완료·수락 (개설자) ───────────────────────────────────────────────

    private function ownedGroupBuy($id): GroupBuy
    {
        return GroupBuy::where('user_id', auth()->id())->findOrFail($id);
    }

    private function totalsPayload(array $t): array
    {
        return [
            'people' => $t['people'], 'quantity' => $t['quantity'], 'price_cents' => $t['price_cents'],
            'total_cents' => $t['total_cents'], 'fee_cents' => $t['fee_cents'], 'deposit_cents' => $t['deposit_cents'],
            'due_cents' => $t['due_cents'], 'waived_cents' => $t['waived_cents'], 'staff' => $t['staff'],
        ];
    }

    /** GET /api/groupbuys/{id}/accept-preview — 수락하면 총액이 얼마이고 수수료·결제액이 얼마인지 미리 보여 준다 */
    public function acceptPreview($id)
    {
        $gb = $this->ownedGroupBuy($id);
        $why = GroupBuyCompletion::ineligible($gb);
        return response()->json(['success' => true, 'data' => array_merge($this->totalsPayload(GroupBuyCompletion::totals($gb)), ['can_accept' => $why === null, 'blocked_reason' => $why])]);
    }

    /**
     * POST /api/groupbuys/{id}/accept — 수락(완료). 낼 수수료가 없으면 바로 완료·쿠폰 발급, 있으면 카드 결제 창을 열 정보를 준다.
     * 참가자 정보(이름·전화·이메일) 열람과 쿠폰 발급은 수수료 결제가 끝난 뒤에만 이루어진다.
     */
    public function accept($id)
    {
        $gb = $this->ownedGroupBuy($id);
        if ($why = GroupBuyCompletion::ineligible($gb)) {
            return response()->json(['success' => false, 'message' => $why], 422);
        }
        $t = GroupBuyCompletion::totals($gb);

        if ($t['due_cents'] <= 0) {
            $issued = GroupBuyCompletion::finalize($gb, $t['total_cents'], $t['fee_cents']);
            return response()->json(['success' => true, 'message' => '공동구매가 완료되었어요.' . ($gb->fulfillment === 'coupon' ? " 참가자에게 쿠폰 {$issued}장이 발급됐어요." : '') . ' 참가자 정보는 "내 공동구매"에서 확인하세요.',
                'data' => array_merge($this->totalsPayload($t), ['needs_card' => false, 'completed' => true])]);
        }

        // 이전에 시작만 하고 끝내지 않은 수수료 결제가 있으면 청구 없이 풀고 새로 만든다 (금액이 바뀌었을 수 있음)
        if ($gb->fee_payment_id && ($old = \App\Models\Payment::find($gb->fee_payment_id))) {
            app(DirectPayments::class)->release($old);
        }
        try {
            [$payment, $secret] = app(DirectPayments::class)->begin(
                auth()->user(), \App\Models\Payment::KIND_GROUPBUY, GroupBuy::class, $gb->id, $t['due_cents'],
                "공동구매 완료 수수료: {$gb->title}"
            );
            $gb->update(['fee_payment_id' => $payment->id, 'fee_cents' => $t['fee_cents'], 'total_cents' => $t['total_cents']]);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('공동구매 수수료 결제 생성 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '결제를 시작하지 못했어요. 잠시 후 다시 시도해주세요.'], 502);
        }
        return response()->json(['success' => true, 'message' => '카드를 확인해 주세요.',
            'data' => array_merge($this->totalsPayload($t), ['needs_card' => true, 'client_secret' => $secret])]);
    }

    /** POST /api/groupbuys/{id}/accept/confirm — 카드 확인이 끝났음을 알림. Stripe 에서 직접 확인하고 청구한 뒤 완료·쿠폰 발급 */
    public function acceptConfirm($id)
    {
        $gb = $this->ownedGroupBuy($id);
        if ($gb->status === 'completed' && $gb->accepted_at) {
            return response()->json(['success' => true, 'message' => '이미 완료된 공동구매예요.', 'data' => ['completed' => true]]);
        }
        if ($why = GroupBuyCompletion::ineligible($gb)) {
            return response()->json(['success' => false, 'message' => $why], 422);
        }
        $payment = $gb->fee_payment_id ? \App\Models\Payment::find($gb->fee_payment_id) : null;
        if (!$payment) {
            return response()->json(['success' => false, 'message' => '결제 정보를 찾을 수 없어요. 처음부터 다시 수락해 주세요.'], 422);
        }
        $dp = app(DirectPayments::class);
        try {
            if ($payment->status !== 'captured') {
                $dp->markAuthorized($payment);
                $dp->capture($payment);
            }
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('공동구매 수수료 청구 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '결제 확인 중 오류가 났어요. 잠시 후 다시 시도해주세요.'], 502);
        }
        $t = GroupBuyCompletion::totals($gb);
        $issued = GroupBuyCompletion::finalize($gb, $t['total_cents'], $t['fee_cents']);
        return response()->json(['success' => true, 'message' => '수수료가 결제됐고 공동구매가 완료되었어요.' . ($gb->fulfillment === 'coupon' ? " 참가자에게 쿠폰 {$issued}장이 발급됐어요." : '') . ' 참가자 정보는 "내 공동구매"에서 확인하세요.',
            'data' => ['completed' => true, 'issued' => $issued]]);
    }

    /**
     * POST /api/groupbuys/{id}/decline {note?} — 조기 마감(모두 취소). 마감일 전이든 사람이 덜 모였든 개설자가 이 시점에 끝낼 수 있다.
     * 참가자 포인트는 돌려주고 낸 디파짓은 돌려주지 않으며, 참가자 모두에게 "피치 못할 사정으로 취소됐다"는 안내를 보낸다.
     */
    public function declineGroupBuy(Request $request, $id)
    {
        $request->validate(['note' => 'nullable|string|max:200']);
        $gb = $this->ownedGroupBuy($id);
        if (!in_array($gb->status, ['recruiting', 'confirmed'], true) || $gb->accepted_at || !$gb->is_approved) {
            return response()->json(['success' => false, 'message' => '종료할 수 없는 공동구매예요.'], 422);
        }
        $n = GroupBuyCompletion::decline($gb, $request->input('note'));
        return response()->json(['success' => true, 'message' => "조기 마감했어요. 참가자 {$n}명에게 취소 안내를 보냈고 포인트는 돌려줬어요. 낸 디파짓은 환불되지 않아요.", 'data' => ['notified' => $n]]);
    }

    /** POST /api/groupbuys/{id}/deposit/confirm — 카드 확인(보류)이 끝났음을 알림. 클라이언트 말만 믿지 않고 Stripe 에서 실제 상태를 조회해 검증한다. */
    public function confirmDeposit($id)
    {
        $gb = GroupBuy::where('user_id', auth()->id())->findOrFail($id);
        if ($gb->status !== 'awaiting_deposit') {
            return response()->json(['success' => true, 'message' => '이미 접수된 공동구매예요.', 'data' => ['group_buy_id' => $gb->id, 'is_approved' => (bool) $gb->is_approved]]);
        }
        try {
            $result = GroupBuyDeposit::confirm($gb);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('공동구매 디파짓 확인 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '결제 확인 중 오류가 났어요. 잠시 후 다시 시도해주세요.'], 502);
        }
        $gb->refresh();
        if ($result === 'held') {
            \App\Support\AdminNotifier::send(2, 'groupbuy_submitted', '새 사업자 공동구매가 승인을 기다리고 있어요', "'{$gb->title}' 공동구매가 승인을 기다리고 있어요.", '/admin/groupbuy', ['groupbuy_id' => $gb->id]);
        }
        $money = '$' . number_format($gb->deposit_cents / 100, 2);
        return response()->json([
            'success' => true,
            'message' => $result === 'captured'
                ? "디파짓 {$money}가 결제됐고, 공동구매가 열렸어요."
                : "접수됐어요. 관리자가 승인하면 그때 카드에 디파짓 {$money}가 청구돼요. 승인되지 않으면 청구되지 않아요.",
            'data' => ['group_buy_id' => $gb->id, 'is_approved' => (bool) $gb->is_approved, 'charged' => $result === 'captured'],
        ]);
    }

    public function update(Request $request, $id)
    {
        $gb = $this->findOwnedOrAdmin(GroupBuy::class, $id);

        if ($gb->is_approved) {
            return response()->json(['success' => false, 'message' => '승인된 공동구매는 수정할 수 없습니다.'], 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
            'original_price' => 'sometimes|numeric|min:0',
            'min_participants' => 'sometimes|integer|min:2',
            'payment_method' => 'sometimes|in:point,none',
            'images.*' => 'nullable|image|max:5120',
            'business_doc' => 'nullable|file|max:10240',
            'discount_tiers' => 'nullable|json',
            'deadline' => 'nullable|date|after:now',
        ]);

        $data = $request->only([
            'title', 'content', 'product_url', 'original_price', 'group_price',
            'min_participants', 'max_participants', 'lat', 'lng', 'city', 'state',
            'end_type', 'payment_method', 'category', 'deadline',
        ]);

        if ($request->discount_tiers) {
            $data['discount_tiers'] = json_decode($request->discount_tiers, true);
        }

        if ($request->hasFile('images')) {
            if ($gb->images) {
                foreach ($gb->images as $old) {
                    Storage::disk('public')->delete($old);
                }
            }
            $images = [];
            foreach ($request->file('images') as $file) {
                $images[] = $this->storeCompressedImageRaw($file, 'groupbuys', 1200, 80);
            }
            $data['images'] = $images;
        }

        if ($request->hasFile('business_doc')) {
            $gb->deleteBusinessDoc();
            $data['business_doc'] = $this->storePrivateDocument($request->file('business_doc'), 'groupbuys/docs');
        }

        $gb->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }

    public function destroy($id)
    {
        $gb = $this->findOwnedOrAdmin(GroupBuy::class, $id);

        if ($gb->status === 'confirmed') {
            return response()->json(['success' => false, 'message' => '확정된 공동구매는 삭제할 수 없습니다.'], 403);
        }

        // 카드 결제를 마치지 않은 임시 글(카드 창 닫기·취소): 청구 없이 보류를 풀고 깨끗이 지운다
        if ($gb->status === 'awaiting_deposit') {
            GroupBuyDeposit::purge($gb);
            return response()->json(['success' => true, 'message' => '취소했어요. 카드에는 청구되지 않았습니다.']);
        }

        // Refund all paid participants
        $paidParticipants = $gb->participants()->where('status', 'paid')->get();
        foreach ($paidParticipants as $p) {
            if ($p->payment_type === 'point' && $p->paid_amount > 0) {
                $p->user->addPoints($p->paid_amount, "공동구매 취소 환불: {$gb->title}", 'groupbuy_refund', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
            }
            $p->update(['status' => 'refunded']);
        }

        if ($gb->images) {
            foreach ($gb->images as $img) {
                Storage::disk('public')->delete($img);
            }
        }
        $gb->deleteBusinessDoc();

        // 카드 보류만 잡혀 있고 아직 청구 전이면 청구 없이 풀어 준다. 이미 청구된 디파짓은 환불하지 않는다(개설 후 철회·삭제).
        $deposit = GroupBuyDeposit::payment($gb);
        $wasCharged = $deposit && $deposit->status === 'captured';
        GroupBuyDeposit::release($gb);

        $gb->update(['status' => 'cancelled']);
        $gb->delete();

        return response()->json(['success' => true, 'message' => $wasCharged
            ? '공동구매가 삭제되었습니다. 이미 결제된 디파짓은 환불되지 않아요.'
            : '공동구매가 삭제되었습니다.']);
    }

    /**
     * Stripe 결제 검증 — 이전엔 클라이언트가 보낸 payment_id가 존재하기만 하면
     * 실제 Stripe 결제 여부·금액을 전혀 확인하지 않고 바로 'paid' 처리했음
     * (PaymentController::confirm()에서 실측 확인된 것과 동일한 패턴의 취약점).
     * PaymentIntent를 직접 조회해 상태·금액을 검증한 뒤에만 결제완료로 인정.
     */
    private function verifyStripePayment(?string $paymentId, int $expectedAmountCents): bool
    {
        if (!$paymentId) return false;
        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) return false;
        try {
            \Stripe\Stripe::setApiKey($stripeSecret);
            $intent = \Stripe\PaymentIntent::retrieve($paymentId);
        } catch (\Exception $e) {
            return false;
        }
        return $intent->status === 'succeeded' && (int) $intent->amount === $expectedAmountCents;
    }

    public function join(Request $request, $id)
    {
        $gb = GroupBuy::findOrFail($id);
        $user = auth()->user();

        if (!$gb->is_approved) {
            return response()->json(['success' => false, 'message' => '아직 승인되지 않은 공동구매입니다.'], 403);
        }
        if ($gb->status !== 'recruiting') {
            return response()->json(['success' => false, 'message' => '모집 중인 공동구매가 아닙니다.'], 400);
        }
        if ($gb->user_id === $user->id) {
            return response()->json(['success' => false, 'message' => '본인이 개설한 공동구매에 참여할 수 없습니다.'], 400);
        }
        if ($gb->deadline && $gb->deadline->isPast()) {
            return response()->json(['success' => false, 'message' => '마감 기한이 지났습니다.'], 400);
        }
        // 공동구매가 완료되면 이름·전화번호·이메일이 개설자에게 제공된다 — 동의하지 않으면 참여할 수 없다(이용약관 제26조의2, 개인정보처리방침)
        if (!$request->boolean('consent')) {
            return response()->json(['success' => false, 'message' => '공동구매가 완료되면 이름(아이디)·전화번호·이메일이 개설자에게 제공돼요. 동의하셔야 참여할 수 있어요.'], 422);
        }
        $existing = GroupBuyParticipant::where('group_buy_id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'paid'])
            ->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => '이미 참여한 공동구매입니다.'], 400);
        }
        if ($gb->max_participants && $gb->current_participants >= $gb->max_participants) {
            return response()->json(['success' => false, 'message' => '정원이 초과되었습니다.'], 400);
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        // 총 수량이 정해진 공동구매는 남은 수량까지만 (소액 공동구매 50개 한도)
        if ($gb->total_quantity) {
            $taken = (int) GroupBuyParticipant::where('group_buy_id', $gb->id)->whereIn('status', ['pending', 'paid'])->sum('quantity');
            if ($taken + $quantity > (int) $gb->total_quantity) {
                $left = max(0, (int) $gb->total_quantity - $taken);
                return response()->json(['success' => false, 'message' => $left > 0 ? "남은 수량은 {$left}개예요. 수량을 줄여 주세요." : '준비한 수량이 모두 찼어요.'], 400);
            }
        }
        // $1 = 100P
        $amount = $gb->current_price * $quantity * 100;
        $paymentType = 'none';
        $paymentId = null;
        $status = 'pending';

        return DB::transaction(function() use ($gb, $user, $request, $quantity, &$amount, &$paymentType, &$paymentId, &$status) {
            switch ($gb->payment_method) {
                case 'none':
                    $paymentType = 'none';
                    $amount = 0;
                    $status = 'paid';
                    break;

                case 'point':
                    if ($user->points < $amount) {
                        return response()->json(['success' => false, 'message' => '포인트가 부족합니다.'], 400);
                    }
                    $user->addPoints(-$amount, "공동구매 참여: {$gb->title}", 'groupbuy_join', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
                    $paymentType = 'point';
                    $status = 'paid';
                    break;

                case 'stripe':
                    $paymentType = 'stripe';
                    $paymentId = $request->input('payment_id');
                    $status = $this->verifyStripePayment($paymentId, (int) round($amount)) ? 'paid' : 'pending';
                    break;

                case 'both':
                    $paymentType = $request->input('payment_type', 'point');
                    if ($paymentType === 'point') {
                        if ($user->points < $amount) {
                            return response()->json(['success' => false, 'message' => '포인트가 부족합니다.'], 400);
                        }
                        $user->addPoints(-$amount, "공동구매 참여: {$gb->title}", 'groupbuy_join', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
                        $status = 'paid';
                    } else {
                        $paymentId = $request->input('payment_id');
                        $status = $this->verifyStripePayment($paymentId, (int) round($amount)) ? 'paid' : 'pending';
                    }
                    break;
            }

            $participant = GroupBuyParticipant::create([
                'group_buy_id' => $gb->id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'paid_amount' => $amount,
                'payment_type' => $paymentType,
                'payment_id' => $paymentId,
                'status' => $status,
                'consent_at' => now(),
            ]);

            $gb->increment('current_participants');

            if ($gb->end_type === 'target_met' && $gb->max_participants && $gb->current_participants >= $gb->max_participants) {
                $gb->update(['status' => 'confirmed']);
            }

            if ($status === 'paid') {
                \App\Support\MilestonePoints::award($user, 'groupbuy_join_bonus', \App\Models\GroupBuy::class, $gb->id, "공동구매 참여 보너스: {$gb->title}", 'groupbuy_join_bonus_daily_max');
            }

            return response()->json(['success' => true, 'data' => $participant->load('user:id,name,nickname')], 201);
        });
    }

    public function cancelParticipation($id)
    {
        $gb = GroupBuy::findOrFail($id);
        $user = auth()->user();

        $participant = GroupBuyParticipant::where('group_buy_id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'paid'])
            ->firstOrFail();

        if ($gb->status === 'confirmed') {
            return response()->json(['success' => false, 'message' => '확정된 공동구매는 취소할 수 없습니다.'], 400);
        }
        // 완료되어 쿠폰이 발급됐거나 종료된 공동구매는 참여를 취소할 수 없다
        if (in_array($gb->status, ['completed', 'cancelled'], true)) {
            return response()->json(['success' => false, 'message' => '이미 끝난 공동구매는 참여를 취소할 수 없습니다.'], 400);
        }

        DB::transaction(function() use ($gb, $user, $participant) {
            if ($participant->status === 'paid' && $participant->paid_amount > 0) {
                if ($participant->payment_type === 'point') {
                    $user->addPoints($participant->paid_amount, "공동구매 참여 취소 환불: {$gb->title}", 'groupbuy_refund', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
                }
            }

            $participant->update(['status' => 'cancelled']);
            $gb->decrement('current_participants');
        });

        return response()->json(['success' => true, 'message' => '참여가 취소되었습니다.']);
    }

    public function participants($id)
    {
        $gb = GroupBuy::findOrFail($id);

        $participants = $gb->participants()
            ->with('user:id,name,nickname,avatar,lifetime_points')
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();
        $this->maskParticipants($participants);

        return response()->json(['success' => true, 'data' => $participants]);
    }

    /**
     * 참여자 목록에서 다른 사람의 신원을 가린다(본인·관리자·운영자는 제외). 사람 수와 수량은 그대로 보인다.
     * 이름·전화·이메일은 개설자가 공동구매를 완료(수락 + 수수료 결제)한 뒤 "내 공동구매"에서만 제공된다.
     */
    private function maskParticipants($participants): void
    {
        $viewer = auth('api')->user();
        $staff = $viewer && in_array($viewer->role, ['admin', 'super_admin', 'moderator'], true);
        foreach ($participants as $p) {
            if ($staff || ($viewer && (int) $p->user_id === (int) $viewer->id)) continue;
            $p->setRelation('user', (new \App\Models\User)->forceFill(['id' => null, 'name' => '참여자', 'nickname' => '참여자', 'avatar' => null, 'lifetime_points' => 0]));
            $p->makeHidden(['user_id']);
        }
    }

    // --- Admin Methods ---

    // AdminMiddleware가 admin/super_admin/moderator 전부를 /admin/* 통과시키는데
    // 여기는 정확히 'admin' 문자열만 확인해 super_admin/moderator가 전부 403을
    // 받고 있었음(실측 확인 — role='admin' 계정이 시스템에 1개뿐이라 사실상
    // 아무도 못 쓰는 상태였음) — 미들웨어와 동일한 허용 목록으로 통일
    private function assertGroupBuyAdmin(): ?\Illuminate\Http\JsonResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'super_admin', 'moderator'], true)) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }
        return null;
    }

    public function adminApprove(Request $request, $id)
    {
        if ($res = $this->assertGroupBuyAdmin()) return $res;

        $gb = GroupBuy::findOrFail($id);
        if ($gb->status === 'awaiting_deposit') {
            return response()->json(['success' => false, 'message' => '개설자가 아직 디파짓 카드 결제를 마치지 않았어요.'], 422);
        }
        // 사업자 공동구매는 승인할 때 개설자 카드에 디파짓이 청구된다(보류 → 청구). 보류가 만료됐으면 승인하지 않고 안내한다.
        try {
            GroupBuyDeposit::capture($gb);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $gb->update([
            'is_approved' => true,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        // 공구 등록 포인트는 스팸 방지를 위해 관리자 승인 시점에 지급
        $organizer = \App\Models\User::find($gb->user_id);
        if ($organizer) {
            \App\Support\WritePoints::award($organizer, \App\Models\GroupBuy::class, $gb->id, '공동구매 등록 승인');
        }

        $this->notify($gb->user_id, 'groupbuy_approved', '공동구매가 승인되었습니다', "'{$gb->title}' 공동구매가 승인되었습니다.", ['groupbuy_id' => $id]);

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }

    public function adminReject(Request $request, $id)
    {
        if ($res = $this->assertGroupBuyAdmin()) return $res;

        // 관리자 화면은 사유를 reason 으로 보내고 있었다 — 예전에는 이름이 달라 거절가 항상 422 로 실패했다. 어느 이름이든 받는다.
        $request->merge(['rejection_reason' => $request->input('rejection_reason', $request->input('reason'))]);
        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $gb = GroupBuy::findOrFail($id);

        // 승인됐다가 거절되는 경우 이미 결제 참여한 사람들의 포인트를
        // destroy()와 동일한 방식으로 환불 — 실측 확인된 자금 유실 버그
        // (거절해도 참여자 포인트가 그대로 사라지고 있었음)
        DB::transaction(function () use ($gb, $request) {
            $paidParticipants = $gb->participants()->where('status', 'paid')->get();
            foreach ($paidParticipants as $p) {
                if ($p->payment_type === 'point' && $p->paid_amount > 0) {
                    $p->user->addPoints($p->paid_amount, "공동구매 거절 환불: {$gb->title}", 'groupbuy_refund', ['type' => \App\Models\GroupBuy::class, 'id' => $gb->id]);
                }
                $p->update(['status' => 'refunded']);
            }

            $gb->update([
                'is_approved' => false,
                'rejection_reason' => $request->rejection_reason,
            ]);
            // 아직 청구 전(카드 보류)인 디파짓은 청구 없이 풀어 준다. 이미 청구된 것(소액·승인 후 거절)은 건드리지 않는다.
            GroupBuyDeposit::release($gb);
        });

        $this->notify($gb->user_id, 'groupbuy_rejected', '공동구매가 거절되었습니다', "'{$gb->title}' 공동구매가 거절되었습니다. 사유: {$request->rejection_reason}", ['groupbuy_id' => $id]);

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }

    public function adminComplete($id)
    {
        if ($res = $this->assertGroupBuyAdmin()) return $res;

        $gb = GroupBuy::findOrFail($id);

        if (!in_array($gb->status, ['recruiting', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => '완료 처리할 수 없는 상태입니다.'], 400);
        }

        // 관리자가 대신 완료 처리: 개설자 수락과 같은 완료 처리(쿠폰 발급·알림)를 하되 수수료는 받지 않는다(관리자 조치)
        $t = GroupBuyCompletion::totals($gb);
        GroupBuyCompletion::finalize($gb, $t['total_cents'], 0);

        return response()->json(['success' => true, 'data' => $gb->fresh()->load('user:id,name,nickname')]);
    }
}
