<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Web\TenantRegistrationController;
use App\Http\Controllers\Web\Admin\Auth\AcceptInviteController;
use App\Http\Controllers\Web\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Web\Admin\VerifyEmailController as AdminVerifyEmailController;
use App\Http\Controllers\Web\Admin\Auth\LogoutController as AdminLogoutController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Web\Admin\DogController as AdminDogController;
use App\Http\Controllers\Web\Admin\RosterController as AdminRosterController;
use App\Http\Controllers\Web\Admin\CreditController as AdminCreditController;
use App\Http\Controllers\Web\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Web\Admin\OrderReceiptController as AdminOrderReceiptController;
use App\Http\Controllers\Web\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Web\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Web\Admin\LogoController as AdminLogoController;
use App\Http\Controllers\Web\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Web\Admin\TaxController as AdminTaxController;
use App\Http\Controllers\Web\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Web\Admin\BoardingController as AdminBoardingController;
use App\Http\Controllers\Web\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\Web\Admin\VaccinationRequirementController as AdminVaccinationRequirementController;
use App\Http\Controllers\Web\Admin\BroadcastNotificationController as AdminBroadcastController;
use App\Http\Controllers\Web\Admin\HelpController as AdminHelpController;
use App\Http\Controllers\Web\Portal\Auth\LoginController;
use App\Http\Controllers\Web\Portal\Auth\LogoutController;
use App\Http\Controllers\Web\Portal\Auth\RegisterController;
use App\Http\Controllers\Web\Portal\Auth\VerifyEmailController;
use App\Http\Controllers\Web\Portal\AccountController;
use App\Http\Controllers\Web\Portal\AttendanceController;
use App\Http\Controllers\Web\Portal\DashboardController;
use App\Http\Controllers\Web\Portal\DogController;
use App\Http\Controllers\Web\Portal\HistoryController;
use App\Http\Controllers\Web\Portal\AutoReplenishController;
use App\Http\Controllers\Web\Portal\SubscriptionController;
use App\Http\Controllers\Web\Portal\NotificationController;
use App\Http\Controllers\Web\Portal\OrderReceiptController;
use App\Http\Controllers\Web\Portal\BoardingController as PortalBoardingController;
use App\Http\Controllers\Web\Portal\PurchaseController;
use App\Http\Controllers\Web\Auth\MagicLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Magic-link passwordless authentication (no tenant scope — works for any portal)
Route::prefix('auth/magic-link')->group(function () {
    Route::post('/request', [MagicLinkController::class, 'request'])->name('magic-link.request');
    Route::get('/verify',   [MagicLinkController::class, 'verify'])->name('magic-link.verify');
    Route::get('/confirm',  [MagicLinkController::class, 'confirmShow'])->name('magic-link.confirm');
    Route::post('/confirm', [MagicLinkController::class, 'confirm'])->name('magic-link.confirm.store');
});

// Tenant self-registration (no tenant middleware — this creates a new tenant)
Route::get('/register', [TenantRegistrationController::class, 'create'])->name('tenant.register');
Route::post('/register', [TenantRegistrationController::class, 'store'])->name('tenant.register.store');
Route::get('/register/success', [TenantRegistrationController::class, 'success'])->name('tenant.register.success');

