<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') - {{ config('app.name', 'payment') }}</title>
        <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    </head>
    <body>
        <div class="admin-shell">
            <aside class="admin-sidebar">
                <a class="brand" href="{{ route('dashboard') }}">
                    <span class="brand-badge">N</span>
                    <span class="brand-copy">
                        <strong>payment.naeva.id</strong>
                        <span>Centralized payment gateway</span>
                    </span>
                </a>

                <div class="sidebar-section">
                    <div class="sidebar-heading">Overview</div>

                    <a class="menu-link @if (request()->routeIs('dashboard')) is-active @endif" href="{{ route('dashboard') }}">
                        <span class="menu-icon">DB</span>
                        <span class="menu-copy">
                            <strong>Dashboard</strong>
                            <small>Ringkasan operasional utama</small>
                        </span>
                    </a>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-heading">Master Data</div>

                    <a class="menu-link @if (request()->routeIs('dashboard.projects.*')) is-active @endif" href="{{ route('dashboard.projects.index') }}">
                        <span class="menu-icon">PJ</span>
                        <span class="menu-copy">
                            <strong>Projects / Tenants</strong>
                            <small>Kelola tenant, `app_id`, callback, secret</small>
                        </span>
                    </a>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-heading">Operasional Payment</div>

                    <a class="menu-link @if (request()->routeIs('dashboard.transactions.*')) is-active @endif" href="{{ route('dashboard.transactions.index') }}">
                        <span class="menu-icon">TR</span>
                        <span class="menu-copy">
                            <strong>Transactions</strong>
                            <small>List global, status, dan detail transaksi</small>
                        </span>
                    </a>

                    <a class="menu-link @if (request()->routeIs('dashboard.webhook-logs.*')) is-active @endif" href="{{ route('dashboard.webhook-logs.index') }}">
                        <span class="menu-icon">WH</span>
                        <span class="menu-copy">
                            <strong>Webhook Logs</strong>
                            <small>Audit webhook Midtrans dan validasi signature</small>
                        </span>
                    </a>

                    <a class="menu-link @if (request()->routeIs('dashboard.callback-logs.*')) is-active @endif" href="{{ route('dashboard.callback-logs.index') }}">
                        <span class="menu-icon">CB</span>
                        <span class="menu-copy">
                            <strong>Callback Logs</strong>
                            <small>Status forwarding ke project asal</small>
                        </span>
                    </a>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-heading">Rencana PRD</div>

                    <div class="menu-link is-muted">
                        <span class="menu-icon">RT</span>
                        <span class="menu-copy">
                            <strong>Retry Manual Callback</strong>
                            <small>Action operasional untuk retry callback gagal</small>
                        </span>
                        <span class="menu-tag">Next</span>
                    </div>

                    <div class="menu-link is-muted">
                        <span class="menu-icon">RP</span>
                        <span class="menu-copy">
                            <strong>Reporting & Filter Lanjutan</strong>
                            <small>Export, analytics, dan filter lintas tenant</small>
                        </span>
                        <span class="menu-tag">Planned</span>
                    </div>

                    <div class="menu-link is-muted">
                        <span class="menu-icon">OP</span>
                        <span class="menu-copy">
                            <strong>Queue & Health Ops</strong>
                            <small>Redis worker, retry, dan health monitoring</small>
                        </span>
                        <span class="menu-tag">Planned</span>
                    </div>
                </div>

                <div class="sidebar-footer">
                    <div class="sidebar-user">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ auth()->user()->email }}</span>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="button button-primary button-block" type="submit">Logout</button>
                    </form>
                </div>
            </aside>

            <div class="admin-main">
                <header class="admin-topbar">
                    <div>
                        <div class="eyebrow">@yield('eyebrow', 'Dashboard')</div>
                        <h1 class="page-title">@yield('page-title')</h1>
                        <p class="page-subtitle">@yield('page-subtitle')</p>
                    </div>

                    <div class="topbar-actions">
                        @yield('page-actions')
                    </div>
                </header>

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <main class="page-content">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
