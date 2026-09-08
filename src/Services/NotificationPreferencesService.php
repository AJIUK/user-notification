<?php

namespace UserNotification\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UserNotification\Contracts\NotifiableUser;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Contracts\NotificationTypeEnum;
use UserNotification\Models\UserNotificationPreference;
use UserNotification\Support\NotificationRegistry;

/**
 * Сервис для работы с настройками уведомлений пользователей
 * Использует модель UserNotificationPreference из библиотеки
 *
 * Пользователь библиотеки может расширить этот класс или создать свой
 */
class NotificationPreferencesService
{

    /**
     * Получить настройки уведомлений для пользователя по типу
     *
     * @param NotifiableUser $user
     * @param bool $includeHidden Если false, скрытые каналы не попадают в ответ для UI
     * @return Collection Коллекция с полями: is_active (bool), channel (NotificationChannelEnum)
     */
    public function getNotificationPreferences(
        NotifiableUser $user,
        ?NotificationTypeEnum $notificationType = null,
        ?NotificationChannelEnum $notificationChannel = null,
        bool $includeHidden = true,
    ): Collection {
        $query = UserNotificationPreference::query()
            ->forUser($user->getKey());

        if ($notificationType) {
            $query->forType($notificationType->getValue());
        }

        if ($notificationChannel) {
            $query->forChannel($notificationChannel->getValue());
        }

        $preferences = $query->get()->keyBy(fn ($pref) => $pref->type->getValue() . "_" . $pref->channel->getValue());

        $result = collect();

        // Получаем зарегистрированные типы и каналы из реестра
        $types = NotificationRegistry::getTypes();
        $channels = NotificationRegistry::getChannels();

        foreach ($types as $type) {
            if ($notificationType && $type !== $notificationType) {
                continue;
            }
            foreach ($channels as $channel) {
                if ($notificationChannel && $channel !== $notificationChannel) {
                    continue;
                }
                if (!$includeHidden && $type->isChannelHidden($channel)) {
                    continue;
                }
                $key = $type->getValue() . "_" . $channel->getValue();

                if ($preferences->has($key)) {
                    $result->push($preferences[$key]);
                } else {
                    $preference = new UserNotificationPreference([
                        'user_id' => $user->getKey(),
                        'type' => $type->getValue(),
                        'channel' => $channel->getValue(),
                        'is_active' => in_array($channel, $type->getDefaultChannels(), true),
                    ]);

                    $result->push($preference);
                }
            }
        }

        return $result;
    }

    /**
     * Установить настройки уведомлений для пользователя
     * Скрытые и нередактируемые пары тип+канал нельзя изменить:
     * входящие значения для них игнорируются, текущие сохраняются
     *
     * @param NotifiableUser $user
     * @param array $preferences Массив с ключами: type (int), channel (int), is_active (bool)
     * @return void
     */
    public function setNotificationPreferences(NotifiableUser $user, array $preferences): void
    {
        $preferencesTable = config('user-notification.preferences_table', 'user_notification_preferences');

        $preserved = [];
        foreach ($this->getNotificationPreferences($user) as $pref) {
            $type = $pref->type;
            $channel = $pref->channel;
            if (!$type instanceof NotificationTypeEnum || !$channel instanceof NotificationChannelEnum) {
                continue;
            }
            if (!$type->isChannelReadonly($channel)) {
                continue;
            }

            $preserved[$this->preferenceKey($type, $channel)] = [
                'user_id' => $user->getKey(),
                'type' => $type->getValue(),
                'channel' => $channel->getValue(),
                'is_active' => (bool) $pref->is_active,
            ];
        }

        $editable = [];
        foreach ($preferences as $item) {
            $type = $this->resolveType($item['type'] ?? null);
            $channel = $this->resolveChannel($item['channel'] ?? null);
            if (!$type || !$channel || $type->isChannelReadonly($channel)) {
                continue;
            }

            $editable[$this->preferenceKey($type, $channel)] = [
                'user_id' => $user->getKey(),
                'type' => $type->getValue(),
                'channel' => $channel->getValue(),
                'is_active' => (bool) ($item['is_active'] ?? false),
            ];
        }

        $toInsert = array_values($preserved + $editable);

        DB::table($preferencesTable)->where('user_id', $user->getKey())->delete();

        if ($toInsert !== []) {
            DB::table($preferencesTable)->insert($toInsert);
        }
    }

    protected function preferenceKey(NotificationTypeEnum $type, NotificationChannelEnum $channel): string
    {
        return $type->getValue() . '_' . $channel->getValue();
    }

    protected function resolveType(mixed $value): ?NotificationTypeEnum
    {
        if ($value instanceof NotificationTypeEnum) {
            return $value;
        }

        return NotificationRegistry::getTypes()->first(
            fn (NotificationTypeEnum $type) => $type->getValue() == $value
        );
    }

    protected function resolveChannel(mixed $value): ?NotificationChannelEnum
    {
        if ($value instanceof NotificationChannelEnum) {
            return $value;
        }

        return NotificationRegistry::getChannels()->first(
            fn (NotificationChannelEnum $channel) => $channel->getValue() == $value
        );
    }

}
