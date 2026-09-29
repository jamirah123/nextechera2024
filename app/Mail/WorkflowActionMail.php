<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class WorkflowActionMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  list<string>  $details
     */
    public function __construct(
        public string $headline,
        public string $summary,
        public ?string $actorName,
        public ?string $actionUrl,
        public string $actionLabel,
        public array $details = [],
        public string $priority = 'normal',
        public ?int $deliveryId = null,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        $support = (string) config('psg.support_email', '');

        if (filter_var($support, FILTER_VALIDATE_EMAIL)) {
            $replyTo[] = new Address($support, (string) config('psg.company', config('app.name')));
        }

        return new Envelope(
            subject: $this->headline.' — '.config('app.name'),
            replyTo: $replyTo,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-PSG-Delivery' => (string) ($this->deliveryId ?? ''),
                'X-PSG-Priority' => $this->priority,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.workflow-action',
            with: [
                'headline' => $this->headline,
                'summary' => $this->summary,
                'actorName' => $this->actorName,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'details' => $this->details,
                'priority' => $this->priority,
                'priorityLabel' => match ($this->priority) {
                    'critical' => 'Critical',
                    'important' => 'Important',
                    default => 'Notice',
                },
                'priorityTone' => match ($this->priority) {
                    'critical' => 'critical',
                    'important' => 'warning',
                    default => 'info',
                },
            ],
        );
    }
}
