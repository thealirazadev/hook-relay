<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    use HasFactory, HasUlids;

    /** Attempt rows are immutable audit records. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'delivery_id',
        'attempt_number',
        'response_status',
        'response_headers',
        'response_body_excerpt',
        'error',
        'duration_ms',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'attempt_number' => 'integer',
        'response_status' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
