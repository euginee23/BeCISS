<?php

namespace App\Mail;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResidentApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Resident $resident,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registration Approved — BeCISS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resident-approved',
        );
    }
}
