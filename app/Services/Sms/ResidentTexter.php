<?php

namespace App\Services\Sms;

use App\Facades\Sms;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Texts a resident about one of their records and logs what was sent.
 *
 * The activity log entry is written only after the send succeeds, so it
 * answers "what was the resident actually told" rather than "what was tried".
 */
class ResidentTexter
{
    /**
     * @throws RuntimeException When there is nothing to send to, or the provider refuses.
     */
    public function send(Resident $resident, string $body, Model $about, ?string $template = null): string
    {
        $body = trim($body);

        if ($body === '') {
            throw new RuntimeException(__('Cannot send an empty message.'));
        }

        $number = $resident->smsNumber();

        if ($number === null) {
            throw new RuntimeException(__('This resident has no mobile number on file.'));
        }

        Sms::send($number, $body);

        [$module, $reference] = $this->describe($about);

        ActivityLog::record(
            module: $module,
            action: 'sms_sent',
            subject: $about,
            description: 'Sent SMS to '.$number.' — '.$reference.'.',
            properties: [
                'to' => $number,
                'template' => $template,
                'body' => $body,
                'length' => mb_strlen($body),
            ],
        );

        return $number;
    }

    /**
     * The activity log module and a readable reference for the record.
     *
     * @return array{0: string, 1: string}
     */
    private function describe(Model $about): array
    {
        return match (true) {
            $about instanceof Certificate => ['certificates', $about->type_label.' ('.$about->certificate_number.')'],
            $about instanceof Blotter => ['blotters', 'blotter '.$about->blotter_number],
            $about instanceof Appointment => ['appointments', $about->service_type_label.' ('.$about->reference_number.')'],
            $about instanceof Resident => ['residents', $about->full_name],
            default => ['sms', class_basename($about).' #'.$about->getKey()],
        };
    }
}
