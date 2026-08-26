<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature = 'psg:test-mail {email : Recipient address for the test message}';

    protected $description = 'Send a test email to verify SMTP / mail configuration';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid email address.');

            return self::FAILURE;
        }

        $company = config('psg.company', config('app.name'));
        $mailer = config('mail.default');

        try {
            Mail::raw(
                "This is a test message from {$company} Shifts.\n\nMailer: {$mailer}\nTime: ".now()->toDateTimeString(),
                function ($message) use ($email, $company): void {
                    $message->to($email)
                        ->subject('PSG Shifts mail test — '.$company);
                }
            );
        } catch (\Throwable $e) {
            $this->error('Mail send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Test message sent to {$email} via [{$mailer}].");

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log — check storage/logs/laravel.log instead of an inbox.');
        }

        return self::SUCCESS;
    }
}
