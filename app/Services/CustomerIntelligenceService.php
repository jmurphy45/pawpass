<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CustomerIntelligenceService
{
    private const PRICE_SENSITIVE_THRESHOLD = 50.0;

    private const MIN_VISITS_FOR_FIT = 4;

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function daysSinceExpr(string $col): string
    {
        return $this->isPostgres()
            ? "EXTRACT(EPOCH FROM now() - ({$col})::timestamptz)::integer / 86400"
            : "CAST(julianday('now') - julianday({$col}) AS INTEGER)";
    }

    public function churnRisk(string $tenantId): array
    {
        $daysSince = $this->daysSinceExpr('MAX(a.checked_in_at)');
        $now30 = now()->subDays(30)->toDateTimeString();
        $now60 = now()->subDays(60)->toDateTimeString();

        $rows = DB::select("
            SELECT
                c.id                AS customer_id,
                c.name              AS customer_name,
                c.email,
                MAX(a.checked_in_at) AS last_visit_at,
                {$daysSince}        AS days_since_last_visit,
                COUNT(CASE WHEN a.checked_in_at >= ? THEN 1 END)                           AS visits_last_30,
                COUNT(CASE WHEN a.checked_in_at >= ? AND a.checked_in_at < ? THEN 1 END)   AS visits_prior_30,
                MAX(CASE WHEN d.credit_balance <= 0 THEN 1 ELSE 0 END)                     AS has_zero_credit_dog,
                COUNT(CASE WHEN o.status = 'paid' AND o.created_at >= ? THEN 1 END)         AS recent_orders
            FROM customers c
            JOIN dogs d ON d.customer_id = c.id AND d.deleted_at IS NULL
            LEFT JOIN attendances a ON a.dog_id = d.id AND a.tenant_id = ?
            LEFT JOIN orders o ON o.customer_id = c.id AND o.tenant_id = ?
            WHERE c.tenant_id = ?
              AND c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.email
            HAVING MAX(a.checked_in_at) IS NOT NULL
            ORDER BY days_since_last_visit DESC
        ", [$now30, $now60, $now30, $now30, $tenantId, $tenantId, $tenantId]);

        $results = [];
        foreach ($rows as $row) {
            $days = (int) $row->days_since_last_visit;

            $riskLevel = match (true) {
                $days > 60 => 'red',
                $days > 30 => 'amber',
                default => 'green',
            };

            if ($riskLevel === 'green') {
                continue;
            }

            $results[] = [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'email' => $row->email,
                'last_visit_at' => $row->last_visit_at,
                'days_since_last_visit' => $days,
                'visits_last_30' => (int) $row->visits_last_30,
                'visits_prior_30' => (int) $row->visits_prior_30,
                'freq_delta' => (int) $row->visits_last_30 - (int) $row->visits_prior_30,
                'has_zero_credit_dog' => (bool) $row->has_zero_credit_dog,
                'recent_orders' => (int) $row->recent_orders,
                'risk_level' => $riskLevel,
            ];
        }

        return $results;
    }

    public function priceSensitivity(string $tenantId): array
    {
        $rows = DB::select("
            SELECT
                c.id                                                                     AS customer_id,
                c.name                                                                   AS customer_name,
                c.email,
                COUNT(DISTINCT o.id)                                                     AS total_paid_orders,
                COUNT(DISTINCT pr.order_id)                                              AS promo_orders,
                ROUND(COUNT(DISTINCT pr.order_id) * 100.0 / NULLIF(COUNT(DISTINCT o.id), 0), 1) AS promo_pct,
                COALESCE(SUM(pr.discount_amount_cents), 0)                               AS total_discount_cents,
                COALESCE(AVG(pr.discount_amount_cents), 0)                               AS avg_discount_cents
            FROM customers c
            JOIN orders o ON o.customer_id = c.id AND o.tenant_id = ? AND o.status = 'paid'
            LEFT JOIN promotion_redemptions pr ON pr.order_id = o.id
            WHERE c.tenant_id = ?
              AND c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.email
            HAVING COUNT(DISTINCT o.id) > 0
            ORDER BY promo_pct DESC, total_discount_cents DESC
        ", [$tenantId, $tenantId]);

        $results = [];
        foreach ($rows as $row) {
            $promoPct = (float) $row->promo_pct;

            if ($promoPct < self::PRICE_SENSITIVE_THRESHOLD) {
                continue;
            }

            $totalOrders = (int) $row->total_paid_orders;
            $promoOrders = (int) $row->promo_orders;

            $results[] = [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'email' => $row->email,
                'total_paid_orders' => $totalOrders,
                'promo_orders' => $promoOrders,
                'promo_pct' => $promoPct,
                'total_discount_cents' => (int) $row->total_discount_cents,
                'avg_discount_cents' => (int) round($row->avg_discount_cents),
                'never_paid_full' => $totalOrders > 0 && $promoOrders === $totalOrders,
            ];
        }

        return $results;
    }

    public function packageFit(string $tenantId): array
    {
        $now90 = now()->subDays(90)->toDateTimeString();

        $visitRows = DB::select('
            SELECT
                c.id    AS customer_id,
                c.name  AS customer_name,
                c.email,
                COUNT(a.id) AS visits_90_days
            FROM customers c
            JOIN dogs d ON d.customer_id = c.id AND d.deleted_at IS NULL
            JOIN attendances a ON a.dog_id = d.id AND a.tenant_id = ? AND a.checked_in_at >= ?
            WHERE c.tenant_id = ?
              AND c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.email
            HAVING COUNT(a.id) >= ?
            ORDER BY visits_90_days DESC
        ', [$tenantId, $now90, $tenantId, self::MIN_VISITS_FOR_FIT]);

        if (empty($visitRows)) {
            return [];
        }

        $lastPackages = $this->lastPackagePerCustomer($tenantId);
        $availPackages = DB::select("
            SELECT id, name, credit_count, price
            FROM packages
            WHERE tenant_id = ?
              AND type = 'one_time'
              AND is_active = true
              AND deleted_at IS NULL
              AND credit_count IS NOT NULL
            ORDER BY credit_count ASC
        ", [$tenantId]);

        if (empty($availPackages)) {
            return [];
        }

        $results = [];
        foreach ($visitRows as $row) {
            $visitsPerMonth = round($row->visits_90_days / 3.0, 1);
            $creditsNeeded = (int) ceil($visitsPerMonth);
            $currentCredits = (int) ($lastPackages[$row->customer_id]->credit_count ?? 0);

            $suggested = null;
            foreach ($availPackages as $pkg) {
                if ((int) $pkg->credit_count >= $creditsNeeded && (int) $pkg->credit_count > $currentCredits) {
                    $suggested = $pkg;
                    break;
                }
            }

            if ($suggested === null) {
                continue;
            }

            $results[] = [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'email' => $row->email,
                'visits_90_days' => (int) $row->visits_90_days,
                'visits_per_month' => $visitsPerMonth,
                'current_package_name' => $lastPackages[$row->customer_id]->name ?? null,
                'current_credit_count' => $currentCredits > 0 ? $currentCredits : null,
                'suggested_package_id' => $suggested->id,
                'suggested_package_name' => $suggested->name,
                'suggested_credit_count' => (int) $suggested->credit_count,
                'suggested_price' => (float) $suggested->price,
            ];
        }

        return $results;
    }

    private function lastPackagePerCustomer(string $tenantId): array
    {
        if ($this->isPostgres()) {
            $rows = DB::select("
                SELECT DISTINCT ON (o.customer_id)
                    o.customer_id,
                    p.name,
                    p.credit_count
                FROM orders o
                JOIN packages p ON p.id = o.package_id
                WHERE o.tenant_id = ?
                  AND o.status = 'paid'
                  AND p.type = 'one_time'
                ORDER BY o.customer_id, o.created_at DESC
            ", [$tenantId]);
        } else {
            $rows = DB::select("
                SELECT o.customer_id, p.name, p.credit_count
                FROM orders o
                JOIN packages p ON p.id = o.package_id
                WHERE o.tenant_id = ?
                  AND o.status = 'paid'
                  AND p.type = 'one_time'
                  AND o.id = (
                      SELECT o2.id
                      FROM orders o2
                      WHERE o2.customer_id = o.customer_id
                        AND o2.tenant_id = ?
                        AND o2.status = 'paid'
                      ORDER BY o2.created_at DESC
                      LIMIT 1
                  )
            ", [$tenantId, $tenantId]);
        }

        $map = [];
        foreach ($rows as $row) {
            $map[$row->customer_id] = $row;
        }

        return $map;
    }
}
