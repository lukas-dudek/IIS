<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    public const STATUS_VISIBLE = 'visible';
    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'event_id',
        'user_id',
        'body',
        'status',
        'moderated_by',
        'moderated_at',
        'hidden_reason',
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VISIBLE);
    }

    public function isVisible(): bool
    {
        return $this->status === self::STATUS_VISIBLE;
    }
}
