<?php

use App\Http\Controllers\Admin\V1\AddonTypeController;
use App\Http\Controllers\Admin\V1\AttendanceAddonController;
use App\Http\Controllers\Admin\V1\BillingController;
use App\Http\Controllers\Admin\V1\BoardingReportCardController;
use App\Http\Controllers\Admin\V1\BreedController as AdminBreedController;
use App\Http\Controllers\Admin\V1\BroadcastNotificationController;
use App\Http\Controllers\Admin\V1\CreditController;
use App\Http\Controllers\Admin\V1\CustomerController;
use App\Http\Controllers\Admin\V1\CustomerIntelligenceController;
use App\Http\Controllers\Admin\V1\DogController as AdminDogController;
use App\Http\Controllers\Admin\V1\DogVaccinationController;
use App\Http\Controllers\Admin\V1\KennelUnitController;
use App\Http\Controllers\Admin\V1\OccupancyController;
use App\Http\Controllers\Admin\V1\OnboardingController;
use App\Http\Controllers\Admin\V1\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\V1\PaymentController;
use App\Http\Controllers\Admin\V1\ReportController as AdminReportController;
use App\Http\Controllers\Admin\V1\ReservationAddonController;
use App\Http\Controllers\Admin\V1\ReservationController;
use App\Http\Controllers\Admin\V1\RosterController;
use App\Http\Controllers\Admin\V1\SettingsController;
use App\Http\Controllers\Admin\V1\VaccinationRequirementController;
use App\Http\Controllers\Platform\V1\AuditLogController;
use App\Http\Controllers\Platform\V1\NotificationController as PlatformNotificationController;
use App\Http\Controllers\Platform\V1\PlatformFeatureController;
use App\Http\Controllers\Platform\V1\PlatformPlanController;
use App\Http\Controllers\Platform\V1\ReportController as PlatformReportController;
use App\Http\Controllers\Platform\V1\TenantController as PlatformTenantController;
use App\Http\Controllers\Platform\V1\TenantEventController;
use App\Http\Controllers\Platform\V1\TenantFeatureOverrideController;
use App\Http\Controllers\Portal\V1\AccountController;
use App\Http\Controllers\Portal\V1\AttendanceController;
use App\Http\Controllers\Portal\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Portal\V1\Auth\RegisterController;
use App\Http\Controllers\Portal\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Portal\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Portal\V1\BreedController as PortalBreedController;
use App\Http\Controllers\Portal\V1\DogController as PortalDogController;
use App\Http\Controllers\Portal\V1\KennelUnitController as PortalKennelUnitController;
use App\Http\Controllers\Portal\V1\NotificationController;
use App\Http\Controllers\Portal\V1\OrderController;
use App\Http\Controllers\Portal\V1\PackageController;
use App\Http\Controllers\Portal\V1\PromotionController as PortalPromotionController;
use App\Http\Controllers\Portal\V1\ReservationController as PortalReservationController;
use App\Http\Controllers\Portal\V1\SubscriptionController;
use App\Http\Controllers\Public\V1\DaycareDirectoryController;
use App\Http\Controllers\Public\V1\PlansController;
use App\Http\Controllers\Public\V1\TenantRegistrationController as PublicTenantRegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — /api/public/v1/*
|--------------------------------------------------------------------------
*/
Route::prefix('public/v1')
    ->middleware(['throttle:30,1'])
    ->name('public.v1.')
    ->group(function () {
        Route::get('plans', [PlansController::class, 'index']);
        Route::post('tenants/register', [PublicTenantRegistrationController::class, 'store'])->middleware('throttle:5,1');
        Route::get('daycares', [DaycareDirectoryController::class, 'index']);
    });

