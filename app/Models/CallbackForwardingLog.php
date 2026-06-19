<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallbackForwardingLog extends Model
{
    protected $fillable = [
        'transaction_id',
        'project_id',
        'callback_url',
        'attempt',
        'event_type',
        'payload',
        'request_headers',
        'response_status_code',
        'response_body',
        'success',
        'error_message',
        'next_retry_at',
        'dispatched_at',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'request_headers' => 'array',
            'success' => 'boolean',
            'next_retry_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
