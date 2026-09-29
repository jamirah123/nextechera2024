<?php

namespace App\Support\Notifications;

use App\Mail\WorkflowActionMail;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;

class QueuedWorkflowMail
{
    public static function fromJob(JobFailed|JobProcessing $event): ?WorkflowActionMail
    {
        $name = (string) ($event->job->payload()['displayName'] ?? '');

        if (! str_contains($name, 'WorkflowActionMail')) {
            return null;
        }

        $command = $event->job->payload()['data']['command'] ?? null;

        if (! is_string($command)) {
            return null;
        }

        try {
            $job = unserialize($command, ['allowed_classes' => true]);
        } catch (\Throwable) {
            return null;
        }

        if (! $job instanceof SendQueuedMailable || ! $job->mailable instanceof WorkflowActionMail) {
            return null;
        }

        return $job->mailable;
    }
}
