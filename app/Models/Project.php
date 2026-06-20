<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'app_id',
        'project_name',
        'secret_key',
        'default_callback_url',
        'is_active',
        'metadata',
    ];

    protected $hidden = [
        'secret_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret_key' => 'encrypted',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function callbackForwardingLogs(): HasMany
    {
        return $this->hasMany(CallbackForwardingLog::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
