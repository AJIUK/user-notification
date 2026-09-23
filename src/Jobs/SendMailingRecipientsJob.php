<?php

namespace UserNotification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use UserNotification\Services\NotificationMailingService;

/**
 * Отправить пачку получателей рассылки и при необходимости поставить следующий chunk.
 */
class SendMailingRecipientsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $mailingId,
    ) {
        $this->onQueue(config('user-notification.mailing.queue', config('user-notification.default_queue', 'default')));
    }

    public function handle(NotificationMailingService $mailings): void
    {
        $mailing = $mailings->findOrFail($this->mailingId);

        if ($mailing->isTerminal()) {
            return;
        }

        $hasMore = $mailings->sendRecipientsChunk($mailing);

        if ($hasMore) {
            static::dispatch($this->mailingId);
        }
    }
}
