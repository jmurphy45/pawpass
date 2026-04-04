<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/auth.php'));

            \Illuminate\Support\Facades\Route::middleware(['api', 'throttle:600,1'])
                ->prefix('webhooks')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Remove SubstituteBindings from the api group so route model binding
        // runs AFTER our tenant/auth middleware (which set current.tenant.id).
        // We add SubstituteBindings explicitly in each route group below.
        $middleware->group('api', []);

        $middleware->alias([
            'auth.jwt'            => \App\Http\Middleware\AuthenticateJwt::class,
            'role'                => \App\Http\Middleware\RequireRole::class,
            'tenant'              => \App\Http\Middleware\TenantMiddleware::class,
            'customer.portal'     => \App\Http\Middleware\CustomerPortalMiddleware::class,
            'customer.portal.web' => \App\Http\Middleware\CustomerPortalWebMiddleware::class,
            'staff.portal.web'    => \App\Http\Middleware\StaffPortalWebMiddleware::class,
            'idempotency'         => \App\Http\Middleware\RequireIdempotencyKey::class,
            'plan'                => \App\Http\Middleware\RequirePlanFeature::class,
            'stripe.onboarded'   => \App\Http\Middleware\RequireStripeOnboarding::class,
            'bindings'            => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('portal.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            return route('portal.dashboard');
        });

        // Remove SubstituteBindings from the web group for the same reason as api:
        // route model binding must run AFTER tenant middleware sets current.tenant.id.
        // Each web route group adds 'bindings' alias as the last middleware.
        $middleware->web(
            remove: [\Illuminate\Routing\Middleware\SubstituteBindings::class],
            append: [\App\Http\Middleware\HandleInertiaRequests::class],
        );
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->job(new \App\Jobs\ExpireSubscriptionCredits)->dailyAt('01:00');
        $schedule->job(new \App\Jobs\PruneOldNotifications)->dailyAt('04:00');
        $schedule->job(new \App\Jobs\PruneRawWebhooks)->dailyAt('04:00');
        $schedule->job(new \App\Jobs\PruneDispatchedPending)->dailyAt('04:00');
        $schedule->job(new \App\Jobs\WarmTenantReportCaches)->dailyAt('02:00');
        $schedule->job(new \App\Jobs\WarmPlatformReportCaches)->dailyAt('03:00');
        $schedule->job(new \App\Jobs\SendTrialExpirationWarnings)->dailyAt('09:00');
        $schedule->job(new \App\Jobs\ExpireTrials)->dailyAt('01:30');
        $schedule->job(new \App\Jobs\SendUpgradeNudges)->dailyAt('09:00');
        $schedule->job(new \App\Jobs\ProcessDunning)->dailyAt('02:00');
        $schedule->job(new \App\Jobs\BillSmsOverageJob)->monthlyOn(1, '05:00');
        $schedule->job(new \App\Jobs\SendVaccinationExpiringSoonWarnings)->dailyAt('13:00');
        $schedule->job(new \App\Jobs\SendVaccinationExpiringUrgentWarnings)->dailyAt('13:00');
        $schedule->command('auth:cleanup-magic-links')->everyTenMinutes();
        $schedule->command('sitemap:generate')->dailyAt('03:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
