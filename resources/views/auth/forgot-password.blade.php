@extends('layouts.auth')

@section('title', 'Lupa Password')
@section('eyebrow', 'Password Reset')
@section('heading', 'Minta link reset password')
@section('subheading', 'Masukkan email akun Anda. Laravel akan mengirim link reset password ke inbox sesuai konfigurasi SMTP production yang sudah dipasang.')

@section('content')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="button-row">
            <button class="button button-primary" type="submit">Kirim link reset</button>
            <a class="button button-secondary" href="{{ route('login') }}">Kembali ke login</a>
        </div>
    </form>

    <div class="muted-links">
        <span>Belum punya akun?</span>
        <a href="{{ route('register') }}">Daftar sekarang</a>
    </div>
@endsection