/*
|--------------------------------------------------------------------------
| Customer Portal API — /api/portal/v1/*
|--------------------------------------------------------------------------
*/
Route::prefix('portal/v1')
    ->middleware(['tenant', 'throttle:60,1'])
    ->name('portal.v1.')
    ->group(function () {
        // Public routes (no auth)
        Route::post('auth/register', [RegisterController::class, 'register']);
        Route::post('auth/verify-email', [VerifyEmailController::class, 'verify']);
        Route::post('auth/forgot-password', [ForgotPasswordController::class, 'send']);
        Route::post('auth/reset-password', [ResetPasswordController::class, 'reset']);
        Route::get('kennel-units/check-availability', [PortalKennelUnitController::class, 'checkAvailability']);

        // Authenticated routes
        Route::middleware(['auth.jwt', 'bindings'])->group(function () {
            Route::get('ping', fn () => response()->json(['data' => 'pong']));

            Route::get('breeds', [PortalBreedController::class, 'index']);

            Route::get('packages', [PackageController::class, 'index']);

            Route::get('dogs', [PortalDogController::class, 'index']);
            Route::post('dogs', [PortalDogController::class, 'store']);
            Route::get('dogs/{dog}', [PortalDogController::class, 'show']);
            Route::patch('dogs/{dog}', [PortalDogController::class, 'update']);
            Route::get('dogs/{dog}/credits', [PortalDogController::class, 'credits']);

            Route::middleware(['idempotency', 'stripe.onboarded'])->post('orders', [OrderController::class, 'store']);
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/tax-preview', [OrderController::class, 'taxPreview']);

            Route::post('promotions/check', [PortalPromotionController::class, 'check']);

            Route::post('subscriptions', [SubscriptionController::class, 'store']);
            Route::get('subscriptions', [SubscriptionController::class, 'index']);
            Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);

            Route::get('account', [AccountController::class, 'show']);
            Route::patch('account', [AccountController::class, 'update']);
            Route::patch('account/password', [AccountController::class, 'updatePassword']);
            Route::get('account/notification-prefs', [AccountController::class, 'notificationPrefs']);
            Route::put('account/notification-prefs', [AccountController::class, 'updateNotificationPrefs']);

            Route::get('attendance', [AttendanceController::class, 'index']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/count', [NotificationController::class, 'count']);
            Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
            Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);

            Route::post('push-subscriptions', [\App\Http\Controllers\Portal\V1\PushSubscriptionController::class, 'store']);
            Route::delete('push-subscriptions', [\App\Http\Controllers\Portal\V1\PushSubscriptionController::class, 'destroy']);

            Route::middleware('plan:boarding')->group(function () {
                Route::get('kennel-units/available', [PortalKennelUnitController::class, 'available']);

                Route::get('reservations', [PortalReservationController::class, 'index']);
                Route::post('reservations', [PortalReservationController::class, 'store']);
                Route::get('reservations/{id}', [PortalReservationController::class, 'show']);
                Route::patch('reservations/{id}/cancel', [PortalReservationController::class, 'cancel']);
            });
        });
    });

