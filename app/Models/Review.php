<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'yandex_review_id',
        'author_name',
        'rating',
        'text',
        'publish_date',
    ];

    protected $casts = [
        'publish_date' => 'datetime',
        'rating' => 'float',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
