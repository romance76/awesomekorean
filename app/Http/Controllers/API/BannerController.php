<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BannerAd;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    // 공개: 특정 페이지/위치의 활성 배너 가져오기
    public function show(Request $request)
    {
        $query = BannerAd::active()
            ->where(function ($q) use ($request) {
                $q->where('page', $request->page)->orWhere('page', 'all');
            });

        if ($request->position) {
            $query->where('position', $request->position);
        }

        $banners = $query->inRandomOrder()->limit(5)->get();

        // 노출 수 증가
        BannerAd::whereIn('id', $banners->pluck('id'))->increment('impressions');

        return response()->json(['success' => true, 'data' => $banners]);
    }

    // 배너 클릭 추적
    public function click($id)
    {
        BannerAd::where('id', $id)->increment('clicks');
        return response()->json(['success' => true]);
    }

    // 유저: 내 배너 신청 목록
    public function myBanners()
    {
        $banners = BannerAd::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $banners]);
    }

    // 유저: 배너 신청
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:100',
            'image' => 'required|image|max:5120',
            'link_url' => 'nullable|url',
            'page' => 'required|in:home,market,jobs,directory,news,qa,recipes,community,all',
            'position' => 'required|in:top,left,center,right',
            'geo_scope' => 'required|in:all,state,county,city',
            'geo_value' => 'nullable|string|max:100',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $days = now()->parse($request->start_date)->diffInDays(now()->parse($request->end_date)) + 1;

        // 위치별 일일 비용 (포인트)
        $dailyCosts = ['top' => 500, 'center' => 300, 'left' => 200, 'right' => 200];
        $dailyCost = $dailyCosts[$request->position] ?? 200;

        // 페이지별 가중치
        if ($request->page === 'all') $dailyCost = (int)($dailyCost * 1.5);
        if ($request->page === 'home') $dailyCost = (int)($dailyCost * 1.3);

        $totalCost = $days * $dailyCost;

        // 포인트 확인
        $user = auth()->user();
        if ($user->points < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => "포인트 부족. 필요: {$totalCost}P ({$days}일×{$dailyCost}P), 보유: {$user->points}P"
            ], 422);
        }

        $imagePath = $request->file('image')->store('banners', 'public');

        $banner = BannerAd::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'image_url' => '/storage/' . $imagePath,
            'link_url' => $request->link_url,
            'page' => $request->page,
            'position' => $request->position,
            'geo_scope' => $request->geo_scope,
            'geo_value' => $request->geo_value,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'daily_cost' => $dailyCost,
            'total_cost' => $totalCost,
            'status' => 'pending',
        ]);

        // 포인트 차감
        $user->addPoints(-$totalCost, "광고 신청: {$request->title} ({$days}일)", 'banner');

        return response()->json([
            'success' => true,
            'message' => "광고 신청 완료! {$totalCost}P 차감. 관리자 승인 후 게시됩니다.",
            'data' => $banner,
        ], 201);
    }
}
