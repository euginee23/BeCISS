<?php

namespace App\Services\Sms;

use App\Models\Appointment;
use App\Models\BarangayProfile;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns the presets in config/sms-templates.php into ready-to-edit text for
 * the "Send SMS" composer.
 */
class SmsTemplates
{
    /**
     * Template keys and labels for one page, for the picker.
     *
     * @return array<string, string>
     */
    public static function options(string $context): array
    {
        return collect(config("sms-templates.{$context}", []))
            ->map(fn (array $template, string $key): string => $template['label'] ?? $key)
            ->all();
    }

    public static function exists(string $context, string $key): bool
    {
        return is_array(config("sms-templates.{$context}.{$key}"));
    }

    /**
     * Fill one template for one record. Unknown placeholders are left as
     * they are, so a typo shows in the composer instead of sending a gap.
     */
    public static function render(string $context, string $key, Model $record): string
    {
        $body = (string) config("sms-templates.{$context}.{$key}.body", '');

        $replacements = [];

        foreach (self::placeholders($record) as $name => $value) {
            $replacements[':'.$name] = $value;
        }

        return strtr($body, $replacements);
    }

    /**
     * Placeholder values for a record. Longest names come first so ":name"
     * cannot clobber a longer placeholder that starts the same way.
     *
     * @return array<string, string>
     */
    public static function placeholders(Model $record): array
    {
        $resident = $record instanceof Resident ? $record : $record->resident;

        $values = [
            'barangay' => (string) BarangayProfile::get()->barangay_name,
            'name' => (string) ($resident?->first_name ?? ''),
        ];

        $values += match (true) {
            $record instanceof Certificate => [
                'reference' => (string) $record->certificate_number,
                'service' => $record->type_label,
                'fee' => number_format((float) $record->fee, 2),
                'reason' => (string) ($record->rejection_reason ?? ''),
            ],
            $record instanceof Blotter => [
                'reference' => (string) $record->blotter_number,
                'service' => __('Blotter Report'),
                'fee' => number_format((float) $record->fee, 2),
                'reason' => (string) ($record->rejection_reason ?? ''),
            ],
            $record instanceof Appointment => [
                'reference' => (string) $record->reference_number,
                'service' => $record->service_type_label,
                'fee' => number_format($record->serviceFee(), 2),
                'date' => $record->appointment_date->format('M j, Y'),
                'time' => $record->appointment_time->format('g:i A'),
                'reason' => (string) ($record->cancellation_reason ?? ''),
            ],
            default => [],
        };

        uksort($values, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $values;
    }
}
