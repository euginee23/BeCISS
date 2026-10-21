<?php

/*
|--------------------------------------------------------------------------
| SMS Templates
|--------------------------------------------------------------------------
|
| Preset texts for the "Send SMS" composer, grouped by the page they are
| sent from. Staff can edit the text before sending.
|
| Placeholders: :name (resident's first name), :barangay, :reference,
| :service, :fee, :date, :time, :reason.
|
| Keep them short and plain ASCII. IPROG adds the account's sender name in
| front of every text, and a single non-GSM character (the peso sign, curly
| quotes, an em dash) turns the whole message into Unicode, which cuts a
| billed text from 160 characters to 70. Write "PHP", not the peso sign.
|
*/

return [

    'certificate' => [
        'payment_due' => [
            'label' => 'Approved, payment needed',
            'body' => 'Hi :name, your :service (:reference) is approved. Please pay PHP :fee at the :barangay hall.',
        ],
        'processing' => [
            'label' => 'Being processed',
            'body' => 'Hi :name, your :service (:reference) is being processed. We will text you when it is ready.',
        ],
        'ready' => [
            'label' => 'Ready for pickup',
            'body' => 'Hi :name, your :service (:reference) is ready for pickup at the :barangay hall.',
        ],
        'rejected' => [
            'label' => 'Request not approved',
            'body' => 'Hi :name, your :service request (:reference) was not approved. Reason: :reason',
        ],
    ],

    'blotter' => [
        'processing' => [
            'label' => 'Being processed',
            'body' => 'Hi :name, your blotter report :reference is being processed by the :barangay office.',
        ],
        'payment_due' => [
            'label' => 'Payment needed',
            'body' => 'Hi :name, please pay the PHP :fee fee for blotter report :reference at the :barangay hall.',
        ],
        'ready' => [
            'label' => 'Ready for pickup',
            'body' => 'Hi :name, your blotter report :reference is ready for pickup at the :barangay hall.',
        ],
    ],

    'appointment' => [
        'confirmed' => [
            'label' => 'Appointment confirmed',
            'body' => 'Hi :name, your :service appointment on :date at :time is confirmed. Ref :reference.',
        ],
        'reminder' => [
            'label' => 'Appointment reminder',
            'body' => 'Reminder: Hi :name, you have a :service appointment at the :barangay hall on :date, :time.',
        ],
        'cancelled' => [
            'label' => 'Appointment cancelled',
            'body' => 'Hi :name, your :service appointment on :date (:reference) was cancelled.',
        ],
    ],

    'resident' => [
        'visit' => [
            'label' => 'Please visit the hall',
            'body' => 'Hi :name, please visit the :barangay hall at your earliest convenience. Thank you.',
        ],
        'update_details' => [
            'label' => 'Update your details',
            'body' => 'Hi :name, please visit the :barangay hall to update your resident record. Thank you.',
        ],
    ],

];
