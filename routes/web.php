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
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::view('/docs/api', 'api-docs')->name('docs.api');
Route::get('/midtrans/finish', function (Request $request) {
    $orderId = (string) $request->query('order_id', '');

    $transaction = filled($orderId)
        ? Transaction::query()
            ->with('project')
            ->where(function ($query) use ($orderId): void {
                $query
                    ->where('gateway_order_id', $orderId)
                    ->orWhere('client_order_id', $orderId);
            })
            ->first()
        : null;

    return view('midtrans-finish', [
        'transaction' => $transaction,
        'midtransStatus' => (string) $request->query('transaction_status', ''),
        'statusCode' => (string) $request->query('status_code', ''),
        'paymentType' => (string) $request->query('payment_type', ''),
        'fraudStatus' => (string) $request->query('fraud_status', ''),
        'grossAmount' => (string) $request->query('gross_amount', ''),
        'transactionTime' => (string) $request->query('transaction_time', ''),
        'settlementTime' => (string) $request->query('settlement_time', ''),
        'orderId' => $orderId,
    ]);
})->name('midtrans.finish');

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
    Route::post('/dashboard/projects/{project}/test-callback', [ProjectController::class, 'testCallback'])->name('dashboard.projects.test-callback');
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
