<template>
  <AdminLayout>
    <div class="su-page">

      <!-- Header -->
      <div class="su-header">
        <div>
          <h1 class="su-title">SMS Usage</h1>
          <p class="su-subtitle">Track your monthly SMS segment usage and billing history</p>
        </div>
        <a :href="route('admin.notifications.broadcast')" class="btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="su-btn-icon">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m-1.394 5.52a23.926 23.926 0 0 1-3.25 2.88" />
          </svg>
          New Broadcast
        </a>
      </div>

      <!-- Current period stats -->
      <div class="su-stats-grid">
        <div class="su-stat-card">
          <div class="su-stat-icon su-stat-icon--blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
            </svg>
          </div>
          <div>
            <p class="su-stat-label">Used this month</p>
            <p class="su-stat-value">{{ smsUsed.toLocaleString() }}</p>
            <p class="su-stat-sub">segments · {{ currentPeriodLabel }}</p>
          </div>
        </div>

        <div class="su-stat-card">
          <div class="su-stat-icon" :class="smsQuota > 0 ? 'su-stat-icon--green' : 'su-stat-icon--gray'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z" />
            </svg>
          </div>
          <div>
            <p class="su-stat-label">Included quota</p>
            <p class="su-stat-value">{{ smsQuota > 0 ? smsQuota.toLocaleString() : '—' }}</p>
            <p class="su-stat-sub">{{ smsQuota === 0 ? `Pay $0.04/segment (${planLabel} plan)` : `segments/month on ${planLabel}` }}</p>
          </div>
        </div>

        <div class="su-stat-card">
          <div class="su-stat-icon" :class="overage > 0 ? 'su-stat-icon--amber' : 'su-stat-icon--green'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
            </svg>
          </div>
          <div>
            <p class="su-stat-label">Current overage</p>
            <p class="su-stat-value" :class="overage > 0 ? 'su-value--amber' : ''">
              {{ overage > 0 ? overage.toLocaleString() : '0' }}
            </p>
            <p class="su-stat-sub" :class="overage > 0 ? 'su-sub--amber' : ''">
              {{ overage > 0 ? `~$${(overage * 0.04).toFixed(2)} will be billed` : 'within quota — no charge' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Current month gauge -->
      <div v-if="smsQuota > 0" class="su-gauge-card">
        <div class="su-gauge-header">
          <h2 class="su-gauge-title">{{ currentPeriodLabel }} usage</h2>
          <div class="su-gauge-pct" :class="gaugeClass">{{ usedPct }}%</div>
        </div>

        <div class="su-gauge-track">
          <div class="su-gauge-fill" :style="{ width: usedPct + '%' }" :class="gaugeClass"></div>
          <div
            v-if="usedPct > 5"
            class="su-gauge-label-inside"
            :style="{ left: Math.max(4, usedPct - 2) + '%' }"
          >
            {{ smsUsed.toLocaleString() }}
          </div>
        </div>

        <div class="su-gauge-legend">
          <div class="su-gl-item">
            <span class="su-gl-dot su-gl-dot--used"></span>
            <span>Used ({{ smsUsed.toLocaleString() }})</span>
          </div>
          <div class="su-gl-item" v-if="remaining > 0">
            <span class="su-gl-dot su-gl-dot--remaining"></span>
            <span>Remaining ({{ remaining.toLocaleString() }})</span>
          </div>
          <div class="su-gl-item" v-if="overage > 0">
            <span class="su-gl-dot su-gl-dot--overage"></span>
            <span>Overage ({{ overage.toLocaleString() }}) · ~${{ (overage * 0.04).toFixed(2) }}</span>
          </div>
          <span class="su-gauge-quota-label">Quota: {{ smsQuota.toLocaleString() }}</span>
        </div>
      </div>

      <!-- Pay-per-segment info (no quota plans) -->
      <div v-else class="su-ppsg-card">
        <div class="su-ppsg-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
          </svg>
        </div>
        <div>
          <p class="su-ppsg-title">Pay-per-segment pricing</p>
          <p class="su-ppsg-body">Your <strong>{{ planLabel }}</strong> plan has no included SMS segments. You're billed $0.04 per segment at month end. Upgrade to Pro or Business to get included segments.</p>
        </div>
      </div>

      <!-- History table -->
      <div class="su-history-card">
        <div class="su-history-header">
          <h2 class="su-history-title">Monthly history</h2>
          <p class="su-history-sub">Last 12 months</p>
        </div>

        <div v-if="history.length === 0" class="su-history-empty">
          No SMS usage recorded yet. Send a broadcast to get started.
        </div>

        <table v-else class="su-table">
          <thead>
            <tr>
              <th>Period</th>
              <th class="su-th-num">Segments used</th>
              <th class="su-th-num" v-if="smsQuota > 0">Overage</th>
              <th class="su-th-num">Billed</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in history" :key="row.period" class="su-tr">
              <td class="su-td-period">
                <span class="su-period-badge" :class="row.period === currentPeriod ? 'su-period-badge--current' : ''">
                  {{ formatPeriod(row.period) }}
                </span>
              </td>
              <td class="su-td-num">
                <span class="su-num-val">{{ row.segments_used.toLocaleString() }}</span>
              </td>
              <td class="su-td-num" v-if="smsQuota > 0">
                <span v-if="row.overage > 0" class="su-overage-badge">
                  +{{ row.overage.toLocaleString() }}
                </span>
                <span v-else class="su-nil">—</span>
              </td>
              <td class="su-td-num">
                <span v-if="row.overage_cents > 0" class="su-cost">
                  ${{ (row.overage_cents / 100).toFixed(2) }}
                </span>
                <span v-else class="su-nil">—</span>
              </td>
              <td class="su-td-status">
                <span v-if="row.period === currentPeriod" class="badge badge-blue">In progress</span>
                <span v-else-if="row.billed_at" class="badge badge-green">
                  Billed {{ formatDate(row.billed_at) }}
                </span>
                <span v-else-if="row.overage > 0" class="badge badge-amber">Pending billing</span>
                <span v-else class="badge badge-gray">No charge</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  currentPeriod: string;
  smsQuota: number;
  smsUsed: number;
  overage: number;
  planSlug: string;
  history: Array<{
    period: string;
    segments_used: number;
    billed_at: string | null;
    overage: number;
    overage_cents: number;
  }>;
}>();

const remaining = computed(() => Math.max(0, props.smsQuota - props.smsUsed));

const usedPct = computed(() => {
  if (props.smsQuota === 0) return 100;
  return Math.min(100, Math.round((props.smsUsed / props.smsQuota) * 100));
});

const gaugeClass = computed(() => {
  if (usedPct.value >= 100 || props.overage > 0) return 'su-gauge--red';
  if (usedPct.value >= 70) return 'su-gauge--amber';
  return 'su-gauge--green';
});

const currentPeriodLabel = computed(() => formatPeriod(props.currentPeriod));

const planLabel = computed(() => {
  const labels: Record<string, string> = {
    free: 'Free',
    starter: 'Starter',
    pro: 'Pro',
    business: 'Business',
  };
  return labels[props.planSlug] ?? props.planSlug;
});

function formatPeriod(period: string): string {
  const [year, month] = period.split('-');
  const d = new Date(parseInt(year), parseInt(month) - 1, 1);
  return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}
</script>

<style scoped>
.su-page { max-width: 900px; }

.su-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.75rem;
}
.su-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #2a2522;
  letter-spacing: -0.02em;
}
.su-subtitle {
  font-size: 0.875rem;
  color: #6b6560;
  margin-top: 0.125rem;
}
.su-btn-icon { width: 1rem; height: 1rem; }

