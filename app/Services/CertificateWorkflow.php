<?php

namespace App\Services;

use App\Mail\CertificateApproved;
use App\Mail\CertificateReadyForPickup;
use App\Mail\CertificateRejected;
use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\ResidentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Moves certificates through request → approval → payment → processing →
 * release, guarding every transition and sending the matching notices.
 */
class CertificateWorkflow
{
    /**
     * Notify staff who process certificates that a resident filed a request.
     */
    public function submitted(Certificate $certificate): void
    {
        User::withPermission('certificates')->each(fn (User $staff) => $staff->notify(new ResidentNotification(
            type: 'certificate_requested',
            title: 'New Certificate Request',
            body: $certificate->requester_name.' requested a '.$certificate->type_label.' ('.$certificate->certificate_number.').',
            url: route('certificates.show', $certificate),
        )));
    }

    /**
     * Approve a pending request. Paid types wait for payment; free ones go
     * straight to processing.
     */
    public function approve(Certificate $certificate, User $staff): void
    {
        $nextStatus = $certificate->requiresPayment() ? 'awaiting_payment' : 'processing';

        $this->ensureCan($certificate, $nextStatus);

        $certificate->update([
            'status' => $nextStatus,
            'approved_at' => now(),
            ...($nextStatus === 'processing' ? ['processed_by' => $staff->id, 'processed_at' => now()] : []),
        ]);

        $this->log($certificate, 'approved', 'Approved the request');

        if ($nextStatus === 'awaiting_payment') {
            $this->mailResident($certificate, CertificateApproved::class);
            $this->notifyResident(
                $certificate,
                type: 'certificate_approved',
                title: 'Certificate Approved — Payment Needed',
                body: 'Your '.$certificate->type_label.' ('.$certificate->certificate_number.') was approved. Please pay ₱'.number_format((float) $certificate->fee, 2).' at the barangay hall. You can book a visit from My Certificates.',
            );

            return;
        }

        $this->log($certificate, 'processing', 'Started processing');
        $this->notifyResident(
            $certificate,
            type: 'certificate_processing',
            title: 'Certificate Being Processed',
            body: 'Your '.$certificate->type_label.' ('.$certificate->certificate_number.') was approved and is now being processed.',
        );
    }

    /**
     * Record the fee. A request awaiting payment moves on to processing;
     * one already further along (paid at release under the old flow) keeps
     * its status so it can be completed.
     */
    public function recordPayment(Certificate $certificate, string $orNumber, User $cashier, ?string $remarks = null): Payment
    {
        $payment = DB::transaction(function () use ($certificate, $orNumber, $cashier, $remarks): Payment {
            /**
             * Re-read under a row lock so a double submit or two cashiers at
             * once cannot both record a payment.
             */
            $locked = Certificate::query()->lockForUpdate()->findOrFail($certificate->id);

            if (! $locked->canTakePayment()) {
                throw ValidationException::withMessages(['status' => __('This request has no payment due.')]);
            }

            if (Payment::orNumberTaken($orNumber)) {
                throw ValidationException::withMessages(['orNumber' => __('This OR number has already been used.')]);
            }

            $payment = $locked->recordPayment($orNumber, $cashier, $remarks);

            if ($locked->status === 'awaiting_payment') {
                $locked->update([
                    'status' => 'processing',
                    'processed_by' => $cashier->id,
                    'processed_at' => now(),
                ]);
            }

            return $payment;
        });

        $certificate->refresh();

        $this->log(
            $certificate,
            'paid',
            'Recorded payment of ₱'.number_format((float) $payment->amount, 2).' under OR '.$orNumber,
            ['or_number' => $orNumber, 'fee' => (float) $payment->amount],
        );

        $this->notifyResident(
            $certificate,
            type: 'certificate_paid',
            title: 'Payment Received',
            body: 'We received ₱'.number_format((float) $payment->amount, 2).' (OR '.$orNumber.') for your '.$certificate->type_label.' ('.$certificate->certificate_number.').'
                .($certificate->status === 'processing' ? ' It is now being processed.' : ''),
        );

        return $payment;
    }

    /**
     * Save the issuance details printed on the certificate and mark it ready.
     *
     * @param  array{issued_at: string, ctc_number?: ?string, ctc_place_issued?: ?string, ctc_date_issued?: ?string}  $issuance
     */
    public function markReady(Certificate $certificate, array $issuance): void
    {
        $this->ensureCan($certificate, 'ready_for_pickup');
        $this->ensurePaid($certificate);

        $certificate->update([
            'status' => 'ready_for_pickup',
            'issued_at' => $issuance['issued_at'],
            'ctc_number' => $issuance['ctc_number'] ?? null,
            'ctc_place_issued' => $issuance['ctc_place_issued'] ?? null,
            'ctc_date_issued' => $issuance['ctc_date_issued'] ?? null,
        ]);

        $this->log($certificate, 'ready', 'Marked ready for pickup');
        $this->mailResident($certificate, CertificateReadyForPickup::class);
        $this->notifyResident(
            $certificate,
            type: 'certificate_ready',
            title: 'Certificate Ready for Pickup',
            body: 'Your '.$certificate->type_label.' ('.$certificate->certificate_number.') is ready for pickup at the barangay hall.',
        );
    }

