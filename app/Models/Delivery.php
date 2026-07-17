<?php

namespace App\Models;

use App\Jobs\DeliverEvent;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Delivery extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERING = 'delivering';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD = 'dead';

    /** All delivery statuses, in lifecycle order. */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DELIVERING,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
        self::STATUS_DEAD,
    ];

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

    /**
     * Return a delivery to the queue with a fresh attempt budget. Lifetime
     * attempt_count and all prior attempt rows are preserved for the audit trail.
     */
    public function requeue(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'next_attempt_at' => null,
            'max_attempts' => config('hook_relay.delivery_max_attempts'),
        ])->save();

        DeliverEvent::dispatch($this);

        Log::info('delivery.requeued', ['delivery_id' => $this->id]);
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