// Admin staff portal — all routes require tenant middleware
Route::middleware(['tenant'])->prefix('admin')->group(function () {

    // Guest-only auth routes
    Route::middleware(['guest'])->group(function () {
        Route::get('/login', [AdminLoginController::class, 'show'])->name('admin.login');

        Route::get('/invite/{token}', [AcceptInviteController::class, 'show'])->name('admin.invite.show');
        Route::post('/invite/{token}', [AcceptInviteController::class, 'store'])->name('admin.invite.store');

        Route::get('/verify-email', [AdminVerifyEmailController::class, 'show'])->name('admin.verify-email');
    });

    // Authenticated staff routes
    Route::middleware(['auth', 'staff.portal.web', 'bindings'])->group(function () {
        Route::post('/logout', [AdminLogoutController::class, 'destroy'])->name('admin.logout');

        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        // Customers
        Route::get('/customers', [AdminCustomerController::class, 'index'])->name('admin.customers.index');
        Route::get('/customers/create', [AdminCustomerController::class, 'create'])->name('admin.customers.create');
        Route::post('/customers', [AdminCustomerController::class, 'store'])->name('admin.customers.store');
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('admin.customers.show');

        // Dogs
        Route::get('/dogs', [AdminDogController::class, 'index'])->name('admin.dogs.index');
        Route::get('/dogs/create', [AdminDogController::class, 'create'])->name('admin.dogs.create');
        Route::post('/dogs', [AdminDogController::class, 'store'])->name('admin.dogs.store');
        Route::get('/dogs/{dog}', [AdminDogController::class, 'show'])->name('admin.dogs.show');
        Route::get('/dogs/{dog}/edit', [AdminDogController::class, 'edit'])->name('admin.dogs.edit');
        Route::patch('/dogs/{dog}', [AdminDogController::class, 'update'])->name('admin.dogs.update');
        Route::post('/dogs/{dog}/vaccinations', [AdminDogController::class, 'storeVaccination'])->name('admin.dogs.vaccinations.store');
        Route::delete('/dogs/{dog}/vaccinations/{vaccination}', [AdminDogController::class, 'destroyVaccination'])->name('admin.dogs.vaccinations.destroy');

        // Boarding
        Route::get('/boarding/reservations', [AdminBoardingController::class, 'reservations'])->name('admin.boarding.reservations');
        Route::get('/boarding/reservations/{reservation}', [AdminBoardingController::class, 'showReservation'])->name('admin.boarding.reservations.show');
        Route::patch('/boarding/reservations/{reservation}', [AdminBoardingController::class, 'updateReservation'])->name('admin.boarding.reservations.update');
        Route::post('/boarding/reservations/{reservation}/checkout', [AdminBoardingController::class, 'processCheckout'])->name('admin.boarding.reservations.checkout');
        Route::post('/boarding/reservations/{reservation}/report-cards', [AdminBoardingController::class, 'storeReportCard'])->name('admin.boarding.reservations.report-cards.store');
        Route::post('/boarding/reservations/{reservation}/addons', [AdminBoardingController::class, 'storeAddon'])->name('admin.boarding.reservations.addons.store');
        Route::delete('/boarding/reservations/{reservation}/addons/{addon}', [AdminBoardingController::class, 'destroyAddon'])->name('admin.boarding.reservations.addons.destroy');
        Route::get('/boarding/occupancy', [AdminBoardingController::class, 'occupancy'])->name('admin.boarding.occupancy');
        Route::get('/boarding/units', [AdminBoardingController::class, 'kennelUnits'])->name('admin.boarding.units');
        Route::post('/boarding/units', [AdminBoardingController::class, 'storeKennelUnit'])->name('admin.boarding.units.store');
        Route::patch('/boarding/units/{kennelUnit}', [AdminBoardingController::class, 'updateKennelUnit'])->name('admin.boarding.units.update');
        Route::delete('/boarding/units/{kennelUnit}', [AdminBoardingController::class, 'destroyKennelUnit'])->name('admin.boarding.units.destroy');

        // Roster
        Route::get('/roster', [AdminRosterController::class, 'index'])->name('admin.roster.index');
        Route::post('/roster/checkin', [AdminRosterController::class, 'checkin'])->name('admin.roster.checkin');
        Route::post('/roster/checkout', [AdminRosterController::class, 'checkout'])->name('admin.roster.checkout');
        Route::post('/roster/attendances/{attendance}/addons', [AdminRosterController::class, 'storeAttendanceAddon'])->name('admin.roster.attendance-addons.store');
        Route::delete('/roster/attendances/{attendance}/addons/{addon}', [AdminRosterController::class, 'destroyAttendanceAddon'])->name('admin.roster.attendance-addons.destroy');

        // Credits
        Route::post('/dogs/{dog}/credits/goodwill', [AdminCreditController::class, 'goodwill'])->name('admin.credits.goodwill');
        Route::post('/dogs/{dog}/credits/correction', [AdminCreditController::class, 'correction'])->name('admin.credits.correction');
        Route::post('/dogs/{dog}/credits/transfer', [AdminCreditController::class, 'transfer'])->name('admin.credits.transfer');

        // Packages (business_owner only enforced in controller)
        Route::get('/packages', [AdminPackageController::class, 'index'])->name('admin.packages.index');
        Route::get('/packages/create', [AdminPackageController::class, 'create'])->name('admin.packages.create');
        Route::post('/packages', [AdminPackageController::class, 'store'])->middleware('stripe.onboarded')->name('admin.packages.store');
        Route::get('/packages/{package}/edit', [AdminPackageController::class, 'edit'])->name('admin.packages.edit');
        Route::patch('/packages/{package}', [AdminPackageController::class, 'update'])->middleware('stripe.onboarded')->name('admin.packages.update');
        Route::post('/packages/{package}/archive', [AdminPackageController::class, 'archive'])->middleware('stripe.onboarded')->name('admin.packages.archive');

        // Payments
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('admin.payments.index');
        Route::post('/payments/{order}/refund', [AdminPaymentController::class, 'refund'])->name('admin.payments.refund');
        Route::get('/orders/{order}/receipt', AdminOrderReceiptController::class)->name('admin.orders.receipt');

        // Settings (business_owner only enforced in controller)
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::patch('/settings/business', [AdminSettingsController::class, 'updateBusiness'])->name('admin.settings.business');
        Route::patch('/settings/notifications', [AdminSettingsController::class, 'updateNotifications'])->name('admin.settings.notifications');
        Route::post('/settings/staff/invite', [AdminSettingsController::class, 'inviteStaff'])->name('admin.settings.staff.invite');
        Route::patch('/settings/staff/{user}/deactivate', [AdminSettingsController::class, 'deactivateStaff'])->name('admin.settings.staff.deactivate');
        Route::patch('/settings/billing-address', [AdminSettingsController::class, 'updateBillingAddress'])->name('admin.settings.billing-address');
        Route::post('/settings/logo', [AdminLogoController::class, 'store'])->name('admin.settings.logo.store');
        Route::delete('/settings/logo', [AdminLogoController::class, 'destroy'])->name('admin.settings.logo.destroy');

        // Reports
        Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
        Route::get('/reports/revenue', [AdminReportController::class, 'revenue'])->name('admin.reports.revenue');
        Route::get('/reports/packages', [AdminReportController::class, 'packages'])->name('admin.reports.packages');
        Route::get('/reports/credits', [AdminReportController::class, 'credits'])->name('admin.reports.credits');
        Route::get('/reports/customers', [AdminReportController::class, 'customers'])->name('admin.reports.customers');
        Route::get('/reports/attendance', [AdminReportController::class, 'attendance'])->name('admin.reports.attendance');
        Route::get('/reports/credit-status', [AdminReportController::class, 'creditStatus'])->name('admin.reports.credit-status');
        Route::get('/reports/vaccinations', [AdminReportController::class, 'vaccinations'])->middleware('plan:vaccination_management')->name('admin.reports.vaccinations');

        // Vaccination Requirements
        Route::get('/vaccination-requirements', [AdminVaccinationRequirementController::class, 'index'])->name('admin.vaccination-requirements.index');
        Route::post('/vaccination-requirements', [AdminVaccinationRequirementController::class, 'store'])->name('admin.vaccination-requirements.store');
        Route::delete('/vaccination-requirements/{vaccinationRequirement}', [AdminVaccinationRequirementController::class, 'destroy'])->name('admin.vaccination-requirements.destroy');

        // Services (Add-on type catalog)
        Route::get('/services', [AdminServicesController::class, 'index'])->name('admin.services.index');
        Route::post('/services', [AdminServicesController::class, 'store'])->name('admin.services.store');
        Route::patch('/services/{addonType}', [AdminServicesController::class, 'update'])->name('admin.services.update');
        Route::delete('/services/{addonType}', [AdminServicesController::class, 'destroy'])->name('admin.services.destroy');

        // Notifications / Broadcast
        Route::get('/notifications/broadcast', [AdminBroadcastController::class, 'index'])->name('admin.notifications.broadcast');
        Route::post('/notifications/broadcast', [AdminBroadcastController::class, 'store'])->name('admin.notifications.broadcast.store');
        Route::get('/notifications/sms-usage', [AdminBroadcastController::class, 'smsUsage'])->name('admin.notifications.sms-usage');

        // Billing (business_owner only enforced in controller)
        Route::get('/billing', [AdminBillingController::class, 'index'])->name('admin.billing.index');
        Route::post('/billing/setup-intent', [AdminBillingController::class, 'setupIntent'])->name('admin.billing.setup-intent');
        Route::post('/billing/subscribe', [AdminBillingController::class, 'subscribe'])->name('admin.billing.subscribe');
        Route::post('/billing/upgrade', [AdminBillingController::class, 'upgrade'])->name('admin.billing.upgrade');
        Route::post('/billing/cancel', [AdminBillingController::class, 'cancel'])->name('admin.billing.cancel');
        Route::get('/billing/portal', [AdminBillingController::class, 'portal'])->name('admin.billing.portal');
        Route::get('/billing/account-session', [AdminBillingController::class, 'accountSession'])->name('admin.billing.account-session');
        Route::post('/billing/payment-method', [AdminBillingController::class, 'updatePaymentMethod'])->name('admin.billing.payment-method');

        // Help / FAQ
        Route::get('/help', [AdminHelpController::class, 'index'])->name('admin.help');

        // Tax (business_owner only enforced in controller)
        Route::get('/tax', [AdminTaxController::class, 'index'])->name('admin.tax.index');
        Route::get('/tax/account-session', [AdminTaxController::class, 'accountSession'])->name('admin.tax.account-session');
        Route::post('/tax/toggle-collection', [AdminTaxController::class, 'toggleCollection'])->name('admin.tax.toggle-collection');
    });
});

