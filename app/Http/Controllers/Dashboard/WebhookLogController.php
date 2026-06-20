<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MidtransWebhookLog;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebhookLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $logs = $this->filteredQuery($filters)
            ->orderByDesc('received_at')
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.webhook-logs.index', [
            'logs' => $logs,
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_name', 'app_id']),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $rows = $this->filteredQuery($filters)
            ->orderByDesc('received_at')
            ->get();

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'order_id',
                'midtrans_transaction_id',
                'project_name',
                'project_app_id',
                'transaction_status',
                'is_signature_valid',
                'processing_status',
                'received_at',
                'processed_at',
            ]);

            $rows->each(function (MidtransWebhookLog $log) use ($handle): void {
                fputcsv($handle, [
                    $log->order_id,
                    $log->midtrans_transaction_id,
                    $log->transaction?->project?->project_name,
                    $log->transaction?->project?->app_id,
                    $log->transaction_status,
                    $log->is_signature_valid ? 'true' : 'false',
                    $log->processing_status,
                    $log->received_at?->toDateTimeString(),
                    $log->processed_at?->toDateTimeString(),
                ]);
            });

            fclose($handle);
        }, 'webhook-logs-export-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(MidtransWebhookLog $webhookLog): View
    {
        $webhookLog->load('transaction.project');

        return view('dashboard.webhook-logs.show', [
            'log' => $webhookLog,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string'],
            'app_id' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'signature' => ['nullable', 'in:valid,invalid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filteredQuery(array $filters): Builder
    {
        return MidtransWebhookLog::query()
            ->with('transaction.project')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('order_id', 'like', "%{$search}%")
                        ->orWhere('midtrans_transaction_id', 'like', "%{$search}%")
                        ->orWhere('transaction_status', 'like', "%{$search}%")
                        ->orWhereHas('transaction.project', function ($projectQuery) use ($search): void {
                            $projectQuery->where('app_id', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['app_id'] ?? null, function ($query, string $appId): void {
                $query->whereHas('transaction.project', function ($projectQuery) use ($appId): void {
                    $projectQuery->where('app_id', 'like', "%{$appId}%");
                });
            })
            ->when($filters['project_id'] ?? null, function ($query, int $projectId): void {
                $query->whereHas('transaction', fn ($transactionQuery) => $transactionQuery->where('project_id', $projectId));
            })
            ->when(($filters['signature'] ?? null) === 'valid', fn ($query) => $query->where('is_signature_valid', true))
            ->when(($filters['signature'] ?? null) === 'invalid', fn ($query) => $query->where('is_signature_valid', false))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->where('received_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->where('received_at', '<=', Carbon::parse($dateTo)->endOfDay()));
    }
}
