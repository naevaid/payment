<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Dashboard\CallbackForwardingLogController;
use App\Http\Controllers\Dashboard\ProjectController;
use App\Http\Controllers\Dashboard\TransactionController;
use App\Http\Controllers\Dashboard\WebhookLogController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('/dashboard/projects', ProjectController::class)->names('dashboard.projects');
    Route::post('/dashboard/projects/{project}/regenerate-app-id', [ProjectController::class, 'regenerateAppId'])->name('dashboard.projects.regenerate-app-id');
    Route::post('/dashboard/projects/{project}/regenerate-secret-key', [ProjectController::class, 'regenerateSecretKey'])->name('dashboard.projects.regenerate-secret-key');
    Route::get('/dashboard/transactions', [TransactionController::class, 'index'])->name('dashboard.transactions.index');
    Route::get('/dashboard/transactions/export/csv', [TransactionController::class, 'exportCsv'])->name('dashboard.transactions.export');
    Route::get('/dashboard/transactions/{transaction}', [TransactionController::class, 'show'])->name('dashboard.transactions.show');
    Route::get('/dashboard/webhook-logs', [WebhookLogController::class, 'index'])->name('dashboard.webhook-logs.index');
    Route::get('/dashboard/webhook-logs/export/csv', [WebhookLogController::class, 'exportCsv'])->name('dashboard.webhook-logs.export');
    Route::get('/dashboard/webhook-logs/{webhookLog}', [WebhookLogController::class, 'show'])->name('dashboard.webhook-logs.show');
    Route::get('/dashboard/callback-logs', [CallbackForwardingLogController::class, 'index'])->name('dashboard.callback-logs.index');
    Route::get('/dashboard/callback-logs/export/csv', [CallbackForwardingLogController::class, 'exportCsv'])->name('dashboard.callback-logs.export');
    Route::get('/dashboard/callback-logs/{callbackLog}', [CallbackForwardingLogController::class, 'show'])->name('dashboard.callback-logs.show');
    Route::post('/dashboard/callback-logs/{callbackLog}/retry', [CallbackForwardingLogController::class, 'retry'])->name('dashboard.callback-logs.retry');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::get('/healthz', function () {
    return response()->json([
        'ok' => true,
        'service' => 'payment',
    ]);
});
