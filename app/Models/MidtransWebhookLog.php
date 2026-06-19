<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MidtransWebhookLog extends Model
{
    protected $fillable = [
        'transaction_id',
        'order_id',
        'midtrans_transaction_id',
        'transaction_status',
        'signature_key',
        'payload',
        'headers',
        'is_signature_valid',
        'processing_status',
        'notes',
        'received_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'is_signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