/*
|--------------------------------------------------------------------------
| Admin API — /api/admin/v1/*
|--------------------------------------------------------------------------
*/
Route::prefix('admin/v1')
    ->middleware(['tenant', 'auth.jwt', 'role:staff,business_owner', 'throttle:60,1', 'bindings'])
    ->name('admin.v1.')
    ->group(function () {
        Route::get('ping', fn () => response()->json(['data' => 'pong']));

        Route::get('breeds', [AdminBreedController::class, 'index']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store'])->middleware('plan:add_customers');
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::patch('customers/{customer}', [CustomerController::class, 'update']);
        Route::post('customers/{customer}/request-payment-update', [CustomerController::class, 'requestPaymentUpdate']);
        Route::middleware('role:business_owner')->group(function () {
            Route::post('customers/{customer}/charge-balance', [CustomerController::class, 'chargeBalance']);
        });

        Route::get('dogs', [AdminDogController::class, 'index']);
        Route::post('dogs', [AdminDogController::class, 'store'])->middleware('plan:add_dogs');
        Route::get('dogs/{dog}', [AdminDogController::class, 'show']);
        Route::patch('dogs/{dog}', [AdminDogController::class, 'update']);
        Route::delete('dogs/{dog}', [AdminDogController::class, 'destroy']);

        Route::get('packages', [AdminPackageController::class, 'index']);
        Route::middleware(['role:business_owner', 'stripe.onboarded'])->group(function () {
            Route::post('packages', [AdminPackageController::class, 'store']);
            Route::patch('packages/{package}', [AdminPackageController::class, 'update']);
            Route::post('packages/{package}/archive', [AdminPackageController::class, 'archive']);
        });

        Route::get('roster', [RosterController::class, 'index']);
        Route::post('roster/checkin', [RosterController::class, 'checkin']);
        Route::post('roster/checkout', [RosterController::class, 'checkout']);

        Route::middleware(['idempotency', 'plan:advanced_credit_ops'])->group(function () {
            Route::post('dogs/{dog}/credits/goodwill', [CreditController::class, 'goodwill']);
            Route::post('dogs/{dog}/credits/correction', [CreditController::class, 'correction']);
            Route::post('dogs/{dog}/credits/transfer', [CreditController::class, 'transfer']);
        });

        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('payments/{order}/refund', [PaymentController::class, 'refund']);

        Route::post('notifications/broadcast', [BroadcastNotificationController::class, 'store'])
            ->middleware('plan:broadcast_notifications');

        Route::post('push-subscriptions', [\App\Http\Controllers\Admin\V1\PushSubscriptionController::class, 'store']);
        Route::delete('push-subscriptions', [\App\Http\Controllers\Admin\V1\PushSubscriptionController::class, 'destroy']);

        // Kennel units (read ungated; write: owner + boarding plan)
        Route::get('kennel-units', [KennelUnitController::class, 'index']);
        Route::middleware(['plan:boarding', 'role:business_owner'])->group(function () {
            Route::post('kennel-units', [KennelUnitController::class, 'store']);
            Route::patch('kennel-units/{kennelUnit}', [KennelUnitController::class, 'update']);
            Route::delete('kennel-units/{kennelUnit}', [KennelUnitController::class, 'destroy']);
        });

        // Boarding & Reservations (plan:boarding required)
        Route::middleware('plan:boarding')->group(function () {
            Route::get('reservations', [ReservationController::class, 'index']);
            Route::post('reservations', [ReservationController::class, 'store']);
            Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
            Route::patch('reservations/{reservation}', [ReservationController::class, 'update']);
            Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy']);

            Route::get('reservations/{reservation}/report-cards', [BoardingReportCardController::class, 'index']);
            Route::post('reservations/{reservation}/report-cards', [BoardingReportCardController::class, 'store']);
            Route::patch('reservations/{reservation}/report-cards/{reportCard}', [BoardingReportCardController::class, 'update']);

            Route::get('reservations/{reservation}/addons', [ReservationAddonController::class, 'index']);
            Route::post('reservations/{reservation}/addons', [ReservationAddonController::class, 'store']);
            Route::delete('reservations/{reservation}/addons/{addon}', [ReservationAddonController::class, 'destroy']);

            Route::get('occupancy', [OccupancyController::class, 'index']);
        });

        // Add-on Services (plan:addon_services required)
        Route::middleware('plan:addon_services')->group(function () {
            Route::get('attendances/{attendance}/addons', [AttendanceAddonController::class, 'index']);
            Route::post('attendances/{attendance}/addons', [AttendanceAddonController::class, 'store']);
            Route::delete('attendances/{attendance}/addons/{addon}', [AttendanceAddonController::class, 'destroy']);

            Route::get('addon-types', [AddonTypeController::class, 'index']);
            Route::middleware('role:business_owner')->group(function () {
                Route::post('addon-types', [AddonTypeController::class, 'store']);
                Route::patch('addon-types/{addonType}', [AddonTypeController::class, 'update']);
                Route::delete('addon-types/{addonType}', [AddonTypeController::class, 'destroy']);
            });
        });

        // Vaccination Management (plan:vaccination_management required)
        Route::middleware('plan:vaccination_management')->group(function () {
            Route::get('dogs/{dog}/vaccinations', [DogVaccinationController::class, 'index']);
            Route::post('dogs/{dog}/vaccinations', [DogVaccinationController::class, 'store']);
            Route::patch('dogs/{dog}/vaccinations/{vaccination}', [DogVaccinationController::class, 'update']);
            Route::delete('dogs/{dog}/vaccinations/{vaccination}', [DogVaccinationController::class, 'destroy']);
        });

        // Vaccination requirements (read ungated; write: owner + vaccination_management plan)
        Route::get('vaccination-requirements', [VaccinationRequirementController::class, 'index']);
        Route::middleware(['role:business_owner', 'plan:vaccination_management'])->group(function () {
            Route::post('vaccination-requirements', [VaccinationRequirementController::class, 'store']);
            Route::delete('vaccination-requirements/{vaccinationRequirement}', [VaccinationRequirementController::class, 'destroy']);
        });

        // Reports — Staff+ with basic_reporting
        Route::middleware(['role:staff,business_owner', 'plan:basic_reporting'])->group(function () {
            Route::get('reports/attendance', [AdminReportController::class, 'attendance']);
            Route::get('reports/roster-history', [AdminReportController::class, 'rosterHistory']);
            Route::get('reports/credit-status', [AdminReportController::class, 'creditStatus']);
        });

        // Reports — Owner + basic_reporting
        Route::middleware(['role:business_owner', 'plan:basic_reporting'])->group(function () {
            Route::get('reports/packages', [AdminReportController::class, 'packages']);
            Route::get('reports/staff-activity', [AdminReportController::class, 'staffActivity']);
        });

        // Reports — Owner + financial_reports
        Route::middleware(['role:business_owner', 'plan:financial_reports'])->group(function () {
            Route::get('reports/revenue', [AdminReportController::class, 'revenue']);
            Route::get('reports/payout-forecast', [AdminReportController::class, 'payoutForecast']);
            Route::get('reports/credits', [AdminReportController::class, 'credits']);
            Route::get('reports/customers/ltv', [AdminReportController::class, 'customersLtv']);
            Route::get('reports/promotions', [AdminReportController::class, 'promotions']);
            Route::get('reports/boarding', [AdminReportController::class, 'boardingRevenue']);
            Route::get('reports/customer-intelligence', CustomerIntelligenceController::class);
        });

        // Reports — Owner + basic_reporting (real-time)
        Route::middleware(['role:business_owner', 'plan:basic_reporting'])->group(function () {
            Route::get('reports/outstanding-balances', [AdminReportController::class, 'outstandingBalances']);
        });

        Route::middleware('role:business_owner')->group(function () {
            Route::get('settings/business', [SettingsController::class, 'showBusiness']);
            Route::patch('settings/business', [SettingsController::class, 'updateBusiness']);

            Route::get('settings/notifications', [SettingsController::class, 'showNotifications']);
            Route::put('settings/notifications', [SettingsController::class, 'updateNotifications']);

            Route::post('settings/staff/invite', [SettingsController::class, 'inviteStaff']);
            Route::delete('settings/staff/{user_id}', [SettingsController::class, 'deactivateStaff']);

            Route::get('billing', [BillingController::class, 'show']);
            Route::post('billing/subscribe', [BillingController::class, 'subscribe']);
            Route::post('billing/upgrade', [BillingController::class, 'upgrade']);
            Route::post('billing/cancel', [BillingController::class, 'cancel']);
            Route::get('billing/invoices', [BillingController::class, 'invoices']);
            Route::get('billing/portal-url', [BillingController::class, 'portalUrl']);

            Route::post('onboarding/connect', [OnboardingController::class, 'createAccount']);
            Route::post('onboarding/account-link', [OnboardingController::class, 'createAccountLink']);
        });
    });

/*
|--------------------------------------------------------------------------
| Platform API — /api/platform/v1/*
|--------------------------------------------------------------------------
*/
Route::prefix('platform/v1')
    ->middleware(['auth.jwt', 'role:platform_admin', 'throttle:120,1', 'bindings'])
    ->name('platform.v1.')
    ->group(function () {
        Route::get('ping', fn () => response()->json(['data' => 'pong']));

        Route::get('tenants', [PlatformTenantController::class, 'index']);
        Route::get('tenants/{id}', [PlatformTenantController::class, 'show']);
        Route::patch('tenants/{id}', [PlatformTenantController::class, 'update']);
        Route::post('tenants/{id}/suspend', [PlatformTenantController::class, 'suspend']);
        Route::post('tenants/{id}/reinstate', [PlatformTenantController::class, 'reinstate']);
        Route::post('tenants/{id}/cancel', [PlatformTenantController::class, 'cancel']);

        Route::get('notifications/delivery', [PlatformNotificationController::class, 'delivery']);
        Route::post('notifications/failures/{log_id}/retry', [PlatformNotificationController::class, 'retry']);

        Route::get('audit-log', [AuditLogController::class, 'index']);
        Route::get('tenant-events', [TenantEventController::class, 'index']);

        Route::get('plans', [PlatformPlanController::class, 'index']);
        Route::post('plans', [PlatformPlanController::class, 'store']);
        Route::patch('plans/{id}', [PlatformPlanController::class, 'update']);
        Route::post('plans/{id}/sync-stripe', [PlatformPlanController::class, 'syncStripe']);

        Route::get('features', [PlatformFeatureController::class, 'index']);
        Route::post('features', [PlatformFeatureController::class, 'store']);
        Route::patch('features/{id}', [PlatformFeatureController::class, 'update']);
        Route::delete('features/{id}', [PlatformFeatureController::class, 'destroy']);

        Route::get('tenants/{tenantId}/features', [TenantFeatureOverrideController::class, 'index']);
        Route::post('tenants/{tenantId}/features', [TenantFeatureOverrideController::class, 'store']);
        Route::delete('tenants/{tenantId}/features/{featureSlug}', [TenantFeatureOverrideController::class, 'destroy']);

        Route::get('reports/revenue', [PlatformReportController::class, 'revenue']);
        Route::get('reports/tenant-health', [PlatformReportController::class, 'tenantHealth']);
        Route::get('reports/notifications', [PlatformReportController::class, 'notificationDelivery']);
    });
