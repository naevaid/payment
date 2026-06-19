<?php

namespace App\Models;

use App\Enums\CallbackStatus;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'project_id',
        'gateway_order_id',
        'client_order_id',
        'midtrans_transaction_id',
        'amount',
        'currency',
        'status',
        'callback_status',
        'callback_url',
        'payment_type',
        'snap_token',
        'snap_redirect_url',
        'customer_details',
        'item_details',
        'metadata',
        'midtrans_payload',
        'paid_at',
        'expires_at',
        'last_webhook_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'customer_details' => 'array',
            'item_details' => 'array',
            'metadata' => 'array',
            'midtrans_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'status' => TransactionStatus::class,
            'callback_status' => CallbackStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
