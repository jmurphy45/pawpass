<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanFeatureCache;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly string $rawToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.$this->tenant->name.' — access your portal',
        );
    }

    public function content(): Content
    {
        $hasWhiteLabel = app(PlanFeatureCache::class)->hasFeature($this->tenant->plan, 'white_label');

        return new Content(
            view: 'emails.customer-welcome',
            text: 'emails.customer-welcome-text',
            with: [
                'loginUrl' => route('magic-link.verify', ['token' => $this->rawToken]),
                'userName' => $this->user->name,
                'tenantName' => $this->tenant->name,
                'logoUrl' => $hasWhiteLabel ? $this->tenant->logo_url : null,
                'primaryColor' => $hasWhiteLabel ? ($this->tenant->primary_color ?? config('pawpass.brand_color')) : config('pawpass.brand_color'),
            ],
        );
    }
}
