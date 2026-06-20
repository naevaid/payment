@extends('layouts.dashboard')

@section('title', 'Projects / Tenants')
@section('eyebrow', 'Master Data')
@section('page-title', 'Projects / Tenants')
@section('page-subtitle', 'Kelola tenant internal Naeva, `app_id`, callback URL default, secret key, dan status aktif project yang boleh memakai centralized payment gateway.')

@section('page-actions')
    <a class="button button-primary" href="{{ route('dashboard.projects.create') }}">Tambah Project</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-body">
            <form method="GET" action="{{ route('dashboard.projects.index') }}">
                <div class="filter-grid">
                    <div class="field">
                        <label for="search">Cari</label>
                        <input class="input" id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama project, app_id, callback URL">
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select class="select" id="status" name="status">
                            <option value="">Semua</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="button-row">
                    <button class="button button-primary" type="submit">Terapkan filter</button>
                    <a class="button" href="{{ route('dashboard.projects.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-body">
            <div class="panel-heading">
                <div>
                    <h2>Daftar project</h2>
                    <p>Setiap tenant memiliki `app_id`, `secret_key`, callback URL default, dan histori transaksi sendiri.</p>
                </div>
            </div>

            @if ($projects->isEmpty())
                <div class="empty-state">Belum ada project / tenant. Tambahkan project pertama untuk mulai uji charge API.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>App ID</th>
                                <th>Status</th>
                                <th>Callback URL</th>
                                <th>Volume</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                <tr>
                                    <td>
                                        <strong>{{ $project->project_name }}</strong>
                                        <br>
                                        <small>Dibuat {{ $project->created_at?->format('d M Y H:i') }}</small>
                                    </td>
                                    <td><code>{{ $project->app_id }}</code></td>
                                    <td>
                                        <span class="badge {{ $project->is_active ? 'badge-success' : 'badge-muted' }}">
                                            {{ $project->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($project->default_callback_url)
                                            <a href="{{ $project->default_callback_url }}" target="_blank" rel="noreferrer">{{ $project->default_callback_url }}</a>
                                        @else
                                            <span class="muted">Belum diisi</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ number_format($project->transactions_count) }}</strong> transaksi
                                        <br>
                                        <small>{{ number_format($project->callback_forwarding_logs_count) }} callback log</small>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="button" href="{{ route('dashboard.projects.show', $project) }}">Detail</a>
                                            <a class="button" href="{{ route('dashboard.projects.edit', $project) }}">Edit</a>

                                            <form class="inline-form" method="POST" action="{{ route('dashboard.projects.destroy', $project) }}" onsubmit="return confirm('Hapus project ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="button button-danger" type="submit">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrap">
                    {{ $projects->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
