<?php

namespace UserNotification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UserNotification\Enums\NotificationMailingStage;
use UserNotification\Enums\NotificationMailingStatus;

class UserNotificationMailing extends Model
{
    protected $fillable = [
        'user_type',
        'user_id',
        'notification_class',
        'notification_type',
        'subject_type',
        'subject_id',
        'meta',
        'status',
        'stage',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'notification_type' => 'integer',
            'status' => NotificationMailingStatus::class,
            'stage' => NotificationMailingStage::class,
            'failed_at' => 'datetime',
        ];
    }

    public function getTable()
    {
        return config('user-notification.mailings_table', 'user_notification_mailings');
    }

    /**
     * Автор рассылки: User, MoonshineUser и т.п.
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(UserNotificationLog::class, 'mailing_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(UserNotificationMailingRecipient::class, 'mailing_id');
    }

    public function isCancellable(): bool
    {
        return in_array($this->stage, [
            NotificationMailingStage::DRAFT,
            NotificationMailingStage::COLLECTING,
            NotificationMailingStage::READY,
            NotificationMailingStage::SENDING,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->stage, [
            NotificationMailingStage::COMPLETED,
            NotificationMailingStage::FAILED,
            NotificationMailingStage::CANCELLED,
        ], true);
    }
}