/* ── Stats grid ──────────────────────────────────────────────────────────── */
.su-stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;
}
@media (max-width: 640px) {
  .su-stats-grid { grid-template-columns: 1fr; }
}

.su-stat-card {
  background: white;
  border: 1px solid #e5e0d8;
  border-radius: 0.875rem;
  padding: 1.25rem;
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.su-stat-icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.625rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.su-stat-icon svg { width: 1.125rem; height: 1.125rem; }
.su-stat-icon--blue   { background: rgba(79, 70, 229, 0.1);  color: #4f46e5; }
.su-stat-icon--green  { background: rgba(22, 163, 74, 0.1);  color: #16a34a; }
.su-stat-icon--amber  { background: rgba(217, 119, 6, 0.1);  color: #d97706; }
.su-stat-icon--gray   { background: #f0ede8; color: #6b6560; }

.su-stat-label {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b6560;
  margin: 0 0 0.25rem;
}
.su-stat-value {
  font-size: 1.625rem;
  font-weight: 800;
  color: #2a2522;
  letter-spacing: -0.03em;
  line-height: 1;
  margin: 0 0 0.25rem;
}
.su-value--amber { color: #d97706; }

.su-stat-sub {
  font-size: 0.75rem;
  color: #6b6560;
  margin: 0;
}
.su-sub--amber { color: #d97706; font-weight: 500; }

/* ── Gauge card ──────────────────────────────────────────────────────────── */
.su-gauge-card, .su-ppsg-card {
  background: white;
  border: 1px solid #e5e0d8;
  border-radius: 0.875rem;
  padding: 1.25rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.su-gauge-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.su-gauge-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #2a2522;
}
.su-gauge-pct {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.su-gauge--green { color: #16a34a; }
.su-gauge--amber { color: #d97706; }
.su-gauge--red   { color: #dc2626; }

.su-gauge-track {
  position: relative;
  height: 1.25rem;
  background: #f0ede8;
  border-radius: 99px;
  overflow: hidden;
  margin-bottom: 0.625rem;
}
.su-gauge-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 600ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.su-gauge--green .su-gauge-fill { background: linear-gradient(90deg, #86efac, #22c55e); }
.su-gauge--amber .su-gauge-fill { background: linear-gradient(90deg, #fde68a, #f59e0b); }
.su-gauge--red   .su-gauge-fill { background: linear-gradient(90deg, #fca5a5, #ef4444); }

.su-gauge-label-inside {
  position: absolute;
  top: 50%;
  transform: translateY(-50%) translateX(-100%);
  font-size: 0.6875rem;
  font-weight: 700;
  color: white;
  mix-blend-mode: overlay;
  white-space: nowrap;
  pointer-events: none;
}

.su-gauge-legend {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  font-size: 0.8125rem;
  color: #6b6560;
}
.su-gauge-quota-label {
  margin-left: auto;
  font-size: 0.75rem;
  font-weight: 600;
  color: #a09890;
}
.su-gl-item { display: flex; align-items: center; gap: 0.375rem; }
.su-gl-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  flex-shrink: 0;
}
.su-gl-dot--used      { background: #22c55e; }
.su-gl-dot--remaining { background: #f0ede8; border: 1px solid #d0cbc4; }
.su-gl-dot--overage   { background: #ef4444; }

/* ── Pay per segment ─────────────────────────────────────────────────────── */
.su-ppsg-card {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  background: rgba(79, 70, 229, 0.03);
  border-color: rgba(79, 70, 229, 0.2);
}
.su-ppsg-icon {
  width: 2.25rem;
  height: 2.25rem;
  background: rgba(79, 70, 229, 0.1);
  color: #4f46e5;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.su-ppsg-icon svg { width: 1.125rem; height: 1.125rem; }
.su-ppsg-title { font-weight: 600; font-size: 0.9375rem; color: #2a2522; margin: 0 0 0.25rem; }
.su-ppsg-body  { font-size: 0.875rem; color: #6b6560; margin: 0; }

/* ── History table ───────────────────────────────────────────────────────── */
.su-history-card {
  background: white;
  border: 1px solid #e5e0d8;
  border-radius: 0.875rem;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.su-history-header {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f0ede8;
}
.su-history-title { font-size: 0.9375rem; font-weight: 600; color: #2a2522; }
.su-history-sub { font-size: 0.8125rem; color: #a09890; }

.su-history-empty {
  padding: 2.5rem 1.25rem;
  text-align: center;
  font-size: 0.875rem;
  color: #a09890;
}

.su-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.su-table thead th {
  padding: 0.625rem 1.25rem;
  text-align: left;
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b6560;
  background: #faf9f6;
  border-bottom: 1px solid #e5e0d8;
}
.su-th-num { text-align: right; }

.su-tr {
  border-bottom: 1px solid #f0ede8;
  transition: background 120ms;
}
.su-tr:last-child { border-bottom: none; }
.su-tr:hover { background: #faf9f6; }

.su-table td { padding: 0.875rem 1.25rem; color: #2a2522; vertical-align: middle; }
.su-td-num { text-align: right; }

.su-period-badge {
  font-weight: 500;
  color: #2a2522;
}
.su-period-badge--current {
  background: rgba(79, 70, 229, 0.08);
  color: #4f46e5;
  padding: 0.125rem 0.5rem;
  border-radius: 99px;
  font-size: 0.8125rem;
  font-weight: 600;
}

.su-num-val { font-variant-numeric: tabular-nums; font-weight: 500; }

.su-overage-badge {
  display: inline-block;
  background: rgba(239, 68, 68, 0.08);
  color: #dc2626;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: 99px;
}

.su-cost {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: #2a2522;
}

.su-nil { color: #c8c3ba; }
.su-td-status { white-space: nowrap; }
</style>
