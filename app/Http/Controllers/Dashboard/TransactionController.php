<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'status' => ['nullable', 'string'],
            'callback_status' => ['nullable', 'string'],
        ]);

        $transactions = Transaction::query()
            ->with('project')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('gateway_order_id', 'like', "%{$search}%")
                        ->orWhere('client_order_id', 'like', "%{$search}%")
                        ->orWhere('midtrans_transaction_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['callback_status'] ?? null, fn ($query, $status) => $query->where('callback_status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.transactions.index', [
            'transactions' => $transactions,
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_name']),
            'filters' => $filters,
        ]);
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load([
            'project',
            'webhookLogs' => fn ($query) => $query->latest(),
            'callbackForwardingLogs' => fn ($query) => $query->latest(),
        ]);

        return view('dashboard.transactions.show', [
            'transaction' => $transaction,
        ]);
    }
}
