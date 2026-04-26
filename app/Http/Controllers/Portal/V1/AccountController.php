<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->accountData($user)]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')
                    ->where('tenant_id', app('current.tenant.id'))
                    ->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update(array_filter($validated, fn ($v) => $v !== null));

        if (isset($validated['name']) && $user->customer) {
            $user->customer()->update(['name' => $validated['name']]);
        }

        return response()->json(['data' => $this->accountData($user->fresh())]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['current_password' => ['Current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['data' => ['message' => 'Password updated.']]);
    }

    public function notificationPrefs(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = app('current.tenant.id');

        $prefs = DB::table('user_notification_preferences')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->get(['type', 'channel', 'is_enabled']);

        return response()->json(['data' => $prefs]);
    }

    public function updateNotificationPrefs(Request $request): JsonResponse
    {
        $request->validate([
            'prefs' => ['required', 'array'],
            'prefs.*.type' => ['required', 'string'],
            'prefs.*.channel' => ['required', 'string', 'in:email,sms'],
            'prefs.*.is_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $tenantId = app('current.tenant.id');
        $now = now();

        $rows = array_map(fn ($pref) => [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'type' => $pref['type'],
            'channel' => $pref['channel'],
            'is_enabled' => $pref['is_enabled'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $request->prefs);

        DB::table('user_notification_preferences')->upsert(
            $rows,
            ['user_id', 'type', 'channel'],
            ['is_enabled', 'updated_at']
        );

        $prefs = DB::table('user_notification_preferences')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->get(['type', 'channel', 'is_enabled']);

        return response()->json(['data' => $prefs]);
    }

    private function accountData($user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'customer_name' => $user->customer?->name,
        ];
    }
}
