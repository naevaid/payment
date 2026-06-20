@extends('layouts.dashboard')

@section('title', 'Tambah Project')
@section('eyebrow', 'Projects / Tenants')
@section('page-title', 'Tambah project / tenant baru')
@section('page-subtitle', 'Project baru akan mendapatkan `app_id` dan `secret_key` untuk autentikasi ke charge API. Nilai ini bisa digenerate otomatis bila tidak diisi manual.')

@section('content')
    <section class="panel">
        <div class="panel-body">
            <div class="alert alert-success" style="margin-bottom: 18px;">
                Simpan project terlebih dahulu untuk menjalankan test callback URL dari server `payment.naeva.id`.
            </div>

            <form method="POST" action="{{ route('dashboard.projects.store') }}">
                @csrf
                @php($submitLabel = 'Simpan project')
                @include('dashboard.projects._form')
            </form>
        </div>
    </section>
@endsection
