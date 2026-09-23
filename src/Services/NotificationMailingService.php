<?php

namespace UserNotification\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UserNotification\Contracts\CreatesFromMailing;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Contracts\NotificationTypeEnum;
use UserNotification\Enums\NotificationLogChannelStatus;
use UserNotification\Enums\NotificationMailingRecipientStatus;
use UserNotification\Enums\NotificationMailingStage;
use UserNotification\Enums\NotificationMailingStatus;
use UserNotification\Events\UserNotificationMailingStageChanged;
use UserNotification\Jobs\CollectMailingRecipientsJob;
use UserNotification\Jobs\SendMailingRecipientsJob;
use UserNotification\Models\UserNotificationLog;
use UserNotification\Models\UserNotificationMailing;
use UserNotification\Models\UserNotificationMailingRecipient;
use UserNotification\Support\NotificationRegistry;
use UserNotification\UserNotification;

class NotificationMailingService
{
    /**
     * Создать черновик рассылки.
     *
     * @param  class-string<UserNotification>  $notificationClass
     */
    public function create(
        string $notificationClass,
        NotificationTypeEnum|int $notificationType,
        ?Model $user = null,
        ?Model $subject = null,
        ?array $meta = null,
    ): UserNotificationMailing {
        $typeValue = $notificationType instanceof NotificationTypeEnum
            ? $notificationType->getValue()
            : $notificationType;

        return UserNotificationMailing::query()->create([
            'user_type' => $user?->getMorphClass(),
            'user_id' => $user?->getKey(),
            'notification_class' => $notificationClass,
            'notification_type' => $typeValue,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'status' => NotificationMailingStatus::DRAFT,
            'stage' => NotificationMailingStage::DRAFT,
        ]);
    }

    public function findOrFail(int $mailingId): UserNotificationMailing
    {
        return UserNotificationMailing::query()->findOrFail($mailingId);
    }

    /**
     * Привязать уведомление к рассылке (id попадёт в delivery log).
     */
    public function attachToNotification(UserNotification $notification, UserNotificationMailing|int $mailing): UserNotification
    {
        return $notification->forMailing($mailing);
    }

    /**
     * Запустить рассылку: сбор получателей, затем отправка.
     */
    public function dispatch(UserNotificationMailing $mailing, bool $thenSend = true): void
    {
        if ($mailing->isTerminal()) {
            return;
        }

        // READY + thenSend=false — пересбор аудитории (например, после пустого collect).
        if ($mailing->stage === NotificationMailingStage::DRAFT
            || $mailing->stage === NotificationMailingStage::FAILED
            || ($mailing->stage === NotificationMailingStage::READY && !$thenSend)
        ) {
            CollectMailingRecipientsJob::dispatch($mailing->getKey(), $thenSend);

            return;
        }

        if (in_array($mailing->stage, [
            NotificationMailingStage::READY,
            NotificationMailingStage::SENDING,
        ], true)) {
            $this->transitionStage($mailing, NotificationMailingStage::SENDING);
            SendMailingRecipientsJob::dispatch($mailing->getKey());
        }
    }

    /**
     * Только переотправить незавершённых получателей (без повторного collect).
     */
    public function resume(UserNotificationMailing $mailing): void
    {
        if ($mailing->stage === NotificationMailingStage::CANCELLED) {
            return;
        }

        $this->transitionStage($mailing, NotificationMailingStage::SENDING);
        SendMailingRecipientsJob::dispatch($mailing->getKey());
    }

    /**
     * Отменить рассылку.
     */
    public function cancel(UserNotificationMailing $mailing): void
    {
        if (!$mailing->isCancellable()) {
            return;
        }

        $this->transitionStage($mailing, NotificationMailingStage::CANCELLED);
    }

