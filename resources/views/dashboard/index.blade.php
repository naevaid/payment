@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('eyebrow', 'Overview')
@section('page-title', 'Dashboard utama payment service')
@section('page-subtitle', 'Ringkasan ini mengikuti kebutuhan PRD: monitoring transaksi global, tenant/project, webhook Midtrans, status forwarding callback, serta health operasional queue callback yang sudah aktif.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.transactions.index') }}">Buka Transactions</a>
    <a class="button" href="{{ route('dashboard.callback-logs.index', ['result' => 'failed']) }}">Callback bermasalah</a>
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
                    <span>Dashboard overview, Projects / Tenants, Transactions, Webhook Logs, Callback Logs, retry manual callback, dan health queue callback.</span>
                </div>

                <div class="summary-card">
                    <strong>Menu next sesuai PRD</strong>
                    <span>Reporting owner-level, analytics nominal settlement, dan penyempurnaan dokumentasi integrasi final.</span>
                </div>

                <div class="summary-card">
                    <strong>Tujuan operasional</strong>
                    <span>Memusatkan monitoring charge, webhook Midtrans, dan forwarding callback untuk semua project internal Naeva.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-body">
            <div class="panel-heading">
                <div>
                    <h2>Queue & Callback Health Ops</h2>
                    <p>Visibilitas operasional untuk backlog callback, retry terjadwal, dan kesiapan queue forwarding async.</p>
                </div>

                <a class="button" href="{{ route('dashboard.callback-logs.index') }}">Buka callback logs</a>
            </div>

            <div class="grid grid-4">
                <div class="stat-card">
                    <span class="label">Queue connection</span>
                    <span class="value">{{ strtoupper($callbackHealth['queue_connection']) }}</span>
                    <div class="meta">{{ $callbackHealth['async_mode'] ? 'Async callback forwarding aktif' : 'Masih berjalan sinkron' }}</div>
                </div>

                <div class="stat-card">
                    <span class="label">Callback queue</span>
                    <span class="value" style="font-size: 22px;">{{ $callbackHealth['callback_queue'] }}</span>
                    <div class="meta">Max {{ $callbackHealth['max_attempts'] }} attempt | backoff {{ implode(', ', $callbackHealth['backoff']) }} detik</div>
                </div>

                <div class="stat-card">
                    <span class="label">Callback backlog</span>
                    <span class="value">{{ number_format($callbackHealth['backlog_transactions']) }}</span>
                    <div class="meta">{{ number_format($stats['queued_callbacks']) }} queued, {{ number_format($stats['callback_failed']) }} failed, {{ number_format($stats['callback_skipped']) }} skipped</div>
                </div>

                <div class="stat-card">
                    <span class="label">Retry terjadwal</span>
                    <span class="value">{{ number_format($callbackHealth['retry_scheduled']) }}</span>
                    <div class="meta">Retry berikutnya {{ $callbackHealth['next_retry_at']?->diffForHumans() ?? 'belum ada jadwal' }}</div>
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
                        <h2>Aksi callback prioritas</h2>
                        <p>Log callback yang paling perlu perhatian untuk retry manual atau pengecekan konfigurasi project.</p>
                    </div>

                    <a class="button" href="{{ route('dashboard.callback-logs.index', ['result' => 'failed']) }}">Lihat yang gagal</a>
                </div>

                @if ($callbackHealthLogs->isEmpty())
                    <div class="empty-state">Belum ada callback queued, failed, atau skipped yang perlu perhatian.</div>
                @else
                    <div class="list">
                        @foreach ($callbackHealthLogs as $entry)
                            @php
                                $log = $entry['log'];
                                $stateBadge = match ($entry['callback_state']) {
                                    'success' => 'badge-success',
                                    'queued', 'pending' => 'badge-warning',
                                    'failed', 'skipped' => 'badge-danger',
                                    default => 'badge-muted',
                                };
                            @endphp
                            <div class="list-item">
                                <strong>{{ $log->transaction?->gateway_order_id ?? 'Callback tanpa transaksi' }}</strong>
                                <span>{{ $log->project?->project_name ?? '-' }} | {{ $log->project?->app_id ?? '-' }}</span>
                                <span>
                                    <span class="badge {{ $stateBadge }}">{{ $entry['callback_state'] }}</span>
                                    Attempt {{ $log->attempt }}/{{ $callbackHealth['max_attempts'] }} | Retry tersisa {{ $entry['retries_remaining'] }}
                                </span>
                                <span>{{ $log->error_message ?: 'Menunggu hasil callback berikutnya.' }}</span>
                                <span>Next retry: {{ $log->next_retry_at?->format('d M Y H:i:s') ?? 'tidak terjadwal' }}</span>
                                <div class="table-actions" style="margin-top: 12px;">
                                    <a class="button" href="{{ route('dashboard.callback-logs.show', $log) }}">Detail log</a>
                                    @if ($entry['is_retryable'])
                                        <form class="inline-form" method="POST" action="{{ route('dashboard.callback-logs.retry', $log) }}">
                                            @csrf
                                            <button class="button button-primary" type="submit">Retry Manual Callback</button>
                                        </form>
                                    @elseif ($log->transaction)
                                        <a class="button" href="{{ route('dashboard.transactions.show', $log->transaction) }}">Lihat transaksi</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
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
