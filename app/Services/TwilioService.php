<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TwilioService
{
    public function __construct(
        private readonly string $sid,
        private readonly string $token,
        private readonly string $from,
    ) {}

    public function send(string $to, string $message): void
    {
        $response = Http::withBasicAuth($this->sid, $this->token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'From' => $this->from,
                'To' => $to,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Twilio SMS failed: {$response->status()} {$response->body()}");
        }
    }
}
