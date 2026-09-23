<?php

namespace UserNotification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UserNotification\Enums\NotificationMailingRecipientStatus;

class UserNotificationMailingRecipient extends Model
{
    protected $fillable = [
        'mailing_id',
        'notifiable_type',
        'notifiable_id',
        'status',
        'meta',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationMailingRecipientStatus::class,
            'meta' => 'array',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function getTable()
    {
        return config(
            'user-notification.mailing_recipients_table',
            'user_notification_mailing_recipients'
        );
    }

    public function mailing(): BelongsTo
    {
        return $this->belongsTo(UserNotificationMailing::class, 'mailing_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Логи доставки этого получателя в рамках рассылки
     * (mailing_id + notifiable morph).
     */
    public function logs(): HasMany
    {
        return $this->hasMany(UserNotificationLog::class, 'mailing_id', 'mailing_id')
            ->where('notifiable_type', $this->notifiable_type)
            ->where('notifiable_id', $this->notifiable_id);
    }

    public function scopePendingRestart($query)
    {
        return $query->whereIn('status', [
            NotificationMailingRecipientStatus::PENDING->value,
            NotificationMailingRecipientStatus::DISPATCHED->value,
            NotificationMailingRecipientStatus::PARTIAL->value,
            NotificationMailingRecipientStatus::FAILED->value,
        ]);
    }
}
