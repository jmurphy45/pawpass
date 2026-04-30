<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Jobs\ProcessAutoReplenishJob;
use App\Models\Attendance;
use App\Models\CreditLedger;
use App\Models\Dog;
use App\Models\Order;
use App\Models\OrderDog;
use App\Models\Subscription;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DogCreditService
{
    public function issueFromOrder(Order $order, Dog $dog): void
    {
        DB::transaction(function () use ($order, $dog) {
            $credits = $order->package->credit_count;
            $newBalance = $dog->credit_balance + $credits;

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'purchase',
                'delta' => $credits,
                'balance_after' => $newBalance,
                'order_id' => $order->id,
            ]);

            $dog->increment('credit_balance', $credits);

            OrderDog::where('order_id', $order->id)
                ->where('dog_id', $dog->id)
                ->update(['credits_issued' => $credits]);
        });
    }

    public function issueUnlimitedPass(Order $order, Dog $dog): void
    {
        DB::transaction(function () use ($order, $dog) {
            $durationDays = $order->package->duration_days ?? 30;
            $credits = now()->daysInMonth;
            $newBalance = $dog->credit_balance + $credits;
            $tz = app('current.tenant')?->timezone ?? 'UTC';
            $expiresAt = now($tz)->startOfDay()->addDays($durationDays)->utc();

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'purchase',
                'delta' => $credits,
                'balance_after' => $newBalance,
                'order_id' => $order->id,
                'expires_at' => $expiresAt,
            ]);

            $dog->increment('credit_balance', $credits);
            $dog->update([
                'unlimited_pass_expires_at' => $expiresAt,
            ]);

            OrderDog::where('order_id', $order->id)
                ->where('dog_id', $dog->id)
                ->update(['credits_issued' => $credits]);
        });
    }

    public function revokeUnlimitedPass(Order $order, Dog $dog): void
    {
        DB::transaction(function () use ($order, $dog) {
            $remaining = $dog->credit_balance;

            if ($remaining <= 0) {
                return;
            }

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'refund',
                'delta' => -$remaining,
                'balance_after' => 0,
                'order_id' => $order->id,
            ]);

            $dog->update([
                'credit_balance' => 0,
                'unlimited_pass_expires_at' => null,
            ]);
        });
    }

    public function deductForAttendance(Attendance $attendance): void
    {
        $dog = $attendance->dog;

        if ($dog->unlimited_pass_expires_at && $dog->unlimited_pass_expires_at->isFuture()) {
            return;
        }

        if ($dog->credit_balance <= 0 && ! $attendance->zero_credit_override) {
            throw new InsufficientCreditsException("Dog {$dog->id} has no credits.");
        }

        DB::transaction(function () use ($attendance, $dog) {
            $locked = Dog::lockForUpdate()->find($dog->id);
            $newBalance = $locked->credit_balance - 1;

            CreditLedger::create([
                'tenant_id' => $locked->tenant_id,
                'dog_id' => $locked->id,
                'type' => 'deduction',
                'delta' => -1,
                'balance_after' => $newBalance,
                'attendance_id' => $attendance->id,
            ]);

            $locked->decrement('credit_balance', 1);
        });

        $this->dispatchCreditAlert($dog->fresh());
    }

    private function dispatchCreditAlert(Dog $dog): void
    {
        // Dogs with auto-replenish: dispatch job when empty, skip credit notifications entirely.
        // The customer receives an auto_replenish.succeeded confirmation instead.
        if ($dog->auto_replenish_enabled) {
            if ($dog->credit_balance <= 0) {
                ProcessAutoReplenishJob::dispatch($dog->id);
            }

            return;
        }

        // Non-auto-replenish: 24h notification dedup
        if ($dog->credits_alert_sent_at && $dog->credits_alert_sent_at->isAfter(now()->subHours(24))) {
            return;
        }

        $threshold = $dog->tenant->low_credit_threshold ?? 2;

        if ($dog->credit_balance <= 0) {
            $type = 'credits.empty';
        } elseif ($dog->credit_balance <= $threshold) {
            $type = 'credits.low';
        } else {
            return;
        }

        $userId = $dog->customer->user_id;

        if (! $userId) {
            return;
        }

        app(NotificationService::class)->enqueueGrouped($type, $dog->tenant_id, $userId, $dog->id);

        $dog->update(['credits_alert_sent_at' => now()]);
    }

    public function removeAllOnRefund(Order $order, Dog $dog): void
    {
        DB::transaction(function () use ($order, $dog) {
            $remaining = $dog->credit_balance;

            if ($remaining <= 0) {
                return;
            }

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'refund',
                'delta' => -$remaining,
                'balance_after' => 0,
                'order_id' => $order->id,
            ]);

            $dog->update(['credit_balance' => 0]);
        });
    }

    public function addGoodwill(Dog $dog, int $credits, string $note, User $by): void
    {
        DB::transaction(function () use ($dog, $credits, $note, $by) {
            $newBalance = $dog->credit_balance + $credits;

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'goodwill',
                'delta' => $credits,
                'balance_after' => $newBalance,
                'note' => $note,
                'created_by' => $by->id,
            ]);

            $dog->increment('credit_balance', $credits);
        });
    }

    public function applyCorrection(Dog $dog, int $delta, string $note, User $by): void
    {
        $type = $delta >= 0 ? 'correction_add' : 'correction_remove';

        DB::transaction(function () use ($dog, $delta, $type, $note, $by) {
            $newBalance = $dog->credit_balance + $delta;

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => $type,
                'delta' => $delta,
                'balance_after' => $newBalance,
                'note' => $note,
                'created_by' => $by->id,
            ]);

            if ($delta >= 0) {
                $dog->increment('credit_balance', $delta);
            } else {
                $dog->decrement('credit_balance', abs($delta));
            }
        });
    }

    public function transfer(Dog $from, Dog $to, int $credits): void
    {
        if ($from->customer_id !== $to->customer_id) {
            throw new InvalidArgumentException('Transfer only allowed within the same customer account.');
        }

        if ($from->credit_balance < $credits) {
            throw new InsufficientCreditsException("Dog {$from->id} has insufficient credits for transfer.");
        }

        DB::transaction(function () use ($from, $to, $credits) {
            $fromNewBalance = $from->credit_balance - $credits;
            $toNewBalance = $to->credit_balance + $credits;

            $outEntry = CreditLedger::create([
                'tenant_id' => $from->tenant_id,
                'dog_id' => $from->id,
                'type' => 'transfer_out',
                'delta' => -$credits,
                'balance_after' => $fromNewBalance,
            ]);

            CreditLedger::create([
                'tenant_id' => $to->tenant_id,
                'dog_id' => $to->id,
                'type' => 'transfer_in',
                'delta' => $credits,
                'balance_after' => $toNewBalance,
                'parent_ledger_id' => $outEntry->id,
            ]);

            $from->decrement('credit_balance', $credits);
            $to->increment('credit_balance', $credits);
        });
    }

    public function issueUnlimitedPassFromSubscription(
        Subscription $subscription,
        Dog $dog,
        \DateTimeInterface $expiresAt,
    ): void {
        DB::transaction(function () use ($subscription, $dog, $expiresAt) {
            $credits = now()->daysInMonth;
            $newBalance = $dog->credit_balance + $credits;

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'subscription',
                'delta' => $credits,
                'balance_after' => $newBalance,
                'subscription_id' => $subscription->id,
                'expires_at' => $expiresAt,
            ]);

            $dog->increment('credit_balance', $credits);
            $dog->update([
                'unlimited_pass_expires_at' => $expiresAt,
            ]);
        });
    }

    public function issueFromSubscription(Subscription $subscription, Dog $dog, DateTimeInterface $periodEnd): void
    {
        DB::transaction(function () use ($subscription, $dog, $periodEnd) {
            $credits = $subscription->package->credit_count;
            $newBalance = $dog->credit_balance + $credits;

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'subscription',
                'delta' => $credits,
                'balance_after' => $newBalance,
                'subscription_id' => $subscription->id,
                'expires_at' => $periodEnd,
            ]);

            $dog->increment('credit_balance', $credits);
            $dog->update(['credits_expire_at' => $periodEnd]);
        });
    }

    public function expireCredits(Dog $dog): void
    {
        DB::transaction(function () use ($dog) {
            $remaining = $dog->credit_balance;

            if ($remaining <= 0) {
                return;
            }

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'expiry_removal',
                'delta' => -$remaining,
                'balance_after' => 0,
            ]);

            $dog->update(['credit_balance' => 0]);
        });
    }

    public function expireUnlimitedPass(Dog $dog): void
    {
        DB::transaction(function () use ($dog) {
            $passEntry = CreditLedger::allTenants()
                ->where('dog_id', $dog->id)
                ->where('expires_at', $dog->unlimited_pass_expires_at)
                ->orderByDesc('created_at')
                ->first();

            $passCredits = $passEntry ? min($dog->credit_balance, $passEntry->delta) : 0;

            if ($passCredits <= 0) {
                $dog->update(['unlimited_pass_expires_at' => null]);

                return;
            }

            $newBalance = $dog->credit_balance - $passCredits;

            CreditLedger::create([
                'tenant_id' => $dog->tenant_id,
                'dog_id' => $dog->id,
                'type' => 'expiry_removal',
                'delta' => -$passCredits,
                'balance_after' => $newBalance,
            ]);

            $dog->update([
                'credit_balance' => $newBalance,
                'unlimited_pass_expires_at' => null,
            ]);
        });
    }
}
