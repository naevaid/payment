<?php

namespace App\Http\Controllers;

use App\Enums\CallbackStatus;
use App\Enums\TransactionStatus;
use App\Models\CallbackForwardingLog;
use App\Models\MidtransWebhookLog;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $failedTransactionStatuses = [
            TransactionStatus::Failed,
            TransactionStatus::Cancelled,
            TransactionStatus::Expired,
            TransactionStatus::Refunded,
        ];
        $callbackMaxAttempts = (int) config('payment.callback.max_attempts', 3);
        $callbackBackoff = array_map('intval', config('payment.callback.backoff', [60, 300, 900]));
        $nextRetryLog = CallbackForwardingLog::query()
            ->whereNotNull('next_retry_at')
            ->orderBy('next_retry_at')
            ->first();
        $topSettlementProjects = Transaction::query()
            ->join('projects', 'projects.id', '=', 'transactions.project_id')
            ->where('transactions.status', TransactionStatus::Settlement->value)
            ->groupBy('transactions.project_id', 'projects.project_name', 'projects.app_id')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(5)
            ->get([
                'transactions.project_id',
                'projects.project_name',
                'projects.app_id',
                DB::raw('COUNT(*) as settlement_transactions'),
                DB::raw('COALESCE(SUM(transactions.amount), 0) as settlement_amount'),
            ]);

        $callbackHealthLogs = CallbackForwardingLog::query()
            ->with(['project', 'transaction.project'])
            ->where(function ($query): void {
                $query
                    ->where('success', false)
                    ->orWhereNotNull('next_retry_at')
                    ->orWhereHas('transaction', function ($transactionQuery): void {
                        $transactionQuery->whereIn('callback_status', [
                            CallbackStatus::Queued,
                            CallbackStatus::Failed,
                            CallbackStatus::Skipped,
                        ]);
                    });
            })
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(function (CallbackForwardingLog $log) use ($callbackMaxAttempts): array {
                $callbackState = $log->transaction?->callback_status?->value
                    ?? ($log->success ? CallbackStatus::Success->value : CallbackStatus::Failed->value);
                $callbackUrl = $log->callback_url ?: ($log->transaction?->callback_url ?: $log->project?->default_callback_url);

                return [
                    'log' => $log,
                    'callback_state' => $callbackState,
                    'retries_remaining' => max($callbackMaxAttempts - $log->attempt, 0),
                    'is_retryable' => ! $log->success
                        && $log->transaction !== null
                        && filled($callbackUrl)
                        && $callbackState !== CallbackStatus::Skipped->value,
                ];
            });

        return view('dashboard.index', [
            'stats' => [
                'projects' => Project::count(),
                'active_projects' => Project::active()->count(),
                'transactions' => Transaction::count(),
                'settlement_transactions' => Transaction::where('status', TransactionStatus::Settlement)->count(),
                'pending_transactions' => Transaction::where('status', TransactionStatus::Pending)->count(),
                'failed_transactions' => Transaction::whereIn('status', $failedTransactionStatuses)->count(),
                'queued_callbacks' => Transaction::where('callback_status', CallbackStatus::Queued)->count(),
                'callback_success' => Transaction::where('callback_status', CallbackStatus::Success)->count(),
                'callback_failed' => Transaction::where('callback_status', CallbackStatus::Failed)->count(),
                'callback_skipped' => Transaction::where('callback_status', CallbackStatus::Skipped)->count(),
                'webhook_logs' => MidtransWebhookLog::count(),
                'forwarding_logs' => CallbackForwardingLog::count(),
            ],
            'ownerMetrics' => [
                'settlement_amount' => (int) Transaction::query()
                    ->where('status', TransactionStatus::Settlement)
                    ->sum('amount'),
                'pending_amount' => (int) Transaction::query()
                    ->where('status', TransactionStatus::Pending)
                    ->sum('amount'),
                'failed_amount' => (int) Transaction::query()
                    ->whereIn('status', $failedTransactionStatuses)
                    ->sum('amount'),
                'top_settlement_projects' => $topSettlementProjects,
            ],
            'callbackHealth' => [
                'queue_connection' => (string) config('queue.default', 'sync'),
                'callback_queue' => (string) config('payment.callback.queue', 'payment-callbacks'),
                'async_mode' => (string) config('queue.default', 'sync') !== 'sync',
                'max_attempts' => $callbackMaxAttempts,
                'backoff' => $callbackBackoff,
                'retry_scheduled' => CallbackForwardingLog::query()->whereNotNull('next_retry_at')->count(),
                'backlog_transactions' => Transaction::query()
                    ->whereIn('callback_status', [
                        CallbackStatus::Queued,
                        CallbackStatus::Failed,
                        CallbackStatus::Skipped,
                    ])
                    ->count(),
                'next_retry_at' => $nextRetryLog?->next_retry_at,
            ],
            'callbackHealthLogs' => $callbackHealthLogs,
            'recentTransactions' => Transaction::query()
                ->with('project')
                ->latest()
                ->limit(5)
                ->get(),
            'recentWebhookLogs' => MidtransWebhookLog::query()
                ->with('transaction.project')
                ->latest()
                ->limit(5)
                ->get(),
            'recentCallbackLogs' => CallbackForwardingLog::query()
                ->with(['project', 'transaction'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
