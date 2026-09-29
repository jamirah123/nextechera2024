<?php

namespace App\Support\Notifications;

use App\Models\User;

class NotificationPreferences
{
    /** @return list<string> */
    public static function forcedActions(): array
    {
        return [
            'backup.failed',
            'backup.verify_failed',
            'backup.restore_failed',
            'backup.offsite_failed',
        ];
    }

    /**
     * @return array{in_app: bool, email: bool, toasts: bool, operational: bool, hr: bool, finance: bool, system: bool}
     */
    public static function for(User $user): array
    {
        $stored = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        return [
            'in_app' => (bool) ($stored['in_app'] ?? true),
            'email' => (bool) ($stored['email'] ?? true),
            'toasts' => (bool) ($stored['toasts'] ?? true),
            'operational' => (bool) ($stored['operational'] ?? true),
            'hr' => (bool) ($stored['hr'] ?? true),
            'finance' => (bool) ($stored['finance'] ?? true),
            'system' => (bool) ($stored['system'] ?? true),
        ];
    }

    public static function isForced(string $action, string $category, string $severity): bool
    {
        if (in_array($action, self::forcedActions(), true)) {
            return true;
        }

        if (WorkflowActionCatalog::priority($action) === 'critical') {
            return true;
        }

        return $category === 'security' && $severity === 'critical';
    }

    public static function wantsEmail(User $user, string $action, string $category, string $severity): bool
    {
        if (self::isForced($action, $category, $severity)) {
            return true;
        }

        return self::for($user)['email'];
    }
}
