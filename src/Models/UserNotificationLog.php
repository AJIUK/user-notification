<?php

namespace UserNotification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserNotificationLog extends Model
{
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'notification_class',
        'mailing_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function getTable()
    {
        return config('user-notification.logs_table', 'user_notification_logs');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function mailing(): BelongsTo
    {
        return $this->belongsTo(UserNotificationMailing::class, 'mailing_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(UserNotificationLogChannel::class, 'log_id');
    }
}
