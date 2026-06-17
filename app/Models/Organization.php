<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_url',
        'normalized_url',
        'yandex_organization_id',
        'rating',
        'rating_count',
        'review_count',
        'last_synced_at',
        'sync_status',
        'sync_error',
        'synced_reviews_count',
        'last_sync_started_at',
        'last_sync_finished_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_sync_started_at' => 'datetime',
        'last_sync_finished_at' => 'datetime',
        'rating' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