    /**
     * Hand the certificate over and close any visit booked for it.
     */
    public function release(Certificate $certificate): void
    {
        $this->ensureCan($certificate, 'completed');
        $this->ensurePaid($certificate);

        $certificate->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->log($certificate, 'completed', 'Released and completed');

        $appointment = $certificate->activeAppointment();

        if ($appointment) {
            $appointment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'handled_by' => auth()->id() ?? $appointment->handled_by,
            ]);

            ActivityLog::record(
                module: 'appointments',
                action: 'completed',
                subject: $appointment,
                description: 'Completed on release of '.$certificate->certificate_number.' — '.$appointment->reference_number.'.',
            );
        }

        $this->notifyResident(
            $certificate,
            type: 'certificate_completed',
            title: 'Certificate Completed',
            body: 'Your '.$certificate->type_label.' ('.$certificate->certificate_number.') has been released and completed.',
        );
    }

    public function reject(Certificate $certificate, string $reason): void
    {
        $this->ensureCan($certificate, 'rejected');

        $certificate->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->log($certificate, 'rejected', 'Rejected. Reason: '.$reason, ['reason' => $reason]);
        $this->cancelVisit($certificate, 'Certificate request was rejected.');
        $this->mailResident($certificate, CertificateRejected::class);
        $this->notifyResident(
            $certificate,
            type: 'certificate_rejected',
            title: 'Certificate Request Rejected',
            body: 'Your '.$certificate->type_label.' ('.$certificate->certificate_number.') was rejected. Reason: '.$reason,
        );
    }

    /**
     * Withdraw a request before payment, by the resident or by staff.
     */
    public function cancel(Certificate $certificate, User $by, ?string $reason = null): void
    {
        $this->ensureCan($certificate, 'cancelled');

        $certificate->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'remarks' => $reason ? trim(($certificate->remarks ? $certificate->remarks."\n" : '').'Cancelled: '.$reason) : $certificate->remarks,
        ]);

        $this->log($certificate, 'cancelled', 'Cancelled'.($reason ? '. Reason: '.$reason : ''), $reason ? ['reason' => $reason] : null);
        $this->cancelVisit($certificate, 'Certificate request was cancelled.');

        if ($by->isResident()) {
            User::withPermission('certificates')->each(fn (User $staff) => $staff->notify(new ResidentNotification(
                type: 'certificate_cancelled',
                title: 'Certificate Request Cancelled',
                body: $certificate->requester_name.' cancelled '.$certificate->type_label.' ('.$certificate->certificate_number.').',
                url: route('certificates.show', $certificate),
            )));

            return;
        }

        $this->notifyResident(
            $certificate,
            type: 'certificate_cancelled',
            title: 'Certificate Request Cancelled',
            body: 'Your '.$certificate->type_label.' ('.$certificate->certificate_number.') was cancelled by the barangay.'.($reason ? ' Reason: '.$reason : ''),
        );
    }

    private function ensureCan(Certificate $certificate, string $status): void
    {
        if (! $certificate->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => __('A :from request cannot be moved to :to.', [
                    'from' => strtolower($certificate->status_label),
                    'to' => strtolower(Certificate::STATUSES[$status] ?? $status),
                ]),
            ]);
        }
    }

    private function ensurePaid(Certificate $certificate): void
    {
        if ($certificate->requiresPayment()) {
            throw ValidationException::withMessages(['status' => __('Record the payment first.')]);
        }
    }

    private function cancelVisit(Certificate $certificate, string $reason): void
    {
        $appointment = $certificate->activeAppointment();

        if (! $appointment) {
            return;
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        ActivityLog::record(
            module: 'appointments',
            action: 'cancelled',
            subject: $appointment,
            description: $reason.' — '.$appointment->reference_number.'.',
        );
    }

    /**
     * @param  array<string, mixed>|null  $properties
     */
    private function log(Certificate $certificate, string $action, string $summary, ?array $properties = null): void
    {
        ActivityLog::record(
            module: 'certificates',
            action: $action,
            subject: $certificate,
            description: $summary.' — '.$certificate->type_label.' ('.$certificate->certificate_number.').',
            properties: $properties,
        );
    }

    /**
     * Email the resident. The status change is already saved, so a mail
     * outage is logged instead of failing the staff member's action.
     *
     * @param  class-string  $mailable
     */
    private function mailResident(Certificate $certificate, string $mailable): void
    {
        $user = $certificate->resident?->user;

        if ($user) {
            rescue(fn () => Mail::to($user->email)->send(new $mailable($user, $certificate)));
        }
    }

    private function notifyResident(Certificate $certificate, string $type, string $title, string $body): void
    {
        $certificate->resident?->user?->notify(new ResidentNotification(
            type: $type,
            title: $title,
            body: $body,
            url: route('resident.certificates.index'),
        ));
    }
}
