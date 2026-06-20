<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CallbackStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ForwardTransactionCallback;
use App\Models\CallbackForwardingLog;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallbackForwardingLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $logs = $this->filteredQuery($filters)
            ->orderByDesc('dispatched_at')
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.callback-logs.index', [
            'logs' => $logs,
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_name', 'app_id']),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $rows = $this->filteredQuery($filters)
            ->orderByDesc('dispatched_at')
            ->get();

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'project_name',
                'project_app_id',
                'gateway_order_id',
                'callback_url',
                'attempt',
                'event_type',
                'success',
                'response_status_code',
                'error_message',
                'dispatched_at',
                'next_retry_at',
            ]);

            $rows->each(function (CallbackForwardingLog $log) use ($handle): void {
                fputcsv($handle, [
                    $log->project?->project_name,
                    $log->project?->app_id,
                    $log->transaction?->gateway_order_id,
                    $log->callback_url,
                    $log->attempt,
                    $log->event_type,
                    $log->success ? 'true' : 'false',
                    $log->response_status_code,
                    $log->error_message,
                    $log->dispatched_at?->toDateTimeString(),
                    $log->next_retry_at?->toDateTimeString(),
                ]);
            });

            fclose($handle);
        }, 'callback-logs-export-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(CallbackForwardingLog $callbackLog): View
    {
        $callbackLog->load(['project', 'transaction']);

        return view('dashboard.callback-logs.show', [
            'log' => $callbackLog,
        ]);
    }

    public function retry(CallbackForwardingLog $callbackLog): RedirectResponse
    {
        $callbackLog->loadMissing(['transaction.project', 'project']);

        $transaction = $callbackLog->transaction;

        if (! $transaction) {
            return back()->withErrors([
                'callback_log' => 'Retry manual tidak bisa dijalankan karena transaksi terkait tidak ditemukan.',
            ]);
        }

        $callbackUrl = $transaction->callback_url ?: $transaction->project?->default_callback_url;

        if (blank($callbackUrl)) {
            return back()->withErrors([
                'callback_log' => 'Retry manual tidak bisa dijalankan karena callback URL belum dikonfigurasi.',
            ]);
        }

        $transaction->forceFill([
            'callback_status' => CallbackStatus::Queued,
        ])->save();

        ForwardTransactionCallback::dispatch($transaction->id)
            ->onQueue((string) config('payment.callback.queue'));

        return redirect()
            ->route('dashboard.callback-logs.show', $callbackLog)
            ->with('status', 'Retry manual callback sudah dijadwalkan untuk transaksi ini.');
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
            'result' => ['nullable', 'in:success,failed'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filteredQuery(array $filters): Builder
    {
        return CallbackForwardingLog::query()
            ->with(['project', 'transaction'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('callback_url', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%")
                        ->orWhereHas('project', function ($projectQuery) use ($search): void {
                            $projectQuery->where('app_id', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['app_id'] ?? null, function ($query, string $appId): void {
                $query->whereHas('project', function ($projectQuery) use ($appId): void {
                    $projectQuery->where('app_id', 'like', "%{$appId}%");
                });
            })
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when(($filters['result'] ?? null) === 'success', fn ($query) => $query->where('success', true))
            ->when(($filters['result'] ?? null) === 'failed', fn ($query) => $query->where('success', false))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->where('dispatched_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->where('dispatched_at', '<=', Carbon::parse($dateTo)->endOfDay()));
    }
}
