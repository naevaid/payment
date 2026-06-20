@extends('layouts.dashboard')

@section('title', 'Detail Callback Log')
@section('eyebrow', 'Operasional Payment')
@section('page-title', 'Detail callback forwarding log')
@section('page-subtitle', 'Audit outbound callback ke project asal, termasuk request payload, header, response, dan error untuk kebutuhan troubleshooting dan retry.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.callback-logs.index') }}">Kembali ke list</a>
    @if ($log->transaction)
        <a class="button button-primary" href="{{ route('dashboard.transactions.show', $log->transaction) }}">Lihat transaksi</a>
    @endif
@endsection

@section('content')
    <section class="grid grid-4">
        <div class="stat-card">
            <span class="label">Project</span>
            <span class="value" style="font-size: 18px;">{{ $log->project?->project_name ?? '-' }}</span>
            <div class="meta">{{ $log->project?->app_id ?? '-' }}</div>
        </div>
        <div class="stat-card">
            <span class="label">Attempt</span>
            <span class="value">{{ $log->attempt }}</span>
            <div class="meta">{{ $log->event_type }}</div>
        </div>
        <div class="stat-card">
            <span class="label">Result</span>
            <span class="value" style="font-size: 18px;">{{ $log->success ? 'Success' : 'Failed' }}</span>
            <div class="meta">HTTP {{ $log->response_status_code ?? '-' }}</div>
        </div>
        <div class="stat-card">
            <span class="label">Next Retry</span>
            <span class="value" style="font-size: 18px;">{{ $log->next_retry_at?->format('d M Y H:i') ?? '-' }}</span>
            <div class="meta">Kosong jika tidak ada retry terjadwal</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <div class="panel-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>Callback URL</strong>
                        <span>{{ $log->callback_url }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Dispatched at</strong>
                        <span>{{ $log->dispatched_at?->format('d M Y H:i:s') ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Responded at</strong>
                        <span>{{ $log->responded_at?->format('d M Y H:i:s') ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Response status</strong>
                        <span>{{ $log->response_status_code ?? '-' }}</span>
                    </div>
                </div>

                <div class="field" style="margin-top: 18px;">
                    <label>Error message</label>
                    <pre class="code-block">{{ $log->error_message ?: 'Tidak ada error message.' }}</pre>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Response body</h2>
                        <p>Body respons dari endpoint callback tujuan.</p>
                    </div>
                </div>
                <pre class="code-block">{{ $log->response_body ?: 'Tidak ada response body.' }}</pre>
            </div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Payload callback</h2>
                        <p>Payload yang dikirim ke client app.</p>
                    </div>
                </div>
                <pre class="code-block">{{ json_encode($log->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Request headers</h2>
                        <p>Header outbound yang dikirim saat forwarding callback.</p>
                    </div>
                </div>
                <pre class="code-block">{{ json_encode($log->request_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </section>
@endsection
