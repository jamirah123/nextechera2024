<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowActionMail extends Mailable
{
    use Queueable, SerializesModels;

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
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->headline.' — '.config('app.name'),
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
            ],
        );
    }
}
