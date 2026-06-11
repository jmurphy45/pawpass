<?php

namespace App\Services;

use Godruoyi\Snowflake\Snowflake;
use SimpleSoftwareIO\QrCode\Generator;

class QrCodeService
{
    public function __construct(
        private readonly Generator $generator,
        private readonly Snowflake $snowflake,
    ) {}

    public function generateToken(): string
    {
        return (string) $this->snowflake->id();
    }

    public function svg(string $url, int $size = 200): string
    {
        return (string) $this->generator
            ->format('svg')
            ->size($size)
            ->errorCorrection('M')
            ->generate($url);
    }

    public function png(string $url, int $size = 200): string
    {
        return (string) $this->generator
            ->format('png')
            ->size($size)
            ->errorCorrection('M')
            ->generate($url);
    }

    public function stableUrl(string $token): string
    {
        return 'https://'.config('app.domain').'/go/'.$token;
    }
}
