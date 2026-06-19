<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login - {{ config('app.name', 'payment') }}</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                font-family: Arial, Helvetica, sans-serif;
                background: #f8fafc;
                color: #0f172a;
                padding: 24px;
            }

            .card {
                width: min(520px, 100%);
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 24px;
                padding: 32px;
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            }

            .eyebrow {
                color: #1d4ed8;
                font-size: 13px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            h1 {
                margin: 12px 0;
                font-size: 32px;
            }

            p {
                color: #475569;
                line-height: 1.7;
                margin: 0 0 18px;
            }

            .actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 24px;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 0 18px;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                border: 1px solid #cbd5e1;
            }

            .button-primary {
                color: #fff;
                background: #1d4ed8;
                border-color: #1d4ed8;
            }

            .button-secondary {
                color: #0f172a;
                background: #fff;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="eyebrow">Login Placeholder</div>
            <h1>Halaman login sedang disiapkan.</h1>
            <p>
                Routing publik untuk login sudah aktif. Implementasi autentikasi form dan session admin internal akan
                dilanjutkan pada tahap berikutnya.
            </p>
            <p>
                Akun utama yang sudah Anda seed dapat digunakan setelah flow autentikasi web selesai dipasang.
            </p>

            <div class="actions">
                <a class="button button-primary" href="{{ route('home') }}">Kembali ke Beranda</a>
                <a class="button button-secondary" href="{{ route('register') }}">Buka Register</a>
            </div>
        </main>
    </body>
</html>
