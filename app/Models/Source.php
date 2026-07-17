<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Source extends Model
{
    use HasFactory, SoftDeletes;

    /** Providers whose signature schemes hook-relay understands. */
    public const PROVIDERS = ['stripe', 'github', 'shopify', 'generic'];

    protected $fillable = [
        'name',
        'provider',
        'signing_secret',
        'active',
    ];

    protected $casts = [
        'signing_secret' => 'encrypted',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Source $source) {
            if (empty($source->ingest_key)) {
                $source->ingest_key = static::generateIngestKey();
            }
        });
    }

    /**
     * A 32-character URL-safe token, unique across active and trashed sources.
     */
    public static function generateIngestKey(): string
    {
        do {
            $key = Str::random(32);
        } while (static::withTrashed()->where('ingest_key', $key)->exists());

        return $key;
    }

    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class, 'source_destination');
    }
}
