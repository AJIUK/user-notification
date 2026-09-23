<?php

namespace UserNotification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use UserNotification\Services\NotificationMailingService;

/**
 * Собрать состав рассылки: активные preferences типа или дефолтные каналы без opt-out.
 */
class CollectMailingRecipientsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $mailingId,
        public bool $thenSend = true,
    ) {
        $this->onQueue(config('user-notification.mailing.queue', config('user-notification.default_queue', 'default')));
    }

    public function handle(NotificationMailingService $mailings): void
    {
        $mailing = $mailings->findOrFail($this->mailingId);
        $mailings->collectRecipients($mailing);

        if ($this->thenSend && !$mailing->fresh()->isTerminal()) {
            SendMailingRecipientsJob::dispatch($this->mailingId);
        }
    }
}