// Customer portal — all routes require tenant middleware
Route::middleware(['tenant'])->prefix('my')->group(function () {

    // Guest-only auth routes
    Route::middleware(['guest'])->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('portal.login');

        Route::get('/register', [RegisterController::class, 'show'])->name('portal.register');
        Route::post('/register', [RegisterController::class, 'store'])->name('portal.register.store');

        Route::get('/verify-email', [VerifyEmailController::class, 'show'])->name('portal.verify-email');


    });

    // Authenticated customer routes
    Route::middleware(['auth', 'customer.portal.web', 'bindings'])->group(function () {
        Route::post('/logout', [LogoutController::class, 'destroy'])->name('portal.logout');

        Route::get('/', [DashboardController::class, 'index'])->name('portal.dashboard');

        // Dogs
        Route::get('/dogs', [DogController::class, 'index'])->name('portal.dogs.index');
        Route::get('/dogs/create', [DogController::class, 'create'])->name('portal.dogs.create');
        Route::post('/dogs', [DogController::class, 'store'])->name('portal.dogs.store');
        Route::get('/dogs/{dog}', [DogController::class, 'show'])->name('portal.dogs.show');
        Route::get('/dogs/{dog}/edit', [DogController::class, 'edit'])->name('portal.dogs.edit');
        Route::patch('/dogs/{dog}', [DogController::class, 'update'])->name('portal.dogs.update');
        Route::post('/dogs/{dog}/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('portal.subscriptions.cancel');
        Route::post('/dogs/{dog}/auto-replenish/cancel', [AutoReplenishController::class, 'cancel'])->name('portal.auto-replenish.cancel');

        // Purchase
        Route::get('/purchase', [PurchaseController::class, 'index'])->name('portal.purchase');
        Route::get('/purchase/tax-preview', [PurchaseController::class, 'taxPreview'])->name('portal.purchase.tax-preview');
        Route::post('/purchase', [PurchaseController::class, 'store'])->name('portal.purchase.store');
        Route::post('/purchase/confirm', [PurchaseController::class, 'confirm'])->name('portal.purchase.confirm');

        // History / Orders
        Route::get('/history', [HistoryController::class, 'index'])->name('portal.history');
        Route::get('/orders/{order}/receipt', OrderReceiptController::class)->name('portal.orders.receipt');

        // Attendance
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('portal.attendance');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('portal.notifications');
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('portal.notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('portal.notifications.read-all');

        // Boarding
        Route::get('/boarding', [PortalBoardingController::class, 'index'])->name('portal.boarding.index');
        Route::get('/boarding/create', [PortalBoardingController::class, 'create'])->name('portal.boarding.create');
        Route::post('/boarding', [PortalBoardingController::class, 'store'])->name('portal.boarding.store');
        Route::get('/boarding/{id}', [PortalBoardingController::class, 'show'])->name('portal.boarding.show');
        Route::post('/boarding/{id}/cancel', [PortalBoardingController::class, 'cancel'])->name('portal.boarding.cancel');

        // Account
        Route::get('/account', [AccountController::class, 'index'])->name('portal.account');
        Route::patch('/account', [AccountController::class, 'update'])->name('portal.account.update');

        Route::put('/account/notification-prefs', [AccountController::class, 'notificationPrefs'])->name('portal.account.notification-prefs');
    });
});
