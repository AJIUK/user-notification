<?php

namespace UserNotification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UserNotification\Contracts\UserNotificationPreferenceInterface;

/**
 * Настройки уведомлений любой Eloquent-модели.
 *
 * Поля:
 * - notifiable_type (string) - класс владельца
 * - notifiable_id (int|string) - ID владельца
 * - type (int) - тип уведомления
 * - channel (int) - канал уведомления
 * - is_active (bool) - активна ли настройка
 *
 * type и channel хранятся как unsignedTinyInteger.
 * Enum'ы должны иметь int backing type.
 */
class UserNotificationPreference extends Model implements UserNotificationPreferenceInterface
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    public function getTable()
    {
        return config('user-notification.preferences_table', 'user_notification_preferences');
    }

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'type',
        'channel',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => config('user-notification.type_enum'),
            'channel' => config('user-notification.channel_enum'),
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return int|string
     */
    public function getNotifiableId()
    {
        return $this->notifiable_id;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getChannel(): int
    {
        return $this->channel;
    }

    public function getIsActive(): bool
    {
        return $this->is_active;
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Настройки конкретной модели.
     */
    public function scopeForNotifiable($query, Model $notifiable)
    {
        return $query
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey());
    }

    public function scopeForType($query, int $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForChannel($query, int $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
