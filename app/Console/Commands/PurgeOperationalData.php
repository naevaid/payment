<?php

namespace App\Console\Commands;

use App\Models\CallbackForwardingLog;
use App\Models\MidtransWebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurgeOperationalData extends Command
{
    protected $signature = 'payment:purge-operational-data {--days= : Override retention days}';

    protected $description = 'Purge old operational logs such as webhook logs, callback logs, and failed jobs.';

    public function handle(): int
    {
        $retentionDays = max(
            1,
            (int) ($this->option('days') ?: config('payment.retention.operational_logs_days', 15))
        );

        $cutoff = Carbon::now()->subDays($retentionDays);

        $deletedWebhookLogs = MidtransWebhookLog::query()
            ->where('received_at', '<', $cutoff)
            ->delete();

        $deletedCallbackLogs = CallbackForwardingLog::query()
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where('responded_at', '<', $cutoff)
                    ->orWhere(function ($subQuery) use ($cutoff): void {
                        $subQuery
                            ->whereNull('responded_at')
                            ->where('dispatched_at', '<', $cutoff);
                    })
                    ->orWhere(function ($subQuery) use ($cutoff): void {
                        $subQuery
                            ->whereNull('responded_at')
                            ->whereNull('dispatched_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->delete();

        $deletedFailedJobs = DB::table('failed_jobs')
            ->where('failed_at', '<', $cutoff)
            ->delete();

        $this->info('Operational data purge selesai.');
        $this->line('Retention days: '.$retentionDays);
        $this->line('Cutoff: '.$cutoff->toDateTimeString());
        $this->line('Deleted midtrans_webhook_logs: '.$deletedWebhookLogs);
        $this->line('Deleted callback_forwarding_logs: '.$deletedCallbackLogs);
        $this->line('Deleted failed_jobs: '.$deletedFailedJobs);

        return self::SUCCESS;
    }
}
