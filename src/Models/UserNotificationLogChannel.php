<?php

namespace UserNotification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UserNotification\Enums\NotificationLogChannelStatus;

class UserNotificationLogChannel extends Model
{
    protected $fillable = [
        'log_id',
        'channel',
        'status',
        'meta',
        'attempts',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => config('user-notification.channel_enum'),
            'status' => NotificationLogChannelStatus::class,
            'meta' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getTable()
    {
        return config('user-notification.log_channels_table', 'user_notification_log_channels');
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(UserNotificationLog::class, 'log_id');
    }
}
