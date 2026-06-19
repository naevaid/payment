@extends('layouts.auth')

@section('title', 'Reset Password')
@section('eyebrow', 'Reset Password')
@section('heading', 'Tetapkan password baru')
@section('subheading', 'Gunakan halaman ini dari link email reset password. Setelah berhasil, Anda bisa langsung login ke dashboard utama.')

@section('content')
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
        </div>

        <div class="field">
            <label for="password">Password baru</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirmation">Konfirmasi password baru</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>

        <div class="button-row">
            <button class="button button-primary" type="submit">Simpan password baru</button>
            <a class="button button-secondary" href="{{ route('login') }}">Batal</a>
        </div>
    </form>
@endsection
