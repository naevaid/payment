@extends('layouts.dashboard')

@section('title', 'Transactions')
@section('eyebrow', 'Operasional Payment')
@section('page-title', 'Transactions')
@section('page-subtitle', 'Monitoring transaksi global lintas project, dengan filter tenant, status pembayaran, dan status callback forwarding sesuai kebutuhan PRD.')

@section('content')
    <section class="panel">
        <div class="panel-body">
            <form method="GET" action="{{ route('dashboard.transactions.index') }}">
                <div class="filter-grid">
                    <div class="field">
                        <label for="search">Cari</label>
                        <input class="input" id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Gateway order, client order, Midtrans ID, atau App ID">
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
                        <label for="status">Status transaksi</label>
                        <input class="input" id="status" type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="pending / settlement / failed">
                    </div>

                    <div class="field">
                        <label for="callback_status">Status callback</label>
                        <input class="input" id="callback_status" type="text" name="callback_status" value="{{ $filters['callback_status'] ?? '' }}" placeholder="queued / success / failed">
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
                    <a class="button" href="{{ route('dashboard.transactions.export', request()->query()) }}">Export CSV</a>
                    <a class="button" href="{{ route('dashboard.transactions.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-body">
            <div class="panel-heading">
                <div>
                    <h2>Daftar transaksi</h2>
                    <p>List global seluruh transaksi tenant untuk audit charge, Midtrans status, dan health callback.</p>
                </div>
            </div>

            @if ($transactions->isEmpty())
                <div class="empty-state">Belum ada transaksi yang cocok dengan filter saat ini.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Project</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Callback</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $transaction)
                                @php
                                    $statusBadge = match ($transaction->status->value) {
                                        'settlement' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'failed', 'cancelled', 'expired', 'refunded' => 'badge-danger',
                                        default => 'badge-muted',
                                    };

                                    $callbackBadge = match ($transaction->callback_status->value) {
                                        'success' => 'badge-success',
                                        'queued', 'pending' => 'badge-warning',
                                        'failed' => 'badge-danger',
                                        default => 'badge-muted',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $transaction->gateway_order_id }}</strong>
                                        <br>
                                        <small>Client: {{ $transaction->client_order_id }}</small>
                                    </td>
                                    <td>
                                        {{ $transaction->project?->project_name ?? '-' }}
                                        <br>
                                        <small>{{ $transaction->project?->app_id ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <strong>Rp {{ number_format($transaction->amount, 0, ',', '.') }}</strong>
                                        <br>
                                        <small>{{ $transaction->currency }}</small>
                                    </td>
                                    <td><span class="badge {{ $statusBadge }}">{{ $transaction->status->value }}</span></td>
                                    <td><span class="badge {{ $callbackBadge }}">{{ $transaction->callback_status->value }}</span></td>
                                    <td>
                                        {{ $transaction->created_at?->format('d M Y H:i') }}
                                        <br>
                                        <small>{{ $transaction->created_at?->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="button" href="{{ route('dashboard.transactions.show', $transaction) }}">Detail</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrap">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
