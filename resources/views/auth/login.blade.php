@extends('layouts.auth')

@section('title', 'Login')
@section('eyebrow', 'Login')
@section('heading', 'Masuk ke dashboard utama')
@section('subheading', 'Gunakan akun internal Anda untuk mengakses dashboard payment service, monitoring transaksi, dan konfigurasi tenant.')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>

        <div class="inline-row">
            <label class="checkbox" for="remember">
                <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Ingat saya</span>
            </label>

            <a href="{{ route('password.request') }}">Lupa password?</a>
        </div>

        <div class="button-row">
            <button class="button button-primary" type="submit">Login</button>
            <a class="button button-secondary" href="{{ route('home') }}">Kembali ke Beranda</a>
        </div>
    </form>

    <div class="muted-links">
        <span>Belum punya akun?</span>
        <a href="{{ route('register') }}">Buat akun baru</a>
    </div>
@endsection
