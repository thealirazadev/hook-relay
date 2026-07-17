<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERING = 'delivering';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD = 'dead';

    /** States from which no further automatic transition happens. */
    public const TERMINAL = [self::STATUS_DELIVERED, self::STATUS_DEAD];

    protected $fillable = [
        'webhook_event_id',
        'destination_id',
        'status',
        'attempt_count',
        'max_attempts',
        'next_attempt_at',
        'last_attempted_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'last_attempted_at' => 'datetime',
    ];

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class);
    }
}
