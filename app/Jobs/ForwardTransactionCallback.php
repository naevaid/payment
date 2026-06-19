<?php

namespace App\Jobs;

use App\Enums\CallbackStatus;
use App\Models\Transaction;
use App\Services\ProjectCallbackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ForwardTransactionCallback implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $transactionId,
    ) {
        $this->tries = (int) config('payment.callback.max_attempts', 3);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_map('intval', config('payment.callback.backoff', [60, 300, 900]));
    }

    /**
     * Execute the job.
     */
    public function handle(ProjectCallbackService $callbackService): void
    {
        $transaction = Transaction::with('project')->findOrFail($this->transactionId);

        $callbackService->forward($transaction, $this->attempts());
    }

    public function failed(?Throwable $exception): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (! $transaction) {
            return;
        }

        $transaction->forceFill([
            'callback_status' => CallbackStatus::Failed,
        ])->save();
    }
}
