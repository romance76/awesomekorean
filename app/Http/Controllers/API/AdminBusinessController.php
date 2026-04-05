<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminBusinessController extends Controller
{
    /**
     * GET /api/admin/businesses
     * 업소 목록 (검색/카테고리 필터)
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('businesses as b')
                ->leftJoin('users as u', 'b.owner_id', '=', 'u.id')
                ->select(
                    'b.*',
                    'u.nickname as owner_name',
                    DB::raw('(SELECT COUNT(*) FROM business_reviews WHERE business_id = b.id) as review_count'),
                    DB::raw('(SELECT COUNT(*) FROM business_claims WHERE business_id = b.id AND status = "pending") as pending_claims')
                );

            if ($search = $request->input('search')) {
                $s = "%{$search}%";
                $query->where(function ($q) use ($s) {
                    $q->where('b.name', 'like', $s)
                      ->orWhere('b.name_ko', 'like', $s)
                      ->orWhere('b.address', 'like', $s)
                      ->orWhere('b.phone', 'like', $s);
                });
            }

            if ($category = $request->input('category')) {
                $query->where('b.category', $category);
            }
            if ($request->has('is_claimed')) {
                $query->where('b.is_claimed', (bool) $request->input('is_claimed'));
            }
            if ($request->has('is_premium')) {
                $query->where('b.is_premium', (bool) $request->input('is_premium'));
            }
            if ($city = $request->input('city')) {
                $query->where('b.city', 'like', "%{$city}%");
            }
            if ($request->has('is_verified')) {
                $query->where('b.is_verified', (bool) $request->input('is_verified'));
            }

            $query->orderByDesc('b.is_premium')->orderByDesc('b.created_at');

            return response()->json(['success' => true, 'data' => $query->paginate(30)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/admin/businesses/{id}
     * 업소 정보 관리자 수정
     */
    public function update(Request $request, $id)
    {
        try {
            $business = Business::findOrFail($id);

            $data = $request->only([
                'name', 'name_ko', 'name_en', 'category', 'address', 'phone',
                'website', 'city', 'state', 'zipcode',
                'premium_type', 'is_claimed', 'description', 'hours',
            ]);

            $business->update($data);

            return response()->json(['success' => true, 'data' => $business->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/businesses/{id}
     * 업소 삭제 (비활성화)
     */
    public function destroy($id)
    {
        try {
            $business = Business::findOrFail($id);
            $business->delete();

            return response()->json(['success' => true, 'message' => '업소가 비활성화되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/business-claims
     * 대기 중인 업소 소유권 주장 목록
     */
    public function claims(Request $request)
    {
        try {
            $status = $request->input('status', 'pending');

            $query = DB::table('business_claims as c')
                ->join('businesses as b', 'c.business_id', '=', 'b.id')
                ->join('users as u', 'c.user_id', '=', 'u.id')
                ->select(
                    'c.*',
                    'b.name as business_name',
                    'b.address as business_address',
                    'b.phone as business_phone',
                    'u.nickname',
                    'u.email as user_email'
                );

            if ($status !== 'all') {
                $query->where('c.status', $status);
            }

            $claims = $query->orderByDesc('c.created_at')->paginate(20);

            return response()->json(['success' => true, 'data' => $claims]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/business-claims/{id}/approve
     * 소유권 주장 승인 -> business.owner_id 설정
     */
    public function approveClaim(Request $request, $claimId)
    {
        try {
            $claim = DB::table('business_claims')->where('id', $claimId)->first();

            if (!$claim) {
                return response()->json(['success' => false, 'message' => '해당 소유권 요청을 찾을 수 없습니다.'], 404);
            }

            // 승인 처리
            DB::table('business_claims')->where('id', $claimId)->update([
                'status'      => 'approved',
                'admin_note'  => $request->input('note', ''),
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'updated_at'  => now(),
            ]);

            // 업소 소유자 설정
            DB::table('businesses')->where('id', $claim->business_id)->update([
                'owner_id' => $claim->user_id,
                'is_claimed'    => true,
                'updated_at'    => now(),
            ]);

            // 같은 업소의 다른 대기 중 요청 거절
            DB::table('business_claims')
                ->where('business_id', $claim->business_id)
                ->where('id', '!=', $claimId)
                ->where('status', 'pending')
                ->update(['status' => 'rejected', 'updated_at' => now()]);

            return response()->json(['success' => true, 'message' => '소유권이 승인되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/business-claims/{id}/reject
     * 소유권 주장 거절
     */
    public function rejectClaim(Request $request, $claimId)
    {
        try {
            $claim = DB::table('business_claims')->where('id', $claimId)->first();

            if (!$claim) {
                return response()->json(['success' => false, 'message' => '해당 소유권 요청을 찾을 수 없습니다.'], 404);
            }

            DB::table('business_claims')->where('id', $claimId)->update([
                'status'      => 'rejected',
                'admin_note'  => $request->input('note', ''),
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'updated_at'  => now(),
            ]);

            return response()->json(['success' => true, 'message' => '소유권 요청이 거절되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
