@extends('layouts.dashboard')

@section('title', 'Webhook Logs')
@section('eyebrow', 'Operasional Payment')
@section('page-title', 'Webhook Logs')
@section('page-subtitle', 'Audit seluruh callback Midtrans yang masuk, termasuk validitas signature, status proses, dan relasi ke transaksi tenant.')

@section('content')
    <section class="panel">
        <div class="panel-body">
            <form method="GET" action="{{ route('dashboard.webhook-logs.index') }}">
                <div class="filter-grid">
                    <div class="field">
                        <label for="search">Cari</label>
                        <input class="input" id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Order ID, Midtrans ID, status, atau App ID">
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
                        <label for="signature">Signature</label>
                        <select class="select" id="signature" name="signature">
                            <option value="">Semua</option>
                            <option value="valid" @selected(($filters['signature'] ?? '') === 'valid')>Valid</option>
                            <option value="invalid" @selected(($filters['signature'] ?? '') === 'invalid')>Invalid</option>
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
                    <a class="button" href="{{ route('dashboard.webhook-logs.export', request()->query()) }}">Export CSV</a>
                    <a class="button" href="{{ route('dashboard.webhook-logs.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-body">
            @if ($logs->isEmpty())
                <div class="empty-state">Belum ada webhook log yang cocok.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Signature</th>
                                <th>Processing</th>
                                <th>Received</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td>
                                        <strong>{{ $log->order_id ?? '-' }}</strong>
                                        <br>
                                        <small>{{ $log->midtrans_transaction_id ?? '-' }}</small>
                                    </td>
                                    <td>
                                        {{ $log->transaction?->project?->project_name ?? '-' }}
                                        <br>
                                        <small>{{ $log->transaction?->project?->app_id ?? '-' }}</small>
                                    </td>
                                    <td><span class="badge badge-primary">{{ $log->transaction_status ?? '-' }}</span></td>
                                    <td>
                                        <span class="badge {{ $log->is_signature_valid ? 'badge-success' : 'badge-danger' }}">
                                            {{ $log->is_signature_valid ? 'Valid' : 'Invalid' }}
                                        </span>
                                    </td>
                                    <td><span class="badge badge-muted">{{ $log->processing_status }}</span></td>
                                    <td>{{ $log->received_at?->format('d M Y H:i:s') }}</td>
                                    <td><a class="button" href="{{ route('dashboard.webhook-logs.show', $log) }}">Detail</a></td>
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
