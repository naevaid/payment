<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MidtransWebhookLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'signature' => ['nullable', 'in:valid,invalid'],
        ]);

        $logs = MidtransWebhookLog::query()
            ->with('transaction.project')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('order_id', 'like', "%{$search}%")
                        ->orWhere('midtrans_transaction_id', 'like', "%{$search}%")
                        ->orWhere('transaction_status', 'like', "%{$search}%");
                });
            })
            ->when($filters['project_id'] ?? null, function ($query, int $projectId): void {
                $query->whereHas('transaction', fn ($transactionQuery) => $transactionQuery->where('project_id', $projectId));
            })
            ->when(($filters['signature'] ?? null) === 'valid', fn ($query) => $query->where('is_signature_valid', true))
            ->when(($filters['signature'] ?? null) === 'invalid', fn ($query) => $query->where('is_signature_valid', false))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.webhook-logs.index', [
            'logs' => $logs,
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_name']),
            'filters' => $filters,
        ]);
    }

    public function show(MidtransWebhookLog $webhookLog): View
    {
        $webhookLog->load('transaction.project');

        return view('dashboard.webhook-logs.show', [
            'log' => $webhookLog,
        ]);
    }
}
