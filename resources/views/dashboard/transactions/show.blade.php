@extends('layouts.dashboard')

@section('title', 'Detail Transaksi')
@section('eyebrow', 'Operasional Payment')
@section('page-title', $transaction->gateway_order_id)
@section('page-subtitle', 'Detail transaksi untuk audit order, respons Midtrans, status callback forwarding, dan payload pendukung.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.transactions.index') }}">Kembali ke list</a>
@endsection

@section('content')
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

    <section class="grid grid-4">
        <div class="stat-card">
            <span class="label">Amount</span>
            <span class="value">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</span>
            <div class="meta">{{ $transaction->currency }}</div>
        </div>

        <div class="stat-card">
            <span class="label">Status</span>
            <span class="value" style="font-size: 20px;"><span class="badge {{ $statusBadge }}">{{ $transaction->status->value }}</span></span>
            <div class="meta">Status transaksi internal hasil mapping Midtrans</div>
        </div>

        <div class="stat-card">
            <span class="label">Callback</span>
            <span class="value" style="font-size: 20px;"><span class="badge {{ $callbackBadge }}">{{ $transaction->callback_status->value }}</span></span>
            <div class="meta">Status forwarding callback ke client app</div>
        </div>

        <div class="stat-card">
            <span class="label">Project</span>
            <span class="value" style="font-size: 20px;">{{ $transaction->project?->project_name ?? '-' }}</span>
            <div class="meta">{{ $transaction->project?->app_id ?? '-' }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Informasi inti</h2>
                        <p>Data utama yang terbentuk saat charge API dipanggil dan saat webhook diproses.</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>Gateway order ID</strong>
                        <span>{{ $transaction->gateway_order_id }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Client order ID</strong>
                        <span>{{ $transaction->client_order_id }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Midtrans transaction ID</strong>
                        <span>{{ $transaction->midtrans_transaction_id ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Payment type</strong>
                        <span>{{ $transaction->payment_type ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Callback URL</strong>
                        <span>{{ $transaction->callback_url ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Snap redirect URL</strong>
                        @if ($transaction->snap_redirect_url)
                            <a href="{{ $transaction->snap_redirect_url }}" target="_blank" rel="noreferrer">{{ $transaction->snap_redirect_url }}</a>
                        @else
                            <span class="muted">Belum ada</span>
                        @endif
                    </div>
                    <div class="detail-item">
                        <strong>Paid at</strong>
                        <span>{{ $transaction->paid_at?->format('d M Y H:i:s') ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Last webhook at</strong>
                        <span>{{ $transaction->last_webhook_at?->format('d M Y H:i:s') ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Aktivitas terkait</h2>
                        <p>Log inbound webhook dan outbound callback forwarding untuk transaksi ini.</p>
                    </div>
                </div>

                <div class="list">
                    @forelse ($transaction->webhookLogs as $log)
                        <div class="list-item">
                            <strong>Webhook {{ $log->transaction_status ?? '-' }}</strong>
                            <span>Signature {{ $log->is_signature_valid ? 'valid' : 'invalid' }} | {{ $log->received_at?->format('d M Y H:i:s') }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada webhook log untuk transaksi ini.</div>
                    @endforelse
                </div>

                <div class="list" style="margin-top: 18px;">
                    @forelse ($transaction->callbackForwardingLogs as $log)
                        <div class="list-item">
                            <strong>Callback attempt {{ $log->attempt }}</strong>
                            <span>{{ $log->success ? 'success' : 'failed' }} | status HTTP {{ $log->response_status_code ?? '-' }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada callback forwarding log untuk transaksi ini.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Customer details</h2>
                        <p>Payload customer yang disimpan saat create charge.</p>
                    </div>
                </div>
                <pre class="code-block">{{ json_encode($transaction->customer_details ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Item details</h2>
                        <p>Payload item yang dikirimkan ke Midtrans.</p>
                    </div>
                </div>
                <pre class="code-block">{{ json_encode($transaction->item_details ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Metadata</h2>
                        <p>Metadata bebas dari client app.</p>
                    </div>
                </div>
                <pre class="code-block">{{ json_encode($transaction->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Midtrans payload</h2>
                        <p>Payload terakhir yang relevan dari Midtrans/Snap.</p>
                    </div>
                </div>
                <pre class="code-block">{{ json_encode($transaction->midtrans_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </section>
@endsection
