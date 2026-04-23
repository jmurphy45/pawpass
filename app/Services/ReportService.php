<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReportService
{
    private function dateGroup(string $column, string $groupBy): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return match ($groupBy) {
                'week' => "strftime('%Y-%W', {$column})",
                'day' => "strftime('%Y-%m-%d', {$column})",
                default => "strftime('%Y-%m', {$column})",
            };
        }

        return match ($groupBy) {
            'week' => "to_char(date_trunc('week', ({$column})::timestamptz), 'IYYY-IW')",
            'day' => "to_char(date_trunc('day', ({$column})::timestamptz), 'YYYY-MM-DD')",
            default => "to_char(date_trunc('month', ({$column})::timestamptz), 'YYYY-MM')",
        };
    }

    /**
     * Report 1 — Revenue Summary
     */
    public function revenue(string $tenantId, string $from, string $to, string $groupBy = 'month'): array
    {
        $periodExpr = $this->dateGroup('created_at', $groupBy);

        $rows = DB::table('orders')
            ->selectRaw("{$periodExpr} AS period")
            ->selectRaw('SUM(total_amount) AS gross')
            ->selectRaw('SUM(COALESCE(platform_fee_amount_cents, ROUND(total_amount * platform_fee_pct)) / 100.0) AS fee')
            ->selectRaw('SUM(total_amount) - SUM(COALESCE(platform_fee_amount_cents, ROUND(total_amount * platform_fee_pct)) / 100.0) AS net')
            ->selectRaw('SUM(COALESCE(tax_amount_cents, 0)) / 100.0 AS tax_total')
            ->selectRaw('SUM(COALESCE(processing_fee_amount_cents, 0)) / 100.0 AS processing_fee_total')
            ->selectRaw('COUNT(*) AS orders')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw($periodExpr)
            ->orderByRaw($periodExpr)
            ->get();

        return $rows->map(fn ($r) => [
            'period' => $r->period,
            'gross' => (float) $r->gross,
            'fee' => (float) $r->fee,
            'net' => (float) $r->net,
            'tax_total' => (float) $r->tax_total,
            'processing_fee_total' => (float) $r->processing_fee_total,
            'orders' => (int) $r->orders,
        ])->all();
    }

    /**
     * Report 2 — Payout Forecast
     */
    public function payoutForecast(string $tenantId): array
    {
        $since = now()->subDays(30)->toDateTimeString();

        $row = DB::table('orders')
            ->selectRaw('SUM(total_amount) AS gross')
            ->selectRaw('SUM(total_amount * platform_fee_pct / 100) AS fee')
            ->selectRaw('COUNT(*) AS orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('created_at', '>=', $since)
            ->first();

        $gross = (float) ($row?->gross ?? 0);
        $fee = (float) ($row?->fee ?? 0);

        return [
            'gross' => $gross,
            'fee' => $fee,
            'net' => $gross - $fee,
            'orders' => (int) ($row?->orders ?? 0),
            'period' => 'last_30_days',
        ];
    }

    /**
     * Report 3 — Package Performance
     */
    public function packages(string $tenantId, string $from, string $to): array
    {
        $rows = DB::table('orders')
            ->join('packages', 'orders.package_id', '=', 'packages.id')
            ->selectRaw('packages.id AS package_id')
            ->selectRaw('packages.name AS package_name')
            ->selectRaw('packages.type AS package_type')
            ->selectRaw('COUNT(orders.id) AS orders')
            ->selectRaw('SUM(orders.total_amount) AS revenue')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('packages.id', 'packages.name', 'packages.type')
            ->orderByRaw('SUM(orders.total_amount) DESC')
            ->get();

        return $rows->map(fn ($r) => [
            'package_id' => $r->package_id,
            'package_name' => $r->package_name,
            'package_type' => $r->package_type,
            'orders' => (int) $r->orders,
            'revenue' => (float) $r->revenue,
        ])->all();
    }

    /**
     * Report 4 — Credit Issuance & Usage
     */
    public function credits(string $tenantId, string $from, string $to): array
    {
        $rows = DB::table('credit_ledger')
            ->selectRaw('type')
            ->selectRaw('SUM(delta) AS total_delta')
            ->selectRaw('COUNT(*) AS entries')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('type')
            ->orderBy('type')
            ->get();

        return $rows->map(fn ($r) => [
            'type' => $r->type,
            'total_delta' => (int) $r->total_delta,
            'entries' => (int) $r->entries,
        ])->all();
    }

    /**
     * Report 5 — Customer LTV
     */
    public function customersLtv(string $tenantId, string $from, string $to): array
    {
        $rows = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->selectRaw('customers.id AS customer_id')
            ->selectRaw('customers.name AS customer_name')
            ->selectRaw('COUNT(orders.id) AS orders')
            ->selectRaw('SUM(orders.total_amount) AS total_spend')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('customers.id', 'customers.name')
            ->orderByRaw('SUM(orders.total_amount) DESC')
            ->get();

        return $rows->map(fn ($r) => [
            'customer_id' => $r->customer_id,
            'customer_name' => $r->customer_name,
            'orders' => (int) $r->orders,
            'total_spend' => (float) $r->total_spend,
        ])->all();
    }

    /**
     * Report 6 — Attendance Summary
     */
    public function attendance(string $tenantId, string $from, string $to, string $groupBy = 'day'): array
    {
        $periodExpr = $this->dateGroup('checked_in_at', $groupBy);

        $rows = DB::table('attendances')
            ->selectRaw("{$periodExpr} AS period")
            ->selectRaw('COUNT(*) AS checkins')
            ->selectRaw('COUNT(DISTINCT dog_id) AS unique_dogs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('checked_in_at', [$from, $to])
            ->groupByRaw($periodExpr)
            ->orderByRaw($periodExpr)
            ->get();

        return $rows->map(fn ($r) => [
            'period' => $r->period,
            'checkins' => (int) $r->checkins,
            'unique_dogs' => (int) $r->unique_dogs,
        ])->all();
    }

    /**
     * Report 7 — Daily Roster History
     */
    public function rosterHistory(string $tenantId, string $date): array
    {
        $rows = DB::table('attendances')
            ->join('dogs', 'attendances.dog_id', '=', 'dogs.id')
            ->join('customers', 'dogs.customer_id', '=', 'customers.id')
            ->selectRaw('attendances.id')
            ->selectRaw('dogs.name AS dog_name')
            ->selectRaw('customers.name AS customer_name')
            ->selectRaw('attendances.checked_in_at')
            ->selectRaw('attendances.checked_out_at')
            ->where('attendances.tenant_id', $tenantId)
            ->whereRaw('date(attendances.checked_in_at) = ?', [$date])
            ->orderBy('attendances.checked_in_at')
            ->get();

        return $rows->map(fn ($r) => [
            'id' => $r->id,
            'dog_name' => $r->dog_name,
            'customer_name' => $r->customer_name,
            'checked_in_at' => $r->checked_in_at,
            'checked_out_at' => $r->checked_out_at,
        ])->all();
    }

    /**
     * Report 8 — Zero & Low Credit Dogs
     */
    public function creditStatus(string $tenantId): array
    {
        $dogs = DB::table('dogs')
            ->join('customers', 'dogs.customer_id', '=', 'customers.id')
            ->selectRaw('dogs.id')
            ->selectRaw('dogs.name AS dog_name')
            ->selectRaw('customers.name AS customer_name')
            ->selectRaw('dogs.credit_balance')
            ->where('dogs.tenant_id', $tenantId)
            ->whereNull('dogs.deleted_at')
            ->where('dogs.credit_balance', '<=', 3)
            ->orderBy('dogs.credit_balance')
            ->get();

        $zero = $dogs->filter(fn ($d) => $d->credit_balance <= 0)->values();
        $low = $dogs->filter(fn ($d) => $d->credit_balance > 0 && $d->credit_balance <= 3)->values();

        $map = fn ($d) => [
            'id' => $d->id,
            'dog_name' => $d->dog_name,
            'customer_name' => $d->customer_name,
            'credit_balance' => (int) $d->credit_balance,
        ];

        return [
            'zero' => $zero->map($map)->all(),
            'low' => $low->map($map)->all(),
        ];
    }

    /**
     * Report 9 — Staff Activity Log
     */
    public function staffActivity(string $tenantId, string $from, string $to, ?string $userId = null): array
    {
        $query = DB::table('attendances')
            ->join('users', 'attendances.checked_in_by', '=', 'users.id')
            ->selectRaw('users.id AS user_id')
            ->selectRaw('users.name AS user_name')
            ->selectRaw('COUNT(*) AS checkins')
            ->where('attendances.tenant_id', $tenantId)
            ->whereBetween('attendances.checked_in_at', [$from, $to])
            ->groupBy('users.id', 'users.name')
            ->orderByRaw('COUNT(*) DESC');

        if ($userId) {
            $query->where('attendances.checked_in_by', $userId);
        }

        $rows = $query->get();

        return $rows->map(fn ($r) => [
            'user_id' => $r->user_id,
            'user_name' => $r->user_name,
            'checkins' => (int) $r->checkins,
        ])->all();
    }

    /**
     * Report 10 — Platform Revenue
     */
    public function platformRevenue(string $from, string $to): array
    {
        $periodExpr = $this->dateGroup('created_at', 'month');

        $rows = DB::table('orders')
            ->selectRaw("{$periodExpr} AS period")
            ->selectRaw('SUM(total_amount) AS gross')
            ->selectRaw('SUM(COALESCE(platform_fee_amount_cents, ROUND(total_amount * platform_fee_pct)) / 100.0) AS fee')
            ->selectRaw('COUNT(*) AS orders')
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw($periodExpr)
            ->orderByRaw($periodExpr)
            ->get();

        return $rows->map(fn ($r) => [
            'period' => $r->period,
            'gross' => (float) $r->gross,
            'fee' => (float) $r->fee,
            'orders' => (int) $r->orders,
        ])->all();
    }

    /**
     * Report 11 — Tenant Health
     */
    public function tenantHealth(): array
    {
        $since = now()->subDays(30)->toDateTimeString();

        $tenants = DB::table('tenants')
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'slug', 'status', 'plan']);

        return $tenants->map(function ($t) use ($since) {
            $dogs = DB::table('dogs')->where('tenant_id', $t->id)->whereNull('deleted_at')->count();
            $customers = DB::table('customers')->where('tenant_id', $t->id)->whereNull('deleted_at')->count();
            $orders = DB::table('orders')
                ->where('tenant_id', $t->id)
                ->where('status', 'paid')
                ->where('created_at', '>=', $since)
                ->count();

            return [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'status' => $t->status,
                'plan' => $t->plan,
                'dogs' => $dogs,
                'customers' => $customers,
                'orders_30_days' => $orders,
            ];
        })->all();
    }

    /**
     * Report 12 — Notification Delivery
     */
    public function notificationDelivery(string $from, string $to, ?string $tenantId = null): array
    {
        $query = DB::table('notification_logs')
            ->selectRaw('channel')
            ->selectRaw('status')
            ->selectRaw('COUNT(*) AS count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('channel', 'status')
            ->orderBy('channel')
            ->orderBy('status');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $rows = $query->get();

        return $rows->map(fn ($r) => [
            'channel' => $r->channel,
            'status' => $r->status,
            'count' => (int) $r->count,
        ])->all();
    }

    /**
     * Report 13 — Promotion Redemptions
     */
    public function promotions(string $tenantId, string $from, string $to): array
    {
        $rows = DB::table('promotions')
            ->leftJoin('promotion_redemptions', function ($join) use ($from, $to) {
                $join->on('promotion_redemptions.promotion_id', '=', 'promotions.id')
                    ->whereBetween('promotion_redemptions.created_at', [$from, $to]);
            })
            ->selectRaw('promotions.id AS promotion_id')
            ->selectRaw('promotions.code')
            ->selectRaw('promotions.name')
            ->selectRaw('promotions.type')
            ->selectRaw('promotions.discount_value')
            ->selectRaw('COUNT(promotion_redemptions.id) AS redemptions')
            ->selectRaw('SUM(COALESCE(promotion_redemptions.discount_amount_cents, 0)) AS total_discount_cents')
            ->where('promotions.tenant_id', $tenantId)
            ->whereNull('promotions.deleted_at')
            ->groupBy('promotions.id', 'promotions.code', 'promotions.name', 'promotions.type', 'promotions.discount_value')
            ->having(DB::raw('COUNT(promotion_redemptions.id)'), '>', 0)
            ->orderByRaw('COUNT(promotion_redemptions.id) DESC')
            ->get();

        return $rows->map(fn ($r) => [
            'promotion_id' => $r->promotion_id,
            'code' => $r->code,
            'name' => $r->name,
            'type' => $r->type,
            'discount_value' => (int) $r->discount_value,
            'redemptions' => (int) $r->redemptions,
            'total_discount_cents' => (int) $r->total_discount_cents,
        ])->all();
    }

    /**
     * Report 14 — Boarding Revenue
     */
    public function boardingRevenue(string $tenantId, string $from, string $to, string $groupBy = 'month'): array
    {
        $periodExpr = $this->dateGroup('created_at', $groupBy);

        $rows = DB::table('orders')
            ->selectRaw("{$periodExpr} AS period")
            ->selectRaw('SUM(total_amount) AS gross')
            ->selectRaw('SUM(COALESCE(platform_fee_amount_cents, ROUND(total_amount * platform_fee_pct)) / 100.0) AS fee')
            ->selectRaw('SUM(total_amount) - SUM(COALESCE(platform_fee_amount_cents, ROUND(total_amount * platform_fee_pct)) / 100.0) AS net')
            ->selectRaw('COUNT(*) AS orders')
            ->where('tenant_id', $tenantId)
            ->where('type', 'boarding')
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw($periodExpr)
            ->orderByRaw($periodExpr)
            ->get();

        return $rows->map(fn ($r) => [
            'period' => $r->period,
            'gross' => (float) $r->gross,
            'fee' => (float) $r->fee,
            'net' => (float) $r->net,
            'orders' => (int) $r->orders,
        ])->all();
    }

    /**
     * Report 15 — Outstanding Customer Balances
     */
    public function outstandingBalances(string $tenantId): array
    {
        $rows = DB::table('customers')
            ->selectRaw('id AS customer_id')
            ->selectRaw('name AS customer_name')
            ->selectRaw('email')
            ->selectRaw('outstanding_balance_cents')
            ->selectRaw('charge_pending_at')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('outstanding_balance_cents', '>', 0)
            ->orderByDesc('outstanding_balance_cents')
            ->get();

        return $rows->map(fn ($r) => [
            'customer_id' => $r->customer_id,
            'customer_name' => $r->customer_name,
            'email' => $r->email,
            'outstanding_balance_cents' => (int) $r->outstanding_balance_cents,
            'charge_pending_at' => $r->charge_pending_at,
        ])->all();
    }
}
