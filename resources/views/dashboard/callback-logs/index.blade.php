@extends('layouts.dashboard')

@section('title', 'Callback Logs')
@section('eyebrow', 'Operasional Payment')
@section('page-title', 'Callback Forwarding Logs')
@section('page-subtitle', 'Monitoring log outbound callback ke project asal, termasuk status success/failed, attempt, response code, dan error untuk kebutuhan retry operasional.')

@section('content')
    <section class="panel">
        <div class="panel-body">
            <form method="GET" action="{{ route('dashboard.callback-logs.index') }}">
                <div class="filter-grid">
                    <div class="field">
                        <label for="search">Cari</label>
                        <input class="input" id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Callback URL, event type, error, atau App ID">
                    </div>

                    <div class="field">
                        <label for="app_id">App ID</label>
                        <input class="input" id="app_id" type="text" name="app_id" value="{{ $filters['app_id'] ?? '' }}" placeholder="APP-OPS / project_a_prod">
                    </div>

                    <div class="field">
                        <label for="project_id">Project</label>
                        <select class="select" id="project_id" name="project_id">
                            <option value="">Semua project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->project_name }} ({{ $project->app_id }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="result">Result</label>
                        <select class="select" id="result" name="result">
                            <option value="">Semua</option>
                            <option value="success" @selected(($filters['result'] ?? '') === 'success')>Success</option>
                            <option value="failed" @selected(($filters['result'] ?? '') === 'failed')>Failed</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="date_from">Tanggal dari</label>
                        <input class="input" id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label for="date_to">Tanggal sampai</label>
                        <input class="input" id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                </div>

                <div class="button-row">
                    <button class="button button-primary" type="submit">Terapkan filter</button>
                    <a class="button" href="{{ route('dashboard.callback-logs.export', request()->query()) }}">Export CSV</a>
                    <a class="button" href="{{ route('dashboard.callback-logs.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-body">
            @if ($logs->isEmpty())
                <div class="empty-state">Belum ada callback forwarding log yang cocok.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Attempt</th>
                                <th>Event</th>
                                <th>Result</th>
                                <th>HTTP</th>
                                <th>Dispatched</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td>
                                        <strong>{{ $log->project?->project_name ?? '-' }}</strong>
                                        <br>
                                        <small>{{ $log->project?->app_id ?? '-' }}</small>
                                        <br>
                                        <small>{{ $log->callback_url }}</small>
                                    </td>
                                    <td>{{ $log->attempt }}</td>
                                    <td><span class="badge badge-primary">{{ $log->event_type }}</span></td>
                                    <td>
                                        <span class="badge {{ $log->success ? 'badge-success' : 'badge-danger' }}">
                                            {{ $log->success ? 'Success' : 'Failed' }}
                                        </span>
                                    </td>
                                    <td>{{ $log->response_status_code ?? '-' }}</td>
                                    <td>{{ $log->dispatched_at?->format('d M Y H:i:s') }}</td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="button" href="{{ route('dashboard.callback-logs.show', $log) }}">Detail</a>

                                            @if (! $log->success && $log->transaction)
                                                <form class="inline-form" method="POST" action="{{ route('dashboard.callback-logs.retry', $log) }}">
                                                    @csrf
                                                    <button class="button button-primary" type="submit">Retry</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrap">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
