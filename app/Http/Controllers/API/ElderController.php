<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ElderSetting;
use App\Models\PointLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ElderController extends Controller
{
    public function settings()
    {
        $settings = ElderSetting::firstOrCreate(
            ['user_id' => Auth::id()],
            ['elder_mode' => false, 'checkin_interval' => 24]
        );
        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'elder_mode'       => 'boolean',
            'guardian_phone'   => 'nullable|string|max:20',
            'guardian_name'    => 'nullable|string|max:50',
            'checkin_interval' => 'integer|in:12,24,48',
        ]);

        $settings = ElderSetting::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );
        return response()->json($settings);
    }

    public function checkin()
    {
        $settings = ElderSetting::firstOrCreate(
            ['user_id' => Auth::id()],
            ['elder_mode' => true, 'checkin_interval' => 24]
        );

        $now = now();
        $last = $settings->last_checkin_at;
        $todayChecked = $last && $last->isToday();

        $settings->update([
            'last_checkin_at' => $now,
            'alert_sent'      => false,
        ]);

        $pointsEarned = 0;
        if (!$todayChecked) {
            $pointsEarned = 5;
            PointLog::create([
                'user_id'     => Auth::id(),
                'type'        => 'earn',
                'amount'      => $pointsEarned,
                'description' => '노인 안심 체크인',
            ]);
            User::where('id', Auth::id())->increment('points', $pointsEarned);
        }

        return response()->json([
            'message'       => '체크인 완료!',
            'points_earned' => $pointsEarned,
            'checked_at'    => $now,
        ]);
    }

    public function sos(Request $request)
    {
        $settings = ElderSetting::firstOrCreate(
            ['user_id' => Auth::id()],
            ['elder_mode' => true]
        );

        $settings->update(['last_sos_at' => now()]);

        // TODO: Twilio SMS to guardian_phone
        $guardianPhone = $settings->guardian_phone;
        $guardianName  = $settings->guardian_name ?? '보호자';
        $user          = Auth::user();

        return response()->json([
            'message'       => 'SOS 신호를 보냈습니다.',
            'guardian_name' => $guardianName,
            'sent_to'       => $guardianPhone ? substr($guardianPhone, 0, 3) . '****' . substr($guardianPhone, -4) : null,
        ]);
    }

    public function guardianView($userId)
    {
        $user     = User::findOrFail($userId);
        $settings = ElderSetting::where('user_id', $userId)->first();

        if (!$settings || !$settings->elder_mode) {
            return response()->json(['message' => '노인 안심 모드가 활성화되지 않았습니다.'], 404);
        }

        $overdue = false;
        if ($settings->last_checkin_at) {
            $hoursAgo = $settings->last_checkin_at->diffInHours(now());
            $overdue  = $hoursAgo > $settings->checkin_interval;
        } else {
            $overdue = true;
        }

        return response()->json([
            'user'             => ['id' => $user->id, 'name' => $user->name, 'nickname' => $user->nickname],
            'last_checkin_at'  => $settings->last_checkin_at,
            'checkin_interval' => $settings->checkin_interval,
            'is_overdue'       => $overdue,
            'alert_sent'       => $settings->alert_sent,
            'last_sos_at'      => $settings->last_sos_at,
        ]);
    }
}
