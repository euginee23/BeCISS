<?php

namespace App\Mail;

use App\Models\Resident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewPendingRegistration extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Resident $resident,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Pending Registration — BeCISS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-pending-registration',
        );
    }
}
