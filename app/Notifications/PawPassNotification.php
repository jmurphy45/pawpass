<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PawPassNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [0, 60, 120];

    public function __construct(
        public readonly string $type,
        public readonly string $tenantId,
        public readonly array $data = [],
        private readonly array $channels = ['database', 'mail', 'sms'],
    ) {
        $this->onQueue('notifications');
    }

    public function via($notifiable): array
    {
        return $this->channels;
    }

    public function toArray($notifiable): array
    {
        [$subject, $body] = $this->buildMessage();

        return [
            'type' => $this->type,
            'tenant_id' => $this->tenantId,
            'subject' => $subject,
            'body' => $body,
            'data' => $this->data,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        [$subject, $body] = $this->buildMessage();

        return (new MailMessage)
            ->subject($subject)
            ->line($body);
    }

    public function toSms($notifiable): string
    {
        [, $body] = $this->buildMessage();

        return $body;
    }

    private function buildMessage(): array
    {
        return match ($this->type) {
            'payment.confirmed' => ['Payment Confirmed', 'Your payment has been confirmed and credits have been added to your account.'],
            'payment.refunded' => ['Payment Refunded', 'Your payment has been refunded.'],
            'subscription.renewed' => ['Subscription Renewed', 'Your subscription has been renewed and credits have been added.'],
            'subscription.payment_failed' => ['Payment Failed', 'Your subscription payment has failed. Please update your payment method.'],
            'subscription.cancelled' => ['Subscription Cancelled', 'Your subscription has been cancelled.'],
            'credits.low' => ['Credits Running Low', 'Your dog\'s credits are running low. Consider purchasing more to avoid interruption.'],
            'credits.empty' => ['Credits Empty', 'Your dog has no credits remaining. Please purchase more credits to continue.'],
            'auth.verify_email' => ['Verify Your Email', 'Please verify your email address to continue.'],
            'auth.password_reset' => ['Password Reset Requested', 'A password reset was requested for your account.'],
            'announcement' => [$this->data['subject'] ?? 'Announcement', $this->data['body'] ?? ''],
            default => [$this->type, $this->type],
        };
    }
}
