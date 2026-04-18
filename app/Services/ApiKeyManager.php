<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * API 키 중앙 관리 (Phase 2-C 묶음 6).
 * DB 기반 키 조회 + 호출 로깅 + 사용량 집계.
 */
class ApiKeyManager
{
    public static function get(string $service): ?string
    {
        return Cache::remember("apikey.{$service}", 300, function () use ($service) {
            $row = DB::table('api_keys')
                ->where('service', $service)
                ->where('is_active', true)
                ->first();
            if (!$row) return null;
            // 기존 평문·암호문 혼용 상태 지원
            $raw = $row->api_key;
            try {
                $decrypted = Crypt::decryptString($raw);
                return $decrypted;
            } catch (\Throwable $e) {
                return $raw;
            }
        });
    }

    public static function mask(string $key): string
    {
        if (strlen($key) <= 8) return '••••';
        return substr($key, 0, 6) . '••••' . substr($key, -4);
    }

    public static function recordCall(string $service, bool $success, int $responseMs = 0, ?int $httpStatus = null, ?string $errorMessage = null): void
    {
        $today = now()->toDateString();

        DB::table('api_usage')->updateOrInsert(
            ['service' => $service, 'date' => $today],
            []
        );

        if ($success) {
            DB::table('api_usage')
                ->where('service', $service)->where('date', $today)
                ->increment('success_count');
        } else {
            DB::table('api_usage')
                ->where('service', $service)->where('date', $today)
                ->increment('error_count');
        }

        if ($responseMs > 0) {
            // 간단한 running average (정확도보다 부하 낮음)
            $row = DB::table('api_usage')->where('service', $service)->where('date', $today)->first();
            $total = ($row->success_count ?? 0) + ($row->error_count ?? 0);
            $newAvg = $total > 0 ? (int) ((($row->avg_response_ms ?? 0) * ($total - 1) + $responseMs) / $total) : $responseMs;
            DB::table('api_usage')->where('service', $service)->where('date', $today)->update(['avg_response_ms' => $newAvg]);
        }

        if (!$success) {
            DB::table('api_logs')->insert([
                'service'          => $service,
                'level'            => 'error',
                'message'          => $errorMessage ?? 'API call failed',
                'http_status'      => $httpStatus,
                'response_time_ms' => $responseMs,
                'created_at'       => now(),
            ]);
            DB::table('api_keys')
                ->where('service', $service)
                ->update([
                    'last_error_at'       => now(),
                    'last_error_message'  => $errorMessage,
                    'updated_at'          => now(),
                ]);
        } else {
            DB::table('api_keys')
                ->where('service', $service)
                ->update([
                    'last_verified_at' => now(),
                    'updated_at'       => now(),
                ]);
        }
    }

    public static function isKillSwitched(string $service): bool
    {
        $row = DB::table('api_keys')->where('service', $service)->first();
        return $row && !$row->is_active;
    }
}
