<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResidentNotification extends Notification
{
    use Queueable;

    /**
     * Icon and colour per notification type, shared by the bell and the
     * notifications page so the two cannot drift apart.
     *
     * @var array<string, array{icon: string, color: string, bg: string}>
     */
    public const array ICONS = [
        'registration_approved' => ['icon' => 'check-circle', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30'],
        'registration_rejected' => ['icon' => 'x-circle', 'color' => 'text-red-500', 'bg' => 'bg-red-100 dark:bg-red-900/30'],
        'certificate_processing' => ['icon' => 'cog-6-tooth', 'color' => 'text-blue-500', 'bg' => 'bg-blue-100 dark:bg-blue-900/30'],
        'certificate_ready' => ['icon' => 'document-check', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30'],
        'certificate_completed' => ['icon' => 'check-badge', 'color' => 'text-green-600', 'bg' => 'bg-green-100 dark:bg-green-900/30'],
        'certificate_rejected' => ['icon' => 'x-circle', 'color' => 'text-red-500', 'bg' => 'bg-red-100 dark:bg-red-900/30'],
        'blotter_processing' => ['icon' => 'cog-6-tooth', 'color' => 'text-blue-500', 'bg' => 'bg-blue-100 dark:bg-blue-900/30'],
        'blotter_ready' => ['icon' => 'document-check', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30'],
        'blotter_completed' => ['icon' => 'check-badge', 'color' => 'text-green-600', 'bg' => 'bg-green-100 dark:bg-green-900/30'],
        'blotter_rejected' => ['icon' => 'x-circle', 'color' => 'text-red-500', 'bg' => 'bg-red-100 dark:bg-red-900/30'],
        'appointment_requested' => ['icon' => 'calendar-days', 'color' => 'text-amber-500', 'bg' => 'bg-amber-100 dark:bg-amber-900/30'],
        'appointment_booked' => ['icon' => 'calendar', 'color' => 'text-blue-500', 'bg' => 'bg-blue-100 dark:bg-blue-900/30'],
        'appointment_confirmed' => ['icon' => 'calendar-days', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30'],
        'appointment_completed' => ['icon' => 'check-circle', 'color' => 'text-green-600', 'bg' => 'bg-green-100 dark:bg-green-900/30'],
        'appointment_cancelled' => ['icon' => 'x-circle', 'color' => 'text-red-500', 'bg' => 'bg-red-100 dark:bg-red-900/30'],
        'appointment_no_show' => ['icon' => 'no-symbol', 'color' => 'text-amber-500', 'bg' => 'bg-amber-100 dark:bg-amber-900/30'],
    ];

    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
    ) {}

    /**
     * Get the icon styling for a notification type.
     *
     * @return array{icon: string, color: string, bg: string}
     */
    public static function iconFor(?string $type): array
    {
        return self::ICONS[$type] ?? [
            'icon' => 'bell',
            'color' => 'text-zinc-400',
            'bg' => 'bg-zinc-100 dark:bg-zinc-800',
        ];
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
