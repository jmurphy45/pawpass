<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastNotificationJob;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastNotificationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject'    => ['required', 'string', 'max:255'],
            'body'       => ['required', 'string', 'max:1600'],
            'channels'   => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:email,sms,in_app'],
        ]);

        if (in_array('sms', $validated['channels'])) {
            $tenant = Tenant::find(app('current.tenant.id'));
            if (! $tenant?->platform_stripe_customer_id) {
                return response()->json(['error' => 'BILLING_NOT_CONFIGURED'], 422);
            }
        }

        SendBroadcastNotificationJob::dispatch(
            tenantId: app('current.tenant.id'),
            subject: $validated['subject'],
            body: $validated['body'],
            requestedChannels: $validated['channels'],
        );

        return response()->json(['message' => 'Broadcast queued'], 202);
    }
}
