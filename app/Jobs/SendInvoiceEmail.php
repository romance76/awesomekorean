<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Support\Invoicing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 인보이스 메일 한 통을 큐에서 보내고, 결과(발송됨/실패 + 이유 한 줄)를 invoice_deliveries 에 남긴다.
 *
 * - 받는 사람은 발송 이력에 저장된 주소(발행 시점 회원 이메일)뿐이다 — 어디서도 바꿀 수 없다.
 * - 일시적 오류는 최대 3번(1분·5분 간격) 다시 시도하고, 끝내 안 되면 failed 로 기록한다.
 * - 이미 sent 인 이력은 다시 보내지 않는다(큐가 같은 작업을 두 번 줘도 메일은 한 통).
 */
class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $delivery = InvoiceDelivery::find($this->deliveryId);
        if (!$delivery || $delivery->status === InvoiceDelivery::SENT) return;

        $invoice = Invoice::find($delivery->invoice_id);
        if (!$invoice) {
            $this->markFailed($delivery, '인보이스를 찾을 수 없어요');
            return;
        }
        $problem = Invoicing::addressProblem($delivery->to_email);
        if ($problem) {
            $this->markFailed($delivery, $problem);
            return;
        }

        try {
            // InvoiceMail 은 ShouldQueue 라 send() 하면 다시 큐로 가므로, 이미 큐 안인 여기서는 sendNow 로 바로 보낸다
            Mail::to($delivery->to_email)->sendNow(new InvoiceMail($invoice->id));
            $delivery->update(['status' => InvoiceDelivery::SENT, 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $msg = Invoicing::oneLine($e->getMessage() ?: get_class($e));
            if ($this->attempts() >= $this->tries) {
                $this->markFailed($delivery, $msg);
                Log::warning('[인보이스] 메일 발송 실패 인보이스 #' . $invoice->id . ': ' . $msg);
                return;
            }
            $delivery->update(['error' => $msg]);   // 아직 대기 중 — 다시 시도 예정
            throw $e;
        }
    }

    /** 시간 초과 등으로 handle() 밖에서 끝난 경우에도 대기 중으로 남지 않게 */
    public function failed(\Throwable $e): void
    {
        $delivery = InvoiceDelivery::find($this->deliveryId);
        if ($delivery && $delivery->status === InvoiceDelivery::QUEUED) {
            $this->markFailed($delivery, Invoicing::oneLine($e->getMessage() ?: get_class($e)));
        }
    }

    private function markFailed(InvoiceDelivery $delivery, string $reason): void
    {
        $delivery->update(['status' => InvoiceDelivery::FAILED, 'error' => mb_substr($reason, 0, 255)]);
    }
}
