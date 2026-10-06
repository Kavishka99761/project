<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Visual severity of a notification.
 */
enum NotificationType: string
{
    use HasOptions;

    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Reminder = 'reminder';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Success => 'Success',
            self::Warning => 'Warning',
            self::Danger => 'Alert',
            self::Reminder => 'Reminder',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Info => ['icon' => 'info-circle'],
            self::Success => ['icon' => 'check-circle'],
            self::Warning => ['icon' => 'exclamation-triangle'],
            self::Danger => ['icon' => 'exclamation-octagon'],
            self::Reminder => ['icon' => 'bell'],
        };
    }
}
