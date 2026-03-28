<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $rawToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your sign-in link for PawPass',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
            text: 'emails.magic-link-text',
            with: [
                'loginUrl' => route('magic-link.verify', ['token' => $this->rawToken]),
                'expiresIn' => 15,
                'userName' => $this->user->name,
            ],
        );
    }
}
