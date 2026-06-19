@extends('layouts.auth')

@section('title', 'Register')
@section('eyebrow', 'Register')
@section('heading', 'Buat akun dashboard utama')
@section('subheading', 'Pendaftaran awal ini bisa dipakai untuk tim internal Naeva. Setelah akun dibuat, welcome email akan dikirim ke alamat yang didaftarkan.')

@section('content')
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="field">
            <label for="name">Nama lengkap</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirmation">Konfirmasi password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>

        <div class="button-row">
            <button class="button button-primary" type="submit">Register</button>
            <a class="button button-secondary" href="{{ route('login') }}">Sudah punya akun</a>
        </div>
    </form>

    <div class="muted-links">
        <span>Butuh akses cepat?</span>
        <a href="{{ route('password.request') }}">Reset password</a>
        <a href="{{ route('home') }}">Kembali ke beranda</a>
    </div>
@endsection
