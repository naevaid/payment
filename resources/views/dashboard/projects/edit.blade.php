@extends('layouts.dashboard')

@section('title', 'Edit Project')
@section('eyebrow', 'Projects / Tenants')
@section('page-title', 'Edit project / tenant')
@section('page-subtitle', 'Perbarui identitas tenant, callback URL, metadata, dan status aktif. Secret key hanya diganti bila Anda mengisi nilai baru.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.projects.show', $project) }}">Lihat detail</a>
@endsection

@section('content')
    @if (session('callback_test'))
        @php($callbackTest = session('callback_test'))
        <section class="panel">
            <div class="panel-body">
                <div class="panel-heading">
                    <div>
                        <h2>Hasil test callback URL</h2>
                        <p>Request uji dikirim langsung dari `payment.naeva.id` ke endpoint yang Anda isi pada form ini.</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>Status</strong>
                        <span>{{ $callbackTest['success'] ? 'Berhasil' : 'Gagal' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Callback URL</strong>
                        <span><code>{{ $callbackTest['callback_url'] }}</code></span>
                    </div>
                    <div class="detail-item">
                        <strong>App ID</strong>
                        <span><code>{{ $callbackTest['app_id'] }}</code></span>
                    </div>
                    <div class="detail-item">
                        <strong>Event</strong>
                        <span><code>{{ $callbackTest['event_type'] }}</code></span>
                    </div>
                    <div class="detail-item">
                        <strong>Delivery ID</strong>
                        <span><code>{{ $callbackTest['delivery_id'] }}</code></span>
                    </div>
                    <div class="detail-item">
                        <strong>Waktu test</strong>
                        <span>{{ $callbackTest['tested_at'] }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>HTTP Status</strong>
                        <span>{{ $callbackTest['status_code'] ?? '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Ringkasan</strong>
                        <span>{{ $callbackTest['error_message'] ?? 'Endpoint merespons request test dari payment.naeva.id.' }}</span>
                    </div>
                </div>

                @if (filled($callbackTest['response_body'] ?? null))
                    <div class="field" style="margin-top: 18px;">
                        <label>Response body</label>
                        <pre class="code-block">{{ $callbackTest['response_body'] }}</pre>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="panel-body">
            <form method="POST" action="{{ route('dashboard.projects.update', $project) }}">
                @csrf
                @method('PUT')
                @php($submitLabel = 'Simpan perubahan')
                @include('dashboard.projects._form')
            </form>

            <form id="project-callback-test-form" method="POST" action="{{ route('dashboard.projects.test-callback', $project) }}" class="inline-form">
                @csrf
                <input type="hidden" name="app_id" value="">
                <input type="hidden" name="secret_key" value="">
                <input type="hidden" name="callback_url" value="">
            </form>
        </div>
    </section>
@endsection
