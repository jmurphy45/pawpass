<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastNotificationJob;
use App\Models\Tenant;
use App\Models\TenantSmsUsage;
use App\Models\User;
use App\Services\PlanFeatureCache;
use App\Services\SmsUsageService;
use App\Services\TenantEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastNotificationController extends Controller
{
    public function __construct(
        private readonly SmsUsageService $smsUsage,
        private readonly PlanFeatureCache $planFeatureCache,
        private readonly TenantEventService $events,
    ) {}

    public function index(): Response
    {
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);

        $customersCount = User::where('tenant_id', $tenantId)
            ->where('role', 'customer')
            ->whereNull('deleted_at')
            ->count();

        $planSlug = $tenant->plan ?? 'free';
        $smsQuota = $this->planFeatureCache->smsSegmentQuota($planSlug);
        $smsUsed = $this->smsUsage->getUsage($tenantId, $this->smsUsage->currentPeriod());

        return Inertia::render('Admin/Notifications/Broadcast', [
            'customersCount' => $customersCount,
            'smsQuota' => $smsQuota,
            'smsUsed' => $smsUsed,
            'hasBillingConfigured' => (bool) $tenant->platform_stripe_customer_id,
            'planSlug' => $planSlug,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1600'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:email,sms,in_app'],
        ]);

        if (in_array('sms', $validated['channels'])) {
            $tenant = Tenant::find(app('current.tenant.id'));
            if (! $tenant?->platform_stripe_customer_id) {
                return back()->withErrors(['channels' => 'SMS broadcasts require billing to be configured.']);
            }
        }

        $tenantId = app('current.tenant.id');

        SendBroadcastNotificationJob::dispatch(
            tenantId: $tenantId,
            subject: $validated['subject'],
            body: $validated['body'],
            requestedChannels: $validated['channels'],
        );

        $this->events->recordOnce($tenantId, 'first_broadcast');

        return back()->with('success', 'Broadcast queued — your customers will receive it shortly.');
    }

    public function smsUsage(): Response
    {
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $planSlug = $tenant->plan ?? 'free';
        $currentPeriod = $this->smsUsage->currentPeriod();
        $smsQuota = $this->planFeatureCache->smsSegmentQuota($planSlug);
        $smsUsed = $this->smsUsage->getUsage($tenantId, $currentPeriod);
        $overage = max(0, $smsUsed - $smsQuota);

        $history = TenantSmsUsage::where('tenant_id', $tenantId)
            ->orderByDesc('period')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'segments_used' => $row->segments_used,
                'billed_at' => $row->billed_at?->toDateTimeString(),
                'overage' => $rowOverage = max(0, $row->segments_used - $smsQuota),
                'overage_cents' => $rowOverage * SmsUsageService::SMS_SEGMENT_RATE_CENTS,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Notifications/SmsUsage', [
            'currentPeriod' => $currentPeriod,
            'smsQuota' => $smsQuota,
            'smsUsed' => $smsUsed,
            'overage' => $overage,
            'planSlug' => $planSlug,
            'history' => $history,
        ]);
    }
}
