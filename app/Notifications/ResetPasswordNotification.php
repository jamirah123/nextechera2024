<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $company = config('psg.company', config('app.name'));
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset your password — '.$company)
            ->greeting('Hello '.$notifiable->name)
            ->line('You requested a password reset for your '.$company.' management account.')
            ->action('Reset password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes.')
            ->line('If you did not request a reset, you can safely ignore this email.');
    }
}
