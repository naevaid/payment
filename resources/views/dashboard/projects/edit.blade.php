@extends('layouts.dashboard')

@section('title', 'Edit Project')
@section('eyebrow', 'Projects / Tenants')
@section('page-title', 'Edit project / tenant')
@section('page-subtitle', 'Perbarui identitas tenant, callback URL, metadata, dan status aktif. Secret key hanya diganti bila Anda mengisi nilai baru.')

@section('page-actions')
    <a class="button" href="{{ route('dashboard.projects.show', $project) }}">Lihat detail</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-body">
            <form method="POST" action="{{ route('dashboard.projects.update', $project) }}">
                @csrf
                @method('PUT')
                @php($submitLabel = 'Simpan perubahan')
                @include('dashboard.projects._form')
            </form>
        </div>
    </section>
@endsection
