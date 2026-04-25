<?php

namespace App\DTOs;

class PushPayload
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $type,
        public readonly ?string $actionUrl = null,
        public readonly ?string $icon = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'actionUrl' => $this->actionUrl,
            'icon' => $this->icon,
        ], fn ($v) => $v !== null);
    }
}
