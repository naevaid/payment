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
    @php
        $maskSecret = static function (?string $value): string {
            if (blank($value)) {
                return '-';
            }

            $start = substr($value, 0, min(6, strlen($value)));
            $end = strlen($value) > 10 ? substr($value, -4) : '';
            $maskedLength = max(strlen($value) - strlen($start) - strlen($end), 6);

            return $start.str_repeat('*', $maskedLength).$end;
        };
    @endphp

    @if (session('generated_credentials'))
        <section class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Kredensial baru</h2>
                        <p>Nilai ini hanya ditampilkan sekali setelah regenerasi. Simpan segera ke client app tenant yang relevan.</p>
                    </div>
                </div>

                <div class="detail-grid">
                    @if (session('generated_credentials.app_id'))
                        <div class="detail-item">
                            <strong>App ID baru</strong>
                            <span><code>{{ session('generated_credentials.app_id') }}</code></span>
                        </div>
                    @endif

                    @if (session('generated_credentials.secret_key'))
                        <div class="detail-item">
                            <strong>Secret key baru</strong>
                            <div class="secret-display">
                                <code
                                    class="secret-value"
                                    data-secret-value="generated-secret-key"
                                    data-state="masked"
                                    data-masked="{{ $maskSecret(session('generated_credentials.secret_key')) }}"
                                    data-full="{{ session('generated_credentials.secret_key') }}"
                                >{{ $maskSecret(session('generated_credentials.secret_key')) }}</code>

                                <div class="secret-actions">
                                    <button
                                        class="icon-button"
                                        type="button"
                                        data-secret-toggle="generated-secret-key"
                                        aria-label="Lihat secret key"
                                        title="Lihat secret key"
                                    >
                                        <svg data-icon-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg class="is-hidden" data-icon-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M3 3l18 18"></path>
                                            <path d="M10.6 10.7a3 3 0 0 0 4.2 4.2"></path>
                                            <path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a18.1 18.1 0 0 1-4 4.7"></path>
                                            <path d="M6.6 6.7C3.8 8.3 2 12 2 12a18.7 18.7 0 0 0 7.4 5.3"></path>
                                        </svg>
                                        <span class="sr-only">Lihat secret key</span>
                                    </button>

                                    <button
                                        class="icon-button"
                                        type="button"
                                        data-secret-copy="generated-secret-key"
                                        aria-label="Salin secret key"
                                        title="Salin secret key"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                        <span class="sr-only" data-copy-feedback>Salin secret key</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

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

                    <div class="button-row">
                        <form class="inline-form" method="POST" action="{{ route('dashboard.projects.regenerate-app-id', $project) }}" onsubmit="return confirm('Regenerasi App ID project ini? Client app perlu diperbarui setelahnya.');">
                            @csrf
                            <button class="button" type="submit">Regenerate App ID</button>
                        </form>

                        <form class="inline-form" method="POST" action="{{ route('dashboard.projects.regenerate-secret-key', $project) }}" onsubmit="return confirm('Regenerasi secret key project ini? Client app perlu diperbarui setelahnya.');">
                            @csrf
                            <button class="button button-primary" type="submit">Regenerate Secret Key</button>
                        </form>
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
                        <div class="secret-display">
                            <code
                                class="secret-value"
                                data-secret-value="project-secret-key"
                                data-state="masked"
                                data-masked="{{ $maskSecret($project->secret_key) }}"
                                data-full="{{ $project->secret_key }}"
                            >{{ $maskSecret($project->secret_key) }}</code>

                            <div class="secret-actions">
                                <button
                                    class="icon-button"
                                    type="button"
                                    data-secret-toggle="project-secret-key"
                                    aria-label="Lihat secret key"
                                    title="Lihat secret key"
                                >
                                    <svg data-icon-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="is-hidden" data-icon-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 3l18 18"></path>
                                        <path d="M10.6 10.7a3 3 0 0 0 4.2 4.2"></path>
                                        <path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a18.1 18.1 0 0 1-4 4.7"></path>
                                        <path d="M6.6 6.7C3.8 8.3 2 12 2 12a18.7 18.7 0 0 0 7.4 5.3"></path>
                                    </svg>
                                    <span class="sr-only">Lihat secret key</span>
                                </button>

                                <button
                                    class="icon-button"
                                    type="button"
                                    data-secret-copy="project-secret-key"
                                    aria-label="Salin secret key"
                                    title="Salin secret key"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    <span class="sr-only" data-copy-feedback>Salin secret key</span>
                                </button>
                            </div>
                        </div>
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
