<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TwilioService
{
    public function __construct(
        private readonly string $sid,
        private readonly string $token,
        private readonly string $from,
        private readonly bool $fake = false,
    ) {}

    public function send(string $to, string $message): int
    {
        $segments = $this->countSegments($message);

        if ($this->fake) {
            return $segments;
        }

        $response = Http::withBasicAuth($this->sid, $this->token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'From' => $this->from,
                'To'   => $to,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Twilio SMS failed: {$response->status()} {$response->body()}");
        }

        return $segments;
    }

    /**
     * Count SMS segments. Non-ASCII characters force UCS-2 encoding (70 chars/segment).
     * Pure ASCII/GSM-7-compatible messages use 160 chars/segment.
     */
    private function countSegments(string $message): int
    {
        $hasNonAscii    = strlen($message) !== mb_strlen($message);
        $charsPerSegment = $hasNonAscii ? 70 : 160;

        return (int) ceil(mb_strlen($message) / $charsPerSegment);
    }
}
