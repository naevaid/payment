@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('eyebrow', 'Overview')
@section('page-title', 'Dashboard utama payment service')
@section('page-subtitle', 'Ringkasan ini mengikuti kebutuhan PRD: monitoring transaksi global, tenant/project, webhook Midtrans, status forwarding callback, dan area operasi yang akan menyusul.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.transactions.index') }}">Buka Transactions</a>
    <a class="button button-primary" href="{{ route('dashboard.projects.create') }}">Tambah Project</a>
@endsection

@section('content')
    <section class="grid grid-4">
        <div class="stat-card">
            <span class="label">Projects</span>
            <span class="value">{{ number_format($stats['projects']) }}</span>
            <div class="meta">{{ number_format($stats['active_projects']) }} project aktif siap menerima callback</div>
        </div>

        <div class="stat-card">
            <span class="label">Transactions</span>
            <span class="value">{{ number_format($stats['transactions']) }}</span>
            <div class="meta">{{ number_format($stats['settlement_transactions']) }} settlement, {{ number_format($stats['pending_transactions']) }} pending</div>
        </div>

        <div class="stat-card">
            <span class="label">Webhook Logs</span>
            <span class="value">{{ number_format($stats['webhook_logs']) }}</span>
            <div class="meta">Audit webhook Midtrans yang diterima service</div>
        </div>

        <div class="stat-card">
            <span class="label">Callback Logs</span>
            <span class="value">{{ number_format($stats['forwarding_logs']) }}</span>
            <div class="meta">{{ number_format($stats['callback_success']) }} success, {{ number_format($stats['callback_failed']) }} failed</div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-body">
            <div class="panel-heading">
                <div>
                    <h2>Peta menu sesuai PRD</h2>
                    <p>Sidebar dashboard dibentuk dari kebutuhan operasional yang disebut di PRD dan dipisahkan antara menu aktif saat ini dan roadmap berikutnya.</p>
                </div>
            </div>

            <div class="grid grid-3">
                <div class="summary-card">
                    <strong>Menu aktif sekarang</strong>
                    <span>Dashboard overview, Projects / Tenants, Transactions, Webhook Logs, dan Callback Logs.</span>
                </div>

                <div class="summary-card">
                    <strong>Menu next sesuai PRD</strong>
                    <span>Retry Manual Callback, reporting lintas tenant, queue monitoring, dan health ops untuk reliability.</span>
                </div>

                <div class="summary-card">
                    <strong>Tujuan operasional</strong>
                    <span>Memusatkan monitoring charge, webhook Midtrans, dan forwarding callback untuk semua project internal Naeva.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="split">
        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Transaksi terbaru</h2>
                        <p>Sampel terakhir dari seluruh tenant untuk monitoring cepat.</p>
                    </div>

                    <a class="button" href="{{ route('dashboard.transactions.index') }}">Lihat semua</a>
                </div>

                @if ($recentTransactions->isEmpty())
                    <div class="empty-state">Belum ada transaksi tersimpan.</div>
                @else
                    <div class="list">
                        @foreach ($recentTransactions as $transaction)
                            <div class="list-item">
                                <strong>{{ $transaction->gateway_order_id }}</strong>
                                <span>{{ $transaction->project?->project_name ?? 'Project tidak ditemukan' }} | {{ $transaction->status->value }} | Rp {{ number_format($transaction->amount, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Fokus reliability</h2>
                        <p>Ringkasan area yang disebut eksplisit di PRD untuk tahap lanjutan.</p>
                    </div>
                </div>

                <div class="list">
                    <div class="list-item">
                        <strong>Retry manual callback</strong>
                        <span>Perlu action admin untuk mengirim ulang callback jika endpoint project tujuan down.</span>
                    </div>
                    <div class="list-item">
                        <strong>Redis queue worker</strong>
                        <span>Disiapkan agar forwarding callback async lebih stabil dibanding mode sinkron.</span>
                    </div>
                    <div class="list-item">
                        <strong>Monitoring lintas tenant</strong>
                        <span>Filter per project dan status forwarding harus menjadi alat operasi utama tim internal.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Webhook terbaru</h2>
                        <p>Log inbound Midtrans yang terakhir diterima.</p>
                    </div>
                    <a class="button" href="{{ route('dashboard.webhook-logs.index') }}">Lihat semua</a>
                </div>

                @if ($recentWebhookLogs->isEmpty())
                    <div class="empty-state">Belum ada webhook log.</div>
                @else
                    <div class="list">
                        @foreach ($recentWebhookLogs as $log)
                            <div class="list-item">
                                <strong>{{ $log->order_id ?? '-' }}</strong>
                                <span>{{ $log->transaction_status ?? '-' }} | signature {{ $log->is_signature_valid ? 'valid' : 'invalid' }} | {{ $log->received_at?->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Callback forwarding terbaru</h2>
                        <p>Log outbound callback ke project asal.</p>
                    </div>
                    <a class="button" href="{{ route('dashboard.callback-logs.index') }}">Lihat semua</a>
                </div>

                @if ($recentCallbackLogs->isEmpty())
                    <div class="empty-state">Belum ada callback forwarding log.</div>
                @else
                    <div class="list">
                        @foreach ($recentCallbackLogs as $log)
                            <div class="list-item">
                                <strong>{{ $log->project?->project_name ?? '-' }}</strong>
                                <span>Attempt {{ $log->attempt }} | {{ $log->success ? 'success' : 'failed' }} | {{ $log->dispatched_at?->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
