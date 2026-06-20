<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CallbackForwardingLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CallbackForwardingLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'result' => ['nullable', 'in:success,failed'],
        ]);

        $logs = CallbackForwardingLog::query()
            ->with(['project', 'transaction'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('callback_url', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%");
                });
            })
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when(($filters['result'] ?? null) === 'success', fn ($query) => $query->where('success', true))
            ->when(($filters['result'] ?? null) === 'failed', fn ($query) => $query->where('success', false))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.callback-logs.index', [
            'logs' => $logs,
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_name']),
            'filters' => $filters,
        ]);
    }

    public function show(CallbackForwardingLog $callbackLog): View
    {
        $callbackLog->load(['project', 'transaction']);

        return view('dashboard.callback-logs.show', [
            'log' => $callbackLog,
        ]);
    }
}
