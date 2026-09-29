<?php

namespace App\Support\Notifications;

class EmailFailureMessage
{
    public static function sanitize(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        if ($message === '' || preg_match('/password|credential|token|SQLSTATE|stack trace|vendor\\\\|\.env|smtp/i', $message) === 1) {
            return 'The mail service could not deliver this message.';
        }

        return mb_substr($message, 0, 255);
    }
}
