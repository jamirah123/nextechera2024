<?php

namespace Tests\Feature\Production;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_mail_command_succeeds_with_log_mailer(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('psg:test-mail', ['email' => 'verify@example.com'])
            ->expectsOutputToContain('Test message sent to verify@example.com')
            ->expectsOutputToContain('log')
            ->assertSuccessful();
    }

    public function test_test_mail_command_rejects_invalid_email(): void
    {
        $this->artisan('psg:test-mail', ['email' => 'not-an-email'])
            ->expectsOutputToContain('valid email')
            ->assertFailed();
    }

    public function test_password_reset_notification_uses_company_branding(): void
    {
        config(['psg.company' => 'Platinum Security Uganda']);

        $notification = new ResetPasswordNotification('sample-token');
        $user = User::factory()->make(['name' => 'Jane Ops', 'email' => 'jane@example.com']);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('Platinum Security Uganda', $mail->subject);
        $this->assertStringContainsString('Platinum Security Uganda', implode("\n", $mail->introLines));
    }
}
