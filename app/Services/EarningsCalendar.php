<?php

namespace App\Services;

use App\Models\EarningsEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/** Nasdaq 공개 어닝(실적 발표) 캘린더에서 날짜별 일정을 받아 earnings_events 에 저장한다. */
class EarningsCalendar
{
    /** 하루치 수집. 성공하면 저장한 건수, 실패하면 null */
    public function syncDate(Carbon $date): ?int
    {
        try {
            $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0', 'Accept' => 'application/json'])->timeout(15)
                ->get('https://api.nasdaq.com/api/calendar/earnings', ['date' => $date->toDateString()]);
            if (!$res->successful()) return null;
            $rows = $res->json('data.rows');
            if (!is_array($rows)) return null;

            $n = 0;
            foreach ($rows as $r) {
                $symbol = strtoupper(trim($r['symbol'] ?? ''));
                if ($symbol === '') continue;
                $slot = match ($r['time'] ?? '') {
                    'time-pre-market' => 'bmo',
                    'time-after-hours' => 'amc',
                    default => 'tns',
                };
                $cap = preg_replace('/[^0-9]/', '', (string) ($r['marketCap'] ?? ''));
                EarningsEvent::updateOrCreate(
                    ['report_date' => $date->toDateString(), 'symbol' => $symbol],
                    [
                        'name' => $r['name'] ?? null,
                        'time_slot' => $slot,
                        'market_cap' => $cap !== '' ? (int) $cap : null,
                        'eps_forecast' => $r['epsForecast'] ?? null,
                        'last_year_eps' => $r['lastYearEPS'] ?? null,
                        'fiscal_quarter' => $r['fiscalQuarterEnding'] ?? null,
                        'est_count' => isset($r['noOfEsts']) && is_numeric($r['noOfEsts']) ? (int) $r['noOfEsts'] : null,
                    ]
                );
                $n++;
            }
            return $n;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** 월~금(미국 동부 기준) 한 주 수집 */
    public function syncWeek(Carbon $monday): int
    {
        $total = 0;
        for ($i = 0; $i < 5; $i++) {
            $n = $this->syncDate($monday->copy()->addDays($i));
            $total += $n ?? 0;
            usleep(250000);
        }
        return $total;
    }

    /** 미국 동부 기준, 오프셋 주의 월요일 (0=이번 주) */
    public static function mondayOf(int $weekOffset = 0): Carbon
    {
        return Carbon::now('America/New_York')->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset)->startOfDay();
    }
}
