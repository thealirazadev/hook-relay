<?php

namespace App\Models;

use App\Jobs\DeliverEvent;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class WebhookEvent extends Model
{
    use HasFactory, HasUlids;

    /** Events are immutable once written. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'source_id',
        'provider_event_id',
        'dedupe_key',
        'event_type',
        'headers',
        'payload',
        'content_type',
        'received_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'received_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Fan the event out to one pending delivery per active routed destination.
     * Shared by the ingest path and by replay.
     *
     * @return Collection<int, Delivery>
     */
    public function createDeliveries(): Collection
    {
        $source = $this->source()->withTrashed()->first();

        if ($source === null) {
            return collect();
        }

        $maxAttempts = config('hook_relay.delivery_max_attempts');

        return $source->destinations()
            ->where('destinations.active', true)
            ->get()
            ->map(function (Destination $destination) use ($maxAttempts) {
                $delivery = $this->deliveries()->create([
                    'destination_id' => $destination->id,
                    'status' => Delivery::STATUS_PENDING,
                    'attempt_count' => 0,
                    'max_attempts' => $maxAttempts,
                ]);

                DeliverEvent::dispatch($delivery);

                return $delivery;
            });
    }
}
