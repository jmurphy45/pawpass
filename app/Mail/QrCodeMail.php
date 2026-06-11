<?php

namespace App\Mail;

use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanFeatureCache;
use App\Services\QrCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QrCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly QrCode $qrCode,
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->qrCode->label ?? $this->qrCode->key;

        return new Envelope(
            subject: 'Your '.$label.' QR Code — '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        $hasWhiteLabel = app(PlanFeatureCache::class)->hasFeature($this->tenant->plan, 'white_label');

        $stableUrl = $this->qrCodeService->stableUrl($this->qrCode->token);
        $qrDataUri = 'data:image/png;base64,'.base64_encode($this->qrCodeService->png($stableUrl, 300));

        return new Content(
            view: 'emails.qr-code',
            text: 'emails.qr-code-text',
            with: [
                'userName' => $this->user->name,
                'tenantName' => $this->tenant->name,
                'label' => $this->qrCode->label ?? $this->qrCode->key,
                'stableUrl' => $stableUrl,
                'qrDataUri' => $qrDataUri,
                'logoUrl' => $hasWhiteLabel ? $this->tenant->logo_url : null,
                'primaryColor' => $hasWhiteLabel ? ($this->tenant->primary_color ?? config('pawpass.brand_color')) : config('pawpass.brand_color'),
            ],
        );
    }
}
