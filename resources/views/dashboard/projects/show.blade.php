@extends('layouts.dashboard')

@section('title', 'Detail Project')
@section('eyebrow', 'Projects / Tenants')
@section('page-title', $project->project_name)
@section('page-subtitle', 'Detail tenant untuk melihat identitas integrasi, callback URL, metadata, dan aktivitas transaksi terbaru.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.projects.index') }}">Kembali ke list</a>
    <a class="button button-primary" href="{{ route('dashboard.projects.edit', $project) }}">Edit project</a>
@endsection

@section('content')
    <section class="grid grid-4">
        <div class="stat-card">
            <span class="label">App ID</span>
            <span class="value" style="font-size: 20px;">{{ $project->app_id }}</span>
            <div class="meta">Dipakai pada header autentikasi API payment</div>
        </div>

        <div class="stat-card">
            <span class="label">Status</span>
            <span class="value" style="font-size: 20px;">{{ $project->is_active ? 'Active' : 'Inactive' }}</span>
            <div class="meta">Tenant {{ $project->is_active ? 'bisa' : 'belum bisa' }} melakukan charge</div>
        </div>

        <div class="stat-card">
            <span class="label">Transactions</span>
            <span class="value">{{ number_format($project->transactions_count) }}</span>
            <div class="meta">Histori charge yang tersimpan untuk project ini</div>
        </div>

        <div class="stat-card">
            <span class="label">Callback Logs</span>
            <span class="value">{{ number_format($project->callback_forwarding_logs_count) }}</span>
            <div class="meta">Log forwarding callback ke endpoint client app</div>
        </div>
    </section>

    <section class="split">
        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Identitas integrasi</h2>
                        <p>Informasi inti tenant sesuai desain multi-tenant di PRD.</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>Nama project</strong>
                        <span>{{ $project->project_name }}</span>
                    </div>

                    <div class="detail-item">
                        <strong>App ID</strong>
                        <span><code>{{ $project->app_id }}</code></span>
                    </div>

                    <div class="detail-item">
                        <strong>Secret key</strong>
                        <span>{{ str_repeat('*', 16) }} (tersimpan terenkripsi)</span>
                    </div>

                    <div class="detail-item">
                        <strong>Default callback URL</strong>
                        @if ($project->default_callback_url)
                            <a href="{{ $project->default_callback_url }}" target="_blank" rel="noreferrer">{{ $project->default_callback_url }}</a>
                        @else
                            <span class="muted">Belum diisi</span>
                        @endif
                    </div>

                    <div class="detail-item">
                        <strong>Dibuat</strong>
                        <span>{{ $project->created_at?->format('d M Y H:i') }}</span>
                    </div>

                    <div class="detail-item">
                        <strong>Diperbarui</strong>
                        <span>{{ $project->updated_at?->format('d M Y H:i') }}</span>
                    </div>
                </div>

                <div class="field" style="margin-top: 18px;">
                    <label>Metadata JSON</label>
                    <pre class="code-block">{{ json_encode($project->metadata ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Aktivitas terbaru</h2>
                        <p>Shortcut cepat ke transaksi dan callback log tenant ini.</p>
                    </div>
                </div>

                <div class="list">
                    @forelse ($project->transactions as $transaction)
                        <div class="list-item">
                            <strong>{{ $transaction->gateway_order_id }}</strong>
                            <span>{{ $transaction->status->value }} | Rp {{ number_format($transaction->amount, 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada transaksi untuk tenant ini.</div>
                    @endforelse
                </div>

                <div class="list" style="margin-top: 18px;">
                    @forelse ($project->callbackForwardingLogs as $log)
                        <div class="list-item">
                            <strong>Attempt {{ $log->attempt }}</strong>
                            <span>{{ $log->success ? 'success' : 'failed' }} | {{ $log->callback_url }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada callback forwarding log untuk tenant ini.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
