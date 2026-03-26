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

        $message = (new MailMessage)
            ->subject($subject)
            ->line($body);

        if ($this->type === 'staff.invite' && isset($this->data['invite_url'])) {
            $message->action('Set Up Your Account', $this->data['invite_url']);
        }

        if ($this->type === 'auth.verify_email' && isset($this->data['verify_url'])) {
            $message->action('Verify Email', $this->data['verify_url']);
        }

        if ($this->type === 'auth.registration_confirmed' && isset($this->data['login_url'])) {
            $message->action('Log In', $this->data['login_url']);
        }

        return $message;
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
            'auth.verify_email' => ['Verify Your Email', 'Hi '.($this->data['name'] ?? 'there').', thanks for signing up! Click the button below to verify your email and activate your account.'],
            'auth.registration_confirmed' => ['Welcome, '.($this->data['name'] ?? 'there').'! Your account is ready.', 'Your email has been verified and your account is all set. Click below to log in and get started.'],
            'auth.password_reset' => ['Password Reset Requested', 'A password reset was requested for your account.'],
            'staff.invite' => ['You\'ve been invited to join the staff portal', 'You\'ve been invited as a staff member. Click the button below to set your password and get started.'],
            'auto_replenish.succeeded' => ['Credits Auto-Renewed', 'Your credits have been automatically topped up.'],
            'auto_replenish.failed' => ['Auto-Replenish Failed', 'We couldn\'t charge your card to top up credits. Please update your payment method.'],
            'announcement' => [$this->data['subject'] ?? 'Announcement', $this->data['body'] ?? ''],
            default => [$this->type, $this->type],
        };
    }
}
