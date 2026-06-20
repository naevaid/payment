<?php

namespace App\Http\Controllers;

use App\Enums\CallbackStatus;
use App\Enums\TransactionStatus;
use App\Models\CallbackForwardingLog;
use App\Models\MidtransWebhookLog;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', [
            'stats' => [
                'projects' => Project::count(),
                'active_projects' => Project::active()->count(),
                'transactions' => Transaction::count(),
                'settlement_transactions' => Transaction::where('status', TransactionStatus::Settlement)->count(),
                'pending_transactions' => Transaction::where('status', TransactionStatus::Pending)->count(),
                'failed_transactions' => Transaction::whereIn('status', [
                    TransactionStatus::Failed,
                    TransactionStatus::Cancelled,
                    TransactionStatus::Expired,
                    TransactionStatus::Refunded,
                ])->count(),
                'callback_success' => Transaction::where('callback_status', CallbackStatus::Success)->count(),
                'callback_failed' => Transaction::where('callback_status', CallbackStatus::Failed)->count(),
                'webhook_logs' => MidtransWebhookLog::count(),
                'forwarding_logs' => CallbackForwardingLog::count(),
            ],
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
