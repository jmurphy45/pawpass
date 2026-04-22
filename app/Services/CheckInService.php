<?php

namespace App\Services;

use App\Exceptions\CheckInException;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    public function __construct(
        private DogCreditService $credits,
        private AutoReplenishService $autoReplenish,
    ) {}

    public function execute(Dog $dog, User $staff, Tenant $tenant, bool $manualOverride, ?string $note): Attendance
    {
        $this->assertEligible($dog);
        $this->assertNotCheckedInToday($dog, $tenant);

        $override = $manualOverride || ! $tenant->checkin_block_at_zero;

        if ($dog->credit_balance <= 0 && ! $this->hasActiveUnlimitedPass($dog) && ! $override) {
            throw new CheckInException('Cannot check in dog with zero credits.');
        }

        return DB::transaction(function () use ($dog, $staff, $tenant, $override, $note) {
            $attendance = Attendance::create([
                'tenant_id' => $tenant->id,
                'dog_id' => $dog->id,
                'checked_in_by' => $staff->id,
                'checked_in_at' => now(),
                'zero_credit_override' => $override,
                'override_note' => $note,
            ]);

            $this->attemptAutoReplenish($dog, $attendance, $tenant);

            $this->credits->deductForAttendance($attendance);

            return $attendance;
        });
    }

    private function assertEligible(Dog $dog): void
    {
        if (! $dog->status->isEligible()) {
            throw new CheckInException("{$dog->name} is {$dog->status->label()} and cannot be checked in.");
        }
    }

    private function assertNotCheckedInToday(Dog $dog, Tenant $tenant): void
    {
        $startOfToday = now($tenant->timezone ?? 'UTC')->startOfDay()->utc();

        $alreadyIn = Attendance::where('dog_id', $dog->id)
            ->where('checked_in_at', '>=', $startOfToday)
            ->whereNull('checked_out_at')
            ->exists();

        if ($alreadyIn) {
            throw new CheckInException('Dog is already checked in today.');
        }
    }

    private function hasActiveUnlimitedPass(Dog $dog): bool
    {
        return (bool) $dog->unlimited_pass_expires_at?->isFuture();
    }

    private function attemptAutoReplenish(Dog $dog, Attendance $attendance, Tenant $tenant): void
    {
        if ($dog->credit_balance > 0 || $this->hasActiveUnlimitedPass($dog) || $tenant->checkin_block_at_zero) {
            return;
        }

        if ($dog->auto_replenish_enabled && $dog->auto_replenish_package_id) {
            $this->assertPaymentMethod($dog->customer, 'Auto-replenish');

            if (! $this->autoReplenish->triggerSync($dog, $attendance)) {
                throw new CheckInException('Auto-replenish charge failed. Check payment method.');
            }

            return;
        }

        if ($tenant->auto_charge_at_zero_package_id) {
            $package = Package::find($tenant->auto_charge_at_zero_package_id);

            if ($package) {
                $this->assertPaymentMethod($dog->customer, 'Auto-charge');

                if (! $this->autoReplenish->triggerForPackage($dog, $package, $attendance)) {
                    throw new CheckInException('Auto-charge failed. Check payment method.');
                }
            }
        }
    }

    private function assertPaymentMethod(?Customer $customer, string $context): void
    {
        if (! $customer?->stripe_payment_method_id) {
            $name = $customer?->name ?? 'Customer';
            throw new CheckInException("{$context} failed: {$name} has no card on file.");
        }
    }
}
