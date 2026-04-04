<?php

namespace App\Http\Controllers\Web\Auth;

use App\Auth\MagicLink\Action;
use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class MagicLinkController extends Controller
{
    public function __construct(private MagicLinkService $magicLink) {}

    /**
     * Handle a magic-link request.
     * Always returns 200 to avoid leaking whether the email exists.
     */
    public function request(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
            'fp_components' => ['nullable', 'array'],
        ]);

        $email = strtolower($request->input('email'));
        $ip = $request->ip();

        // Rate limit: 5 per email per hour
        $emailKey = 'magic-link-email:'.sha1($email);
        // Rate limit: 10 per IP per hour
        $ipKey = 'magic-link-ip:'.sha1($ip);

        if (RateLimiter::tooManyAttempts($emailKey, 5) || RateLimiter::tooManyAttempts($ipKey, 10)) {
            Log::info('MagicLink rate limited', ['email_hash' => sha1($email), 'ip' => $ip]);
            // Still return 200 to avoid leaking info; the link just won't arrive
            return response()->noContent();
        }

        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        $user = User::where('email', $email)->first();

        if ($user === null) {
            Log::info('MagicLink requested for unknown email', ['email_hash' => sha1($email), 'ip' => $ip]);

            return response()->noContent();
        }

        $fpComponents = $request->input('fp_components', []);
        $rawToken = $this->magicLink->generateToken($user, $fpComponents, $ip);

        Log::info('MagicLink token generated', ['user_id' => $user->id, 'ip' => $ip]);

        $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

        try {
            Mail::to($user->email)->send(new MagicLinkMail($user, $rawToken, $tenant));
            Log::info('MagicLink mail sent', ['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Log::error('MagicLink mail failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return response()->noContent();
    }

    /**
     * Verify a magic-link token from the email link.
     * Branches on the VerificationResult action.
     */
    public function verify(Request $request): RedirectResponse
    {
        $rawToken = $request->query('token', '');
        $fpParam = $request->query('fp', '');
        $fpComponents = $this->decodeFpParam($fpParam);
        $ip = $request->ip();

        $result = $this->magicLink->verifyToken($rawToken, $fpComponents, $ip);

        return match ($result->action) {
            Action::AUTHENTICATE, Action::AUTHENTICATE_FLAGGED => $this->doLogin($request, $result->user),

            Action::STEP_UP => $this->storeStepUpSession($request, $result, $rawToken),

            Action::BLOCK => redirect()->route('portal.login')
                ->withErrors(['token' => 'This link has expired or cannot be verified. Please request a new one.']),
        };
    }

    /**
     * Show the "Was this you?" confirmation page.
     */
    public function confirmShow(Request $request): \Illuminate\View\View|RedirectResponse
    {
        if (! $request->session()->has('magic_link_pending')) {
            return redirect()->route('portal.login');
        }

        return view('auth.magic-link-confirm');
    }

    /**
     * Handle the "Was this you?" form submission.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:yes,no']]);

        $pending = $request->session()->get('magic_link_pending');

        if ($pending === null) {
            return redirect()->route('portal.login');
        }

        if ($request->input('confirm') === 'no') {
            $request->session()->forget('magic_link_pending');

            $user = User::find($pending['user_id']);
            if ($user !== null) {
                $this->magicLink->revokeAllTokens($user);
            }

            return redirect()->route('portal.login')
                ->with('status', 'All magic links for your account have been revoked.');
        }

        // "Yes, this was me" — complete authentication
        $user = User::find($pending['user_id']);

        if ($user === null) {
            return redirect()->route('portal.login')
                ->withErrors(['token' => 'Session expired. Please request a new link.']);
        }

        $request->session()->forget('magic_link_pending');

        return $this->doLogin($request, $user);
    }

    /**
     * Log the user in, regenerate the session, and redirect to the appropriate dashboard.
     */
    private function doLogin(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        $redirect = match ($user->role) {
            'staff', 'business_owner' => route('admin.dashboard'),
            'customer' => route('portal.dashboard'),
            default => '/',
        };

        return redirect($redirect);
    }

    /**
     * Store step-up data in the session and redirect to the confirm page.
     */
    private function storeStepUpSession(Request $request, \App\Auth\MagicLink\VerificationResult $result, string $rawToken): RedirectResponse
    {
        $request->session()->put('magic_link_pending', [
            'user_id' => $result->user?->id,
            'risk_score' => $result->riskScore,
            'risk_factors' => $result->riskFactors,
        ]);

        return redirect()->route('magic-link.confirm');
    }

    /**
     * Decode the base64url-encoded fp query param into an array of components.
     *
     * @return array<string, mixed>
     */
    private function decodeFpParam(string $fpParam): array
    {
        if ($fpParam === '') {
            return [];
        }

        $padded = str_pad(strtr($fpParam, '-_', '+/'), strlen($fpParam) + (4 - strlen($fpParam) % 4) % 4, '=');
        $decoded = base64_decode($padded, strict: true);

        if ($decoded === false) {
            return [];
        }

        $components = json_decode($decoded, true);

        return is_array($components) ? $components : [];
    }
}
