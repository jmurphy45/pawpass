<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V1\InviteStaffRequest;
use App\Http\Requests\Admin\V1\UpdateBusinessSettingsRequest;
use App\Http\Requests\Admin\V1\UpdateNotificationSettingsRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function showBusiness(): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        return response()->json(['data' => $this->tenantData($tenant)]);
    }

    public function updateBusiness(UpdateBusinessSettingsRequest $request): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));
        $tenant->update($request->validated());

        return response()->json(['data' => $this->tenantData($tenant->fresh())]);
    }

    public function showNotifications(): JsonResponse
    {
        $tenantId = app('current.tenant.id');

        $settings = DB::table('tenant_notification_settings')
            ->where('tenant_id', $tenantId)
            ->get(['type', 'is_enabled']);

        return response()->json(['data' => $settings]);
    }

    public function updateNotifications(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');
        $now = now();

        $rows = array_map(fn ($setting) => [
            'tenant_id' => $tenantId,
            'type' => $setting['type'],
            'is_enabled' => $setting['is_enabled'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $request->settings);

        DB::table('tenant_notification_settings')->upsert(
            $rows,
            ['tenant_id', 'type'],
            ['is_enabled', 'updated_at']
        );

        $settings = DB::table('tenant_notification_settings')
            ->where('tenant_id', $tenantId)
            ->get(['type', 'is_enabled']);

        return response()->json(['data' => $settings]);
    }

    public function inviteStaff(InviteStaffRequest $request, NotificationService $notifications): JsonResponse
    {
        $tenantId = app('current.tenant.id');

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt(Str::random(32)),
            'role' => 'staff',
            'status' => 'pending_invite',
            'invite_token' => Str::random(64),
            'invite_expires_at' => now()->addHours(48),
        ]);

        $notifications->dispatch(
            'staff.invite',
            $tenantId,
            $user->id,
            ['invite_token' => $user->invite_token]
        );

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
        ]], 201);
    }

    public function deactivateStaff(string $userId): JsonResponse
    {
        $tenantId = app('current.tenant.id');
        $authUser = auth()->user();

        $target = User::where('tenant_id', $tenantId)->where('id', $userId)->first();

        if (! $target) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $tenant = Tenant::find($tenantId);

        if ($target->id === $tenant->owner_user_id || $target->role === 'platform_admin') {
            return response()->json(['message' => 'Cannot deactivate this user.'], 422);
        }

        $target->update(['status' => 'suspended']);

        return response()->json(['data' => ['message' => 'User suspended.']]);
    }

    private function tenantData(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'timezone' => $tenant->timezone,
            'primary_color' => $tenant->primary_color,
            'low_credit_threshold' => $tenant->low_credit_threshold,
            'checkin_block_at_zero' => $tenant->checkin_block_at_zero,
            'payout_schedule' => $tenant->payout_schedule,
        ];
    }
}
