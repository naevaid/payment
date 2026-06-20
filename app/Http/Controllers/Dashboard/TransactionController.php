<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $transactions = $this->filteredQuery($filters)
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.transactions.index', [
            'transactions' => $transactions,
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_name']),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $rows = $this->filteredQuery($filters)
            ->orderByDesc('created_at')
            ->get();

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'gateway_order_id',
                'client_order_id',
                'project_name',
                'amount',
                'currency',
                'status',
                'callback_status',
                'payment_type',
                'callback_url',
                'created_at',
            ]);

            $rows->each(function (Transaction $transaction) use ($handle): void {
                fputcsv($handle, [
                    $transaction->gateway_order_id,
                    $transaction->client_order_id,
                    $transaction->project?->project_name,
                    $transaction->amount,
                    $transaction->currency,
                    $transaction->status->value,
                    $transaction->callback_status->value,
                    $transaction->payment_type,
                    $transaction->callback_url,
                    $transaction->created_at?->toDateTimeString(),
                ]);
            });

            fclose($handle);
        }, 'transactions-export-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
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

    /**
     * @return array<string, mixed>
     */
    protected function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'status' => ['nullable', 'string'],
            'callback_status' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filteredQuery(array $filters): Builder
    {
        return Transaction::query()
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
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay()));
    }
}
