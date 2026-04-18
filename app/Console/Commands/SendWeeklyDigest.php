<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Phase 2-C Post: 주간 KPI 이메일 다이제스트 (관리자용).
 * cron: 0 9 * * 1 (매주 월요일 09:00).
 */
class SendWeeklyDigest extends Command
{
    protected $signature = 'digest:weekly
        {--to= : 특정 이메일만 (기본: 모든 super_admin + manager)}';
    protected $description = '주간 KPI 요약 이메일 발송';

    public function handle(): int
    {
        $to = $this->option('to');
        $recipients = [];

        if ($to) {
            $recipients[] = (object) ['email' => $to, 'nickname' => 'Admin'];
        } else {
            // Spatie 기반 super_admin + manager 조회
            try {
                $recipients = User::role(['super_admin', 'manager'])->get(['id', 'email', 'nickname', 'name']);
            } catch (\Throwable $e) {
                // 폴백: legacy users.role
                $recipients = User::whereIn('role', ['super_admin', 'admin', 'manager'])->get(['id', 'email', 'nickname', 'name']);
            }
        }

        if (empty($recipients) || (is_object($recipients) && $recipients->isEmpty())) {
            $this->warn('수신자 없음');
            return self::SUCCESS;
        }

        // 지난 7일 KPI 집계
        $weekStart = now()->subDays(7)->startOfDay()->toDateString();
        $weekEnd   = now()->subDay()->toDateString();

        $rows = DB::table('kpi_daily')->whereBetween('date', [$weekStart, $weekEnd])->get();
        if ($rows->isEmpty()) {
            $this->warn('KPI 데이터 없음 — kpi:daily cron 확인 필요');
            return self::SUCCESS;
        }

        $summary = [
            'new_users'     => (int) $rows->sum('new_users'),
            'dau_avg'       => round($rows->avg('dau'), 1),
            'posts'         => (int) $rows->sum('posts_count'),
            'comments'      => (int) $rows->sum('comments_count'),
            'market'        => (int) $rows->sum('market_items_count'),
            'realestate'    => (int) $rows->sum('real_estate_count'),
            'payments'      => (int) $rows->sum('payments_count'),
            'revenue'       => round($rows->sum('revenue_usd'), 2),
            'reports'       => (int) $rows->sum('reports_count'),
            'total_users'   => (int) ($rows->last()->total_users ?? 0),
        ];

        $subject = "📊 SomeKorean 주간 리포트 ({$weekStart} ~ {$weekEnd})";
        $body = $this->renderEmailBody($weekStart, $weekEnd, $summary);

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $u) {
            if (empty($u->email)) continue;
            try {
                Mail::html($body, function ($m) use ($u, $subject) {
                    $m->to($u->email, $u->nickname ?? $u->name ?? 'Admin')->subject($subject);
                });
                $sent++;
                $this->info("Sent to {$u->email}");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Failed {$u->email}: " . $e->getMessage());
            }
        }

        $this->line("Done: sent={$sent} failed={$failed}");
        return self::SUCCESS;
    }

    protected function renderEmailBody(string $start, string $end, array $s): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
  <div style="background: linear-gradient(135deg, #f59e0b, #fb923c); color: white; padding: 30px; border-radius: 12px; text-align: center;">
    <h1 style="margin: 0; font-size: 24px;">📊 SomeKorean 주간 리포트</h1>
    <p style="margin: 5px 0 0; opacity: 0.9; font-size: 14px;">{$start} ~ {$end}</p>
  </div>

  <div style="background: #fff; padding: 24px; border: 1px solid #eee; border-radius: 12px; margin-top: 16px;">
    <h2 style="font-size: 18px; margin: 0 0 16px;">주요 지표</h2>

    <table style="width: 100%; border-collapse: collapse;">
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">총 회원</td>
        <td style="padding: 12px 4px; text-align: right; font-weight: bold;">{$s['total_users']} 명</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">신규 가입</td>
        <td style="padding: 12px 4px; text-align: right; font-weight: bold; color: #10b981;">+{$s['new_users']} 명</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">DAU 평균</td>
        <td style="padding: 12px 4px; text-align: right; font-weight: bold;">{$s['dau_avg']} 명/일</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">신규 게시글</td>
        <td style="padding: 12px 4px; text-align: right;">{$s['posts']}</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">댓글</td>
        <td style="padding: 12px 4px; text-align: right;">{$s['comments']}</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">장터 등록</td>
        <td style="padding: 12px 4px; text-align: right;">{$s['market']}</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">부동산 매물</td>
        <td style="padding: 12px 4px; text-align: right;">{$s['realestate']}</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">결제 건수</td>
        <td style="padding: 12px 4px; text-align: right;">{$s['payments']}</td>
      </tr>
      <tr style="border-bottom: 1px solid #eee;">
        <td style="padding: 12px 4px; color: #666;">매출</td>
        <td style="padding: 12px 4px; text-align: right; font-weight: bold; color: #f59e0b;">\${$s['revenue']}</td>
      </tr>
      <tr>
        <td style="padding: 12px 4px; color: #666;">신고 접수</td>
        <td style="padding: 12px 4px; text-align: right; color: {$this->reportsColor($s['reports'])};">{$s['reports']}</td>
      </tr>
    </table>
  </div>

  <div style="text-align: center; margin-top: 24px;">
    <a href="https://somekorean.com/admin/v2/dashboard" style="display: inline-block; background: #f59e0b; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">
      📊 전체 대시보드 보기
    </a>
  </div>

  <p style="text-align: center; font-size: 12px; color: #999; margin-top: 24px;">
    이 이메일은 관리자에게 매주 월요일 자동 발송됩니다.
  </p>
</body>
</html>
HTML;
    }

    protected function reportsColor(int $n): string
    {
        return $n > 10 ? '#ef4444' : ($n > 0 ? '#f59e0b' : '#10b981');
    }
}
