<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Укажите полное имя класса модели User в вашем приложении.
    | По умолчанию используется App\Models\User
    |
    */
    'user_model' => env('USER_NOTIFICATION_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Preferences Table
    |--------------------------------------------------------------------------
    |
    | Укажите имя таблицы для настроек уведомлений.
    | По умолчанию используется 'user_notification_preferences'
    |
    */
    'preferences_table' => env('USER_NOTIFICATION_PREFERENCES_TABLE', 'user_notification_preferences'),

    /*
    |--------------------------------------------------------------------------
    | Channel Enum Class
    |--------------------------------------------------------------------------
    |
    | Укажите полное имя класса enum для каналов уведомлений.
    | По умолчанию используется App\Enums\UserNotificationChannel
    |
    */
    'channel_enum' => env('USER_NOTIFICATION_CHANNEL_ENUM', 'App\\Enums\\UserNotificationChannel'),

    /*
    |--------------------------------------------------------------------------
    | Type Enum Class
    |--------------------------------------------------------------------------
    |
    | Укажите полное имя класса enum для типов уведомлений.
    | По умолчанию используется App\Enums\UserNotificationType
    |
    */
    'type_enum' => env('USER_NOTIFICATION_TYPE_ENUM', 'App\\Enums\\UserNotificationType'),
];
