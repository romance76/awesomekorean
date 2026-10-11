<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Invoicing;
use Illuminate\Console\Command;

/**
 * 인보이스 기능이 생기기 전에 끝난 결제에 과거 인보이스를 만들어 주는 도구.
 *
 *   php artisan invoices:backfill              미리보기만 (기본) — 몇 건이 대상인지만 보여 주고 아무것도 만들지 않음
 *   php artisan invoices:backfill --execute    실제로 만든다 (Kay 승인 후에만)
 *
 * 만들 때도 메일은 절대 보내지 않는다(옛 결제 고객에게 갑자기 메일이 가지 않게). payments 행은 읽기만 하고 바꾸지 않는다.
 * 대상: 결제 완료(completed / captured)이고 아직 인보이스가 없는 결제. 환불된 결제는 건너뛴다.
 */
class InvoicesBackfill extends Command
{
    protected $signature = 'invoices:backfill {--execute : 실제로 인보이스를 만든다 (없으면 미리보기만)} {--limit=0 : 최대 몇 건까지 (0 = 전부)}';
    protected $description = '인보이스가 없는 지난 결제에 인보이스를 만든다 (기본은 미리보기, 메일은 보내지 않음)';

    public function handle(): int
    {
        if (!Invoicing::ready()) {
            $this->error('invoices 표가 아직 없어요. 먼저 마이그레이션을 실행하세요.');
            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $limit = max(0, (int) $this->option('limit'));

        $base = Payment::whereIn('status', Invoicing::PAID)
            ->whereNotIn('id', Invoice::query()->whereNotNull('payment_id')->select('payment_id'));
        $total = (clone $base)->count();
        $this->info(($execute ? '[실행]' : '[미리보기]') . " 인보이스가 없는 결제 완료 건: {$total}건" . ($limit ? " (이번에는 최대 {$limit}건)" : ''));

        if (!$execute) {
            $this->line('아무것도 만들지 않았어요. 실제로 만들려면 --execute 를 붙이세요. (메일은 보내지 않아요)');
            return self::SUCCESS;
        }

        $made = 0;
        $failed = 0;
        $query = (clone $base)->orderBy('id');
        if ($limit) $query->limit($limit);
        foreach ($query->get() as $payment) {
            try {
                $invoice = Invoicing::issueForPayment($payment, $payment->captured_at ?: $payment->created_at);
                if ($invoice->wasRecentlyCreated) $made++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("결제 #{$payment->id} 실패: " . Invoicing::oneLine($e->getMessage()));
            }
        }
        $this->info("만든 인보이스 {$made}건, 실패 {$failed}건 (메일은 보내지 않았어요)");
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
