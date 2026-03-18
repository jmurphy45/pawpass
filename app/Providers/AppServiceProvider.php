<?php

namespace App\Providers;

use App\Auth\JwtService;
use App\Listeners\LogNotificationSent;
use App\Models\Package;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Notifications\Channels\SmsChannel;
use App\Observers\PackageObserver;
use App\Observers\PlatformPlanObserver;
use App\Services\NotificationService;
use App\Services\PlanFeatureCache;
use App\Services\SmsUsageService;
use App\Services\StripeBillingService;
use App\Services\StripeService;
use App\Services\TwilioService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default binding — resolved by TenantMiddleware on web/API routes.
        // Tests override this with app()->instance('current.tenant.id', $id).
        $this->app->bind('current.tenant.id', fn () => null);

        $this->app->singleton(StripeService::class, function () {
            return new StripeService(new StripeClient(config('services.stripe.secret')));
        });

        $this->app->singleton(JwtService::class, function () {
            $privatePath = env('JWT_PRIVATE_KEY_PATH', storage_path('keys/jwt_private.pem'));
            $publicPath = env('JWT_PUBLIC_KEY_PATH', storage_path('keys/jwt_public.pem'));

            return new JwtService(
                privateKey: file_get_contents($privatePath),
                publicKey: file_get_contents($publicPath),
            );
        });

        $this->app->singleton(TwilioService::class, function () {
            return new TwilioService(
                sid: config('services.twilio.sid'),
                token: config('services.twilio.token'),
                from: config('services.twilio.from'),
                fake: (bool) config('services.twilio.fake', false),
            );
        });

        $this->app->singleton(NotificationService::class, fn () => new NotificationService);

        $this->app->singleton(StripeBillingService::class, function () {
            return new StripeBillingService(new StripeClient(config('services.stripe.billing_secret')));
        });

        $this->app->singleton(PlanFeatureCache::class);
        $this->app->singleton(SmsUsageService::class);
    }

    public function boot(): void
    {
        PlatformPlan::observe(PlatformPlanObserver::class);
        Package::observe(PackageObserver::class);

        Notification::extend('sms', fn ($app) => new SmsChannel(
            $app->make(TwilioService::class),
            $app->make(SmsUsageService::class),
        ));

        Event::listen(NotificationSent::class, [LogNotificationSent::class, 'handleSent']);
        Event::listen(NotificationFailed::class, [LogNotificationSent::class, 'handleFailed']);

        Feature::resolveScopeUsing(fn ($driver) =>
            ($id = app('current.tenant.id')) ? Tenant::find($id) : null
        );
    }
}
