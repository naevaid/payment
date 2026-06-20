@extends('layouts.dashboard')

@section('title', 'Detail Webhook Log')
@section('eyebrow', 'Operasional Payment')
@section('page-title', 'Detail webhook log')
@section('page-subtitle', 'Audit lengkap callback Midtrans: payload, headers, signature, dan status pemrosesan ke transaksi internal.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.webhook-logs.index') }}">Kembali ke list</a>
    @if ($log->transaction)
        <a class="button button-primary" href="{{ route('dashboard.transactions.show', $log->transaction) }}">Lihat transaksi</a>
    @endif
@endsection

@section('content')
    <section class="grid grid-4">
        <div class="stat-card">
            <span class="label">Order ID</span>
            <span class="value" style="font-size: 18px;">{{ $log->order_id ?? '-' }}</span>
            <div class="meta">Order reference dari Midtrans</div>
        </div>
        <div class="stat-card">
            <span class="label">Status</span>
            <span class="value" style="font-size: 18px;">{{ $log->transaction_status ?? '-' }}</span>
            <div class="meta">Status transaksi yang diterima di webhook</div>
        </div>
        <div class="stat-card">
            <span class="label">Signature</span>
            <span class="value" style="font-size: 18px;">{{ $log->is_signature_valid ? 'Valid' : 'Invalid' }}</span>
            <div class="meta">Verifikasi signature sebelum update status</div>
        </div>
        <div class="stat-card">
            <span class="label">Processing</span>
            <span class="value" style="font-size: 18px;">{{ $log->processing_status }}</span>
            <div class="meta">Status pemrosesan internal webhook</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <div class="panel-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>Project</strong>
                        <span>{{ $log->transaction?->project?->project_name ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Gateway order ID</strong>
                        <span>{{ $log->transaction?->gateway_order_id ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Midtrans transaction ID</strong>
                        <span>{{ $log->midtrans_transaction_id ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Received at</strong>
                        <span>{{ $log->received_at?->format('d M Y H:i:s') ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Processed at</strong>
                        <span>{{ $log->processed_at?->format('d M Y H:i:s') ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Signature key</strong>
                        <span>{{ $log->signature_key ? \Illuminate\Support\Str::limit($log->signature_key, 48) : '-' }}</span>
                    </div>
                </div>

                <div class="field" style="margin-top: 18px;">
                    <label>Notes</label>
                    <pre class="code-block">{{ $log->notes ?: 'Tidak ada catatan tambahan.' }}</pre>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Headers</h2>
                        <p>Header inbound yang ikut tercatat saat webhook diterima.</p>
                    </div>
                </div>
                <pre class="code-block">{{ json_encode($log->headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-body">
            <div class="panel-heading">
                <div>
                    <h2>Payload webhook</h2>
                    <p>Payload penuh yang dikirim Midtrans ke endpoint callback.</p>
                </div>
            </div>
            <pre class="code-block">{{ json_encode($log->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </section>
@endsection