    /**
     * Собрать пользователей, у которых для типа включён хотя бы один канал.
     *
     * Логика совпадает с NotificationPreferencesService:
     * - есть is_active=true в preferences → включён;
     * - нет строки для дефолтного канала → канал считается включённым;
     * - все каналы явно выключены → не включаем.
     */
    public function collectRecipients(UserNotificationMailing $mailing): void
    {
        if ($mailing->isTerminal()) {
            return;
        }

        $this->transitionStage($mailing, NotificationMailingStage::COLLECTING);

        try {
            $userModel = $this->userModelClass();
            /** @var Model $userInstance */
            $userInstance = new $userModel;
            $userMorph = $userInstance->getMorphClass();
            $usersTable = $userInstance->getTable();
            $userKey = $userInstance->getKeyName();
            $preferencesTable = config('user-notification.preferences_table', 'user_notification_preferences');
            $typeValue = $mailing->notification_type;
            $defaultChannelValues = $this->defaultChannelValuesForType($typeValue);
            $recipientRoles = data_get($mailing->meta, 'recipient_roles');

            $usersQuery = $userModel::query();

            if (is_array($recipientRoles) && $recipientRoles !== [] && method_exists($userModel, 'scopeRole')) {
                $usersQuery->role($recipientRoles);
            }

            $userIds = $usersQuery
                ->where(function ($query) use ($preferencesTable, $typeValue, $defaultChannelValues, $usersTable, $userKey) {
                    $query->whereExists(function ($active) use ($preferencesTable, $typeValue, $usersTable, $userKey) {
                        $active->selectRaw('1')
                            ->from($preferencesTable)
                            ->whereColumn("{$preferencesTable}.user_id", "{$usersTable}.{$userKey}")
                            ->where('type', $typeValue)
                            ->where('is_active', true);
                    });

                    foreach ($defaultChannelValues as $channelValue) {
                        $query->orWhereNotExists(function ($optOut) use (
                            $preferencesTable,
                            $typeValue,
                            $channelValue,
                            $usersTable,
                            $userKey,
                        ) {
                            $optOut->selectRaw('1')
                                ->from($preferencesTable)
                                ->whereColumn("{$preferencesTable}.user_id", "{$usersTable}.{$userKey}")
                                ->where('type', $typeValue)
                                ->where('channel', $channelValue);
                        });
                    }
                })
                ->orderBy($userKey)
                ->pluck($userKey);

            $channelCounts = [];
            $registeredChannels = NotificationRegistry::getChannels()
                ->keyBy(fn (NotificationChannelEnum $channel) => (int) $channel->getValue());

            foreach ($userIds->chunk(500) as $chunk) {
                $prefsByUser = DB::table($preferencesTable)
                    ->where('type', $typeValue)
                    ->whereIn('user_id', $chunk->all())
                    ->get(['user_id', 'channel', 'is_active'])
                    ->groupBy('user_id');

                $rows = [];
                $now = now();

                foreach ($chunk as $userId) {
                    $plannedChannelValues = $this->resolvePlannedChannelValues(
                        $prefsByUser->get($userId),
                        $defaultChannelValues,
                        $registeredChannels->keys()->all(),
                    );

                    foreach ($plannedChannelValues as $channelValue) {
                        $channelCounts[$channelValue] = ($channelCounts[$channelValue] ?? 0) + 1;
                    }

                    $rows[] = [
                        'mailing_id' => $mailing->getKey(),
                        'notifiable_type' => $userMorph,
                        'notifiable_id' => $userId,
                        'status' => NotificationMailingRecipientStatus::PENDING->value,
                        'meta' => json_encode([
                            'planned_channels' => $plannedChannelValues,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                UserNotificationMailingRecipient::query()->upsert(
                    $rows,
                    ['mailing_id', 'notifiable_type', 'notifiable_id'],
                    ['updated_at', 'meta']
                );
            }

            $plannedChannels = [];
            foreach ($channelCounts as $channelValue => $count) {
                $channel = $registeredChannels->get((int) $channelValue);
                $key = $channel instanceof NotificationChannelEnum
                    ? $channel->getName()
                    : (string) $channelValue;
                $plannedChannels[$key] = $count;
            }

            ksort($plannedChannels);

            $mailing->meta = array_merge($mailing->meta ?? [], [
                'recipients_count' => $userIds->count(),
                'planned_channels' => $plannedChannels,
            ]);
            $mailing->save();

            $this->transitionStage($mailing, NotificationMailingStage::READY);
        } catch (Throwable $exception) {
            $mailing->failed_at = now();
            $mailing->save();
            $this->transitionStage($mailing, NotificationMailingStage::FAILED);

            throw $exception;
        }
    }

    /**
     * Каналы, которые уйдут пользователю при текущих preferences/дефолтах.
     *
     * @param  \Illuminate\Support\Collection<int, object>|null  $userPrefs
     * @param  list<int>  $defaultChannelValues
     * @param  list<int>  $registeredChannelValues
     * @return list<int>
     */
    protected function resolvePlannedChannelValues(
        $userPrefs,
        array $defaultChannelValues,
        array $registeredChannelValues,
    ): array {
        $prefsByChannel = collect($userPrefs ?? [])->keyBy(
            fn ($pref) => (int) $pref->channel
        );

        $planned = [];

        foreach ($registeredChannelValues as $channelValue) {
            $channelValue = (int) $channelValue;

            if ($prefsByChannel->has($channelValue)) {
                if ((bool) $prefsByChannel->get($channelValue)->is_active) {
                    $planned[] = $channelValue;
                }

                continue;
            }

            if (in_array($channelValue, $defaultChannelValues, true)) {
                $planned[] = $channelValue;
            }
        }

        return $planned;
    }

    /**
     * @return list<int>
     */
    protected function defaultChannelValuesForType(int|string|null $typeValue): array
    {
        $type = NotificationRegistry::getTypes()->first(
            fn (NotificationTypeEnum $item) => $item->getValue() == $typeValue
        );

        if (!$type instanceof NotificationTypeEnum) {
            return [];
        }

        return array_values(array_map(
            static fn (NotificationChannelEnum $channel) => (int) $channel->getValue(),
            $type->getDefaultChannels(),
        ));
    }

    /**
     * Отправить одну пачку получателей.
     *
     * @return bool true — остались ещё получатели
     */
    public function sendRecipientsChunk(UserNotificationMailing $mailing): bool
    {
        if ($mailing->isTerminal()) {
            return false;
        }

        if ($mailing->stage !== NotificationMailingStage::SENDING) {
            $this->transitionStage($mailing, NotificationMailingStage::SENDING);
        }

        $chunkSize = (int) config('user-notification.mailing.chunk_size', 100);

        $recipients = UserNotificationMailingRecipient::query()
            ->where('mailing_id', $mailing->getKey())
            ->pendingRestart()
            ->orderBy('id')
            ->limit($chunkSize)
            ->get();

        if ($recipients->isEmpty()) {
            $this->finishIfDone($mailing);

            return false;
        }

        foreach ($recipients as $recipient) {
            $freshMailing = $mailing->fresh();
            if (!$freshMailing || $freshMailing->stage === NotificationMailingStage::CANCELLED) {
                return false;
            }

            $this->sendToRecipient($freshMailing, $recipient);
        }

        $hasMore = UserNotificationMailingRecipient::query()
            ->where('mailing_id', $mailing->getKey())
            ->pendingRestart()
            ->exists();

        if (!$hasMore) {
            $this->finishIfDone($mailing->fresh());
        }

        return $hasMore;
    }

    protected function sendToRecipient(
        UserNotificationMailing $mailing,
        UserNotificationMailingRecipient $recipient,
    ): void {
        $recipient->status = NotificationMailingRecipientStatus::SENDING;
        $recipient->last_attempt_at = now();
        $recipient->save();

        try {
            $notifiable = $recipient->notifiable;
            if (!$notifiable instanceof Model) {
                throw new \RuntimeException('Mailing recipient notifiable not found');
            }

            $notification = $this->makeNotification($mailing);
            $notification->forMailing($mailing);

            $existingLog = UserNotificationLog::query()
                ->where('mailing_id', $mailing->getKey())
                ->where('notifiable_type', $recipient->notifiable_type)
                ->where('notifiable_id', $recipient->notifiable_id)
                ->latest('id')
                ->first();

            if ($existingLog) {
                $notification->setLogId($existingLog->getKey());

                $unfinishedChannelClasses = $this->unfinishedChannelClasses($existingLog);

                // Все каналы уже Sent/Skipped — только синхронизируем статус получателя.
                if ($unfinishedChannelClasses === []) {
                    $this->refreshRecipientStatus($recipient->fresh() ?? $recipient);

                    return;
                }

                // Повторно шлём только незавершённые каналы (не дублируем Sent).
                $notification->setChannels($unfinishedChannelClasses);
            }

            $notifiable->notify($notification);

            $recipient->status = NotificationMailingRecipientStatus::DISPATCHED;
            $recipient->save();

            $this->refreshRecipientStatus($recipient->fresh() ?? $recipient);
        } catch (Throwable $exception) {
            $recipient->status = NotificationMailingRecipientStatus::FAILED;
            $recipient->meta = array_merge($recipient->meta ?? [], [
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
            $recipient->save();
        }
    }

    /**
     * Классы каналов со статусом Queued/Sending/Failed — их можно (пере)отправить.
     *
     * @return list<class-string>
     */
    protected function unfinishedChannelClasses(UserNotificationLog $log): array
    {
        $classes = [];

        foreach ($log->channels as $row) {
            if (!in_array($row->status, [
                NotificationLogChannelStatus::QUEUED,
                NotificationLogChannelStatus::SENDING,
                NotificationLogChannelStatus::FAILED,
            ], true)) {
                continue;
            }

            $channel = $row->channel;
            if (!$channel instanceof NotificationChannelEnum) {
                $raw = $row->getAttributes()['channel'] ?? null;
                $channel = NotificationRegistry::getChannels()->first(
                    fn (NotificationChannelEnum $item) => $item->getValue() == $raw
                );
            }

            if ($channel instanceof NotificationChannelEnum) {
                $classes[] = $channel->getChannelClassName();
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @return UserNotification&CreatesFromMailing
     */
    public function makeNotification(UserNotificationMailing $mailing): UserNotification
    {
        $class = $mailing->notification_class;

        if (!is_subclass_of($class, UserNotification::class)) {
            throw new \InvalidArgumentException("{$class} must extend UserNotification");
        }

        if (!is_subclass_of($class, CreatesFromMailing::class)) {
            throw new \InvalidArgumentException(
                "{$class} must implement ".CreatesFromMailing::class.' to be used in mailings'
            );
        }

        /** @var class-string<CreatesFromMailing&UserNotification> $class */
        return $class::fromMailing($mailing);
    }

    /**
     * Пересчитать status получателя по его delivery log_channels.
     * При терминальном статусе получателя — проверить, можно ли закрыть рассылку.
     */
    public function refreshRecipientStatus(UserNotificationMailingRecipient $recipient): void
    {
        $log = UserNotificationLog::query()
            ->where('mailing_id', $recipient->mailing_id)
            ->where('notifiable_type', $recipient->notifiable_type)
            ->where('notifiable_id', $recipient->notifiable_id)
            ->latest('id')
            ->first();

        if (!$log) {
            return;
        }

        $channels = $log->channels;
        if ($channels->isEmpty()) {
            return;
        }

        $statuses = $channels->pluck('status');

        $allSent = $statuses->every(
            fn ($status) => $status === NotificationLogChannelStatus::SENT
                || $status === NotificationLogChannelStatus::SKIPPED
        );
        $anySent = $statuses->contains(NotificationLogChannelStatus::SENT);
        $anyFailed = $statuses->contains(NotificationLogChannelStatus::FAILED);
        $anyPending = $statuses->contains(fn ($status) => in_array($status, [
            NotificationLogChannelStatus::QUEUED,
            NotificationLogChannelStatus::SENDING,
        ], true));

        if ($allSent) {
            $recipient->status = NotificationMailingRecipientStatus::COMPLETED;
        } elseif ($anyPending) {
            $recipient->status = NotificationMailingRecipientStatus::DISPATCHED;
        } elseif ($anySent && $anyFailed) {
            $recipient->status = NotificationMailingRecipientStatus::PARTIAL;
        } elseif ($anyFailed && !$anySent) {
            $recipient->status = NotificationMailingRecipientStatus::FAILED;
        } else {
            $recipient->status = NotificationMailingRecipientStatus::PARTIAL;
        }

        $recipient->save();

        $mailing = $recipient->mailing;
        if ($mailing && !$mailing->isTerminal()) {
            $this->finishIfDone($mailing);
        }
    }

    /**
     * Пересчитать статусы всех получателей рассылки по логам (для уже ушедших рассылок).
     */
    public function refreshAllRecipientStatuses(UserNotificationMailing $mailing): void
    {
        UserNotificationMailingRecipient::query()
            ->where('mailing_id', $mailing->getKey())
            ->orderBy('id')
            ->chunkById(200, function ($recipients) {
                foreach ($recipients as $recipient) {
                    $this->refreshRecipientStatus($recipient);
                }
            });
    }

    public function transitionStage(
        UserNotificationMailing $mailing,
        NotificationMailingStage $stage,
    ): UserNotificationMailing {
        $previous = $mailing->stage;

        if ($previous === $stage) {
            return $mailing;
        }

        $mailing->stage = $stage;
        $mailing->status = $this->statusForStage($stage);

        if ($stage === NotificationMailingStage::FAILED) {
            $mailing->failed_at = $mailing->failed_at ?? now();
        }

        if ($stage === NotificationMailingStage::COMPLETED) {
            $mailing->failed_at = null;
        }

        $mailing->save();

        $this->broadcastStageChanged($mailing->fresh() ?? $mailing, $previous);

        return $mailing;
    }

    /**
     * WS-уведомление не должно ломать рассылку, если Pusher/Soketi недоступен.
     */
    protected function broadcastStageChanged(
        UserNotificationMailing $mailing,
        ?NotificationMailingStage $previous,
    ): void {
        if (!config('user-notification.mailing.broadcast', true)) {
            return;
        }

        try {
            broadcast(new UserNotificationMailingStageChanged($mailing, $previous));
        } catch (Throwable $exception) {
            Log::warning('UserNotification: mailing stage broadcast failed', [
                'mailing_id' => $mailing->getKey(),
                'stage' => $mailing->stage?->name,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function statusForStage(NotificationMailingStage $stage): NotificationMailingStatus
    {
        return match ($stage) {
            NotificationMailingStage::DRAFT => NotificationMailingStatus::DRAFT,
            NotificationMailingStage::COLLECTING,
            NotificationMailingStage::SENDING => NotificationMailingStatus::PROCESSING,
            NotificationMailingStage::READY => NotificationMailingStatus::PENDING,
            NotificationMailingStage::COMPLETED => NotificationMailingStatus::COMPLETED,
            NotificationMailingStage::FAILED => NotificationMailingStatus::FAILED,
            NotificationMailingStage::CANCELLED => NotificationMailingStatus::CANCELLED,
        };
    }

    /**
     * Рассылка завершена, когда у всех получателей нет «открытых» статусов:
     * PENDING/SENDING (ещё не вызвали / вызываем notify) и DISPATCHED (ждём каналы).
     * COMPLETED/PARTIAL/FAILED — финал по получателю.
     */
    protected function finishIfDone(?UserNotificationMailing $mailing): void
    {
        if (!$mailing || $mailing->isTerminal()) {
            return;
        }

        $hasOpen = UserNotificationMailingRecipient::query()
            ->where('mailing_id', $mailing->getKey())
            ->whereIn('status', [
                NotificationMailingRecipientStatus::PENDING->value,
                NotificationMailingRecipientStatus::SENDING->value,
                NotificationMailingRecipientStatus::DISPATCHED->value,
            ])
            ->exists();

        if ($hasOpen) {
            return;
        }

        $this->transitionStage($mailing, NotificationMailingStage::COMPLETED);
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        $userClass = config('user-notification.user_model', 'App\\Models\\User');

        if (!$userClass || !class_exists($userClass)) {
            throw new \RuntimeException('user-notification.user_model is not configured');
        }

        return $userClass;
    }
}
