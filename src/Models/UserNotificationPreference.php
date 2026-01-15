<?php

namespace UserNotification\Models;

use App\Enums\UserNotificationChannel;
use App\Enums\UserNotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UserNotification\Contracts\NotifiableUser;
use UserNotification\Contracts\UserNotificationPreferenceInterface;

/**
 * Модель для хранения настроек уведомлений пользователей
 *
 * Поля:
 * - user_id (int|string) - ID пользователя
 * - type (int) - тип уведомления (int значение enum NotificationTypeEnum)
 * - channel (int) - канал уведомления (int значение enum NotificationChannelEnum)
 * - is_active (bool) - активна ли настройка
 *
 * Примечание: type и channel хранятся как unsignedTinyInteger в БД.
 * Enum'ы должны иметь int backing type (enum UserNotificationType: int).
 */
class UserNotificationPreference extends Model implements UserNotificationPreferenceInterface
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    /**
     * Получить имя таблицы из конфига или использовать значение по умолчанию
     */
    public function getTable()
    {
        return config('user-notification.preferences_table', 'user_notification_preferences');
    }

    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'type' => config('user-notification.type_enum'),
            'channel' => config('user-notification.channel_enum'),
            'is_active' => 'boolean',
        ];
    }

    /**
     * Получить ID пользователя
     * @return int|string
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * Получить тип уведомления
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Получить канал уведомления
     * @return int
     */
    public function getChannel(): int
    {
        return $this->channel;
    }

    /**
     * Проверить, активна ли настройка
     * @return bool
     */
    public function getIsActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Связь с пользователем
     * Пользователь библиотеки может переопределить этот метод,
     * если его модель User имеет другое имя или namespace
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        $userClass = config('user-notification.user_model');

        if (!$userClass || !class_exists($userClass)) {
            throw new \RuntimeException(
                'User model class not found. Please set "user-notification.user_model" config or create App\\Models\\User class.'
            );
        }

        return $this->belongsTo($userClass, 'user_id');
    }

    /**
     * Scope для фильтрации по пользователю
     */
    public function scopeForUser($query, $userId)
    {
        $id = $userId instanceof NotifiableUser ? ($userId->id ?? $userId->getKey()) : $userId;
        return $query->where('user_id', $id);
    }

    /**
     * Scope для фильтрации по типу
     */
    public function scopeForType($query, int $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope для фильтрации по каналу
     */
    public function scopeForChannel($query, int $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope для активных настроек
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
