<?php

namespace App\Http\Controllers\Portal\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('tenant_id', app('current.tenant.id'))
            ->where('email', $request->email)->first();

        $row = $user
            ? DB::table('password_reset_tokens')->where('email', $user->email)->first()
            : null;

        if (! $row || ! Hash::check($request->token, $row->token)) {
            return response()->json(['message' => 'Invalid or expired token.'], 422);
        }

        if (Carbon::parse($row->created_at)->isBefore(now()->subHour())) {
            return response()->json(['message' => 'Token has expired.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

        return response()->json(['data' => ['message' => 'Password reset successfully.']]);
    }
}
