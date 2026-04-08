<?php

namespace App\Services;

use App\Models\TenantEvent;

class TenantEventService
{
    public function record(string $tenantId, string $eventType, array $payload = []): TenantEvent
    {
        return TenantEvent::create([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'payload' => empty($payload) ? null : $payload,
        ]);
    }

    public function recordOnce(string $tenantId, string $eventType, array $payload = []): ?TenantEvent
    {
        if (TenantEvent::where('tenant_id', $tenantId)->where('event_type', $eventType)->exists()) {
            return null;
        }

        return $this->record($tenantId, $eventType, $payload);
    }
}
