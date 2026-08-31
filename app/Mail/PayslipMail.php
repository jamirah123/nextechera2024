<?php

namespace App\Mail;

use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollRun $run,
        public PayrollPayslip $payslip,
        public string $pdfBinary,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payslip '.$this->run->reference.' — '.$this->payslip->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payslip',
            with: [
                'run' => $this->run,
                'payslip' => $this->payslip,
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinary, 'payslip-'.$this->payslip->employment_id.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
