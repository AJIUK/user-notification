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
     * @param NotificationTypeEnum $type
     * @return Collection Коллекция с полями: is_active (bool), channel (NotificationChannelEnum)
     */
    public function getNotificationPreferences(
        NotifiableUser $user,
        ?NotificationTypeEnum $notificationType = null,
        ?NotificationChannelEnum $notificationChannel = null
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
     *
     * @param NotifiableUser $user
     * @param array $preferences Массив с ключами: type (int), channel (int), is_active (bool)
     * @return void
     */
    public function setNotificationPreferences(NotifiableUser $user, array $preferences): void
    {
        $preferencesTable = config('user-notification.preferences_table', 'user_notification_preferences');

        $delQuery = DB::table($preferencesTable)->where('user_id', $user->getKey());
        $delQuery->delete();

        $preferences = array_map(function ($item) use ($user) {
            $item['user_id'] = $user->getKey();
            return $item;
        }, $preferences);

        DB::table($preferencesTable)->insert($preferences);
    }

}
