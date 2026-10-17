<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent after a password actually changes. Real services always send this: it
 * is how the owner finds out if someone else reset their account.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $when,
        public readonly ?string $ip = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your AcadeAlert password was changed');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.password-changed');
    }
}
