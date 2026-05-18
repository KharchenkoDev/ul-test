<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\OutboxMessage;
use Illuminate\Console\Command;

class ProcessOutboxCommand extends Command
{
    protected $signature   = 'outbox:process';
    protected $description = 'Dispatch jobs for outbox records that survived a process crash';

    public function handle(): void
    {
        OutboxMessage::with('notification')
            ->orderBy('created_at')
            ->get()
            ->each(function (OutboxMessage $outbox): void {
                dispatch(new SendNotificationJob($outbox->notification));
                $outbox->delete();
            });
    }
}
