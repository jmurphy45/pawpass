<template>
  <AdminLayout>
    <div class="bc-page">

      <!-- Page header -->
      <div class="bc-header">
        <div class="bc-header-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m-1.394 5.52a23.926 23.926 0 0 1-3.25 2.88" />
          </svg>
        </div>
        <div>
          <h1 class="bc-title">Broadcast</h1>
          <p class="bc-subtitle">Send a one-off message to all your customers at once</p>
        </div>

        <a :href="route('admin.notifications.sms-usage')" class="bc-usage-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="bc-usage-icon">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
          </svg>
          SMS Usage
        </a>
      </div>

      <form @submit.prevent="submit">
        <div class="bc-layout">

          <!-- LEFT: Compose -->
          <div class="bc-compose">

            <!-- Step 1: Channels -->
            <div class="bc-card">
              <div class="bc-card-head">
                <span class="bc-step-num">1</span>
                <span class="bc-card-title">Choose channels</span>
              </div>

              <div class="bc-channels">
                <button
                  v-for="ch in channelDefs"
                  :key="ch.id"
                  type="button"
                  @click="toggleChannel(ch.id)"
                  :class="['bc-ch', `bc-ch--${ch.id}`, { 'bc-ch--on': isSelected(ch.id) }]"
                  :aria-pressed="isSelected(ch.id)"
                >
                  <div class="bc-ch-icon">
                    <component :is="ch.icon" />
                  </div>
                  <div class="bc-ch-body">
                    <span class="bc-ch-name">{{ ch.name }}</span>
                    <span class="bc-ch-desc">{{ ch.desc }}</span>
                  </div>
                  <div class="bc-ch-check" :class="{ 'bc-ch-check--on': isSelected(ch.id) }">
                    <svg v-if="isSelected(ch.id)" viewBox="0 0 16 16" fill="currentColor">
                      <path d="M12.207 4.793a1 1 0 0 1 0 1.414l-5 5a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L6.5 9.086l4.293-4.293a1 1 0 0 1 1.414 0Z"/>
                    </svg>
                  </div>
                </button>
              </div>

              <!-- SMS billing warning -->
              <div v-if="isSelected('sms') && !hasBillingConfigured" class="bc-warn">
                <svg viewBox="0 0 20 20" fill="currentColor" class="bc-warn-icon">
                  <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                </svg>
                <span>SMS requires billing to be configured in <a :href="route('admin.billing.index')" class="bc-warn-link">Billing settings</a> before sending.</span>
              </div>
            </div>

            <!-- SMS Quota (shown when SMS selected) -->
            <Transition name="bc-slide">
              <div v-if="isSelected('sms')" class="bc-card bc-quota-card">
                <div class="bc-card-head">
                  <svg class="bc-quota-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-3 3h1.5" />
                  </svg>
                  <span class="bc-card-title">SMS quota — {{ currentMonthLabel }}</span>
                </div>

                <div class="bc-quota-stats">
                  <div class="bc-qstat">
                    <span class="bc-qstat-num">{{ smsUsed.toLocaleString() }}</span>
                    <span class="bc-qstat-label">used</span>
                  </div>
                  <div class="bc-qstat-div"></div>
                  <div class="bc-qstat" v-if="smsQuota > 0">
                    <span class="bc-qstat-num">{{ smsQuota.toLocaleString() }}</span>
                    <span class="bc-qstat-label">included</span>
                  </div>
                  <div class="bc-qstat" v-if="smsQuota > 0">
                    <span class="bc-qstat-num" :class="remaining <= 0 ? 'text-red-600' : ''">
                      {{ Math.max(0, remaining).toLocaleString() }}
                    </span>
                    <span class="bc-qstat-label">remaining</span>
                  </div>
                  <div class="bc-qstat" v-if="smsQuota === 0">
                    <span class="bc-qstat-num text-amber-600">$0.04</span>
                    <span class="bc-qstat-label">per segment</span>
                  </div>
                </div>

                <!-- Quota bar -->
                <div v-if="smsQuota > 0" class="bc-quota-bar-wrap">
                  <div class="bc-quota-bar-track">
                    <div
                      class="bc-quota-bar-fill bc-quota-bar-used"
                      :style="{ width: usedPct + '%' }"
                    ></div>
                    <div
                      v-if="broadcastSegments > 0"
                      class="bc-quota-bar-fill bc-quota-bar-projected"
                      :style="{ width: projectedPct + '%', left: usedPct + '%' }"
                    ></div>
                  </div>
                  <div class="bc-quota-bar-labels">
                    <span>0</span>
                    <span>{{ (smsQuota / 2).toLocaleString() }}</span>
                    <span>{{ smsQuota.toLocaleString() }}</span>
                  </div>
                </div>

                <div v-if="broadcastSegments > 0" class="bc-quota-note" :class="wouldExceedQuota ? 'bc-quota-note--warn' : 'bc-quota-note--ok'">
                  <template v-if="wouldExceedQuota">
                    This broadcast will use <strong>{{ broadcastSegmentsEstimate }}</strong> SMS segment{{ broadcastSegmentsEstimate !== 1 ? 's' : '' }} (~{{ customersCount }} customers). <strong>{{ overageEstimate.toLocaleString() }}</strong> of those will be billed at $0.04/segment.
                  </template>
                  <template v-else>
                    ~{{ broadcastSegmentsEstimate.toLocaleString() }} segment{{ broadcastSegmentsEstimate !== 1 ? 's' : '' }} for {{ customersCount }} customers — within your included quota.
                  </template>
                </div>
              </div>
            </Transition>

            <!-- Step 2: Message -->
            <div class="bc-card">
              <div class="bc-card-head">
                <span class="bc-step-num">2</span>
                <span class="bc-card-title">Write your message</span>
              </div>

              <!-- Subject (email / in-app only) -->
              <div v-if="!smsOnly" class="bc-field">
                <label class="bc-label">
                  Subject
                  <span v-if="isSelected('sms') && !smsOnly" class="bc-field-note">not sent via SMS</span>
                </label>
                <input
                  v-model="form.subject"
                  class="bc-input"
                  :class="{ 'bc-input--error': form.errors.subject }"
                  placeholder="e.g. Closing early today 🐾"
                  maxlength="255"
                />
                <p v-if="form.errors.subject" class="bc-field-err">{{ form.errors.subject }}</p>
              </div>

              <!-- Body -->
              <div class="bc-field">
                <label class="bc-label">Message</label>
                <div class="bc-textarea-wrap">
                  <textarea
                    v-model="form.body"
                    class="bc-textarea"
                    :class="{ 'bc-input--error': form.errors.body }"
                    rows="6"
                    placeholder="Write your message to your customers…"
                    maxlength="1600"
                    @input="onBodyInput"
                  ></textarea>
                  <div class="bc-counter" :class="counterClass">
                    <template v-if="isSelected('sms')">
                      <span class="bc-counter-segs">{{ broadcastSegments }} seg</span>
                      <span class="bc-counter-sep">·</span>
                      <span>{{ charCount }}/{{ maxCharsForPlan }}</span>
                    </template>
                    <template v-else>
                      {{ charCount }}/500
                    </template>
                  </div>
                </div>
                <p v-if="form.errors.body" class="bc-field-err">{{ form.errors.body }}</p>

                <!-- SMS segment dots -->
                <div v-if="isSelected('sms') && broadcastSegments > 0" class="bc-seg-dots">
                  <div
                    v-for="i in Math.min(broadcastSegments, 10)"
                    :key="'s' + i"
                    class="bc-seg-dot bc-seg-dot--on"
                  ></div>
                  <span v-if="broadcastSegments > 10" class="bc-seg-more">+{{ broadcastSegments - 10 }}</span>
                </div>
              </div>

              <p v-if="form.errors.channels" class="bc-field-err">{{ form.errors.channels }}</p>
            </div>

          </div>

          <!-- RIGHT: Preview + Audience + Send -->
          <div class="bc-sidebar">

            <!-- Live preview -->
            <div v-if="form.channels.length > 0 && (form.subject || form.body)" class="bc-card bc-preview-card">
              <div class="bc-card-head bc-preview-head">
                <span class="bc-card-title">Preview</span>
                <div class="bc-preview-tabs">
                  <button
                    v-for="tab in previewTabs"
                    :key="tab.id"
                    type="button"
                    @click="activePreviewTab = tab.id"
                    :class="['bc-preview-tab', { 'bc-preview-tab--on': activePreviewTab === tab.id }]"
                  >
                    {{ tab.label }}
                  </button>
                </div>
              </div>

              <!-- Email preview -->
              <div v-if="activePreviewTab === 'email'" class="bc-preview-body bc-preview-email">
                <div class="bc-pe-from">From: <strong>{{ tenantName }}</strong> via PawPass</div>
                <div class="bc-pe-subject">{{ form.subject || '(no subject)' }}</div>
                <div class="bc-pe-body">{{ form.body || '(empty message)' }}</div>
              </div>

              <!-- SMS preview -->
              <div v-if="activePreviewTab === 'sms'" class="bc-preview-body bc-preview-sms">
                <div class="bc-ps-bubble">
                  <p>{{ form.body || '(empty message)' }}</p>
                  <span class="bc-ps-meta">{{ broadcastSegments }} segment{{ broadcastSegments !== 1 ? 's' : '' }}</span>
                </div>
              </div>

              <!-- In-app preview -->
              <div v-if="activePreviewTab === 'in_app'" class="bc-preview-body bc-preview-inapp">
                <div class="bc-pia-notification">
                  <div class="bc-pia-dot"></div>
                  <div>
                    <p class="bc-pia-title">{{ form.subject || 'Announcement' }}</p>
                    <p class="bc-pia-body">{{ form.body || '(empty message)' }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Audience -->
            <div class="bc-card bc-audience-card">
              <div class="bc-audience-num">{{ customersCount.toLocaleString() }}</div>
              <div class="bc-audience-label">customers will receive this</div>
              <div class="bc-audience-channels" v-if="form.channels.length > 0">
                <span v-for="ch in selectedChannelLabels" :key="ch" class="bc-audience-badge">{{ ch }}</span>
              </div>
            </div>

            <!-- Send button -->
            <button
              type="submit"
              :disabled="!canSubmit || form.processing"
              class="bc-send-btn"
              :class="{ 'bc-send-btn--loading': form.processing }"
            >
              <span v-if="!form.processing" class="bc-send-content">
                <svg class="bc-send-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
                Send Broadcast
              </span>
              <span v-else class="bc-send-content">
                <span class="bc-send-spinner"></span>
                Sending…
              </span>
            </button>

            <p v-if="!canSubmit && form.channels.length === 0" class="bc-hint">Select at least one channel to continue</p>
            <p v-else-if="!canSubmit && !form.body" class="bc-hint">Write a message to continue</p>
          </div>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref, h } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  customersCount: number;
  smsQuota: number;
  smsUsed: number;
  hasBillingConfigured: boolean;
  planSlug: string;
}>();

const page = usePage<PageProps>();
const tenantName = computed(() => page.props.tenant?.name ?? 'PawPass');

const form = useForm({
  subject: '',
  body: '',
  channels: [] as string[],
});

// ── Channel definitions ──────────────────────────────────────────────────────

const EmailIcon = () => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.5' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75' }),
]);
const SmsIcon = () => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.5' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-3 3h1.5' }),
]);
const BellIcon = () => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.5' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0' }),
]);

const channelDefs = [
  {
    id: 'email',
    name: 'Email',
    desc: 'Delivered to customer inboxes',
    icon: EmailIcon,
  },
  {
    id: 'sms',
    name: 'Text Message',
    desc: props.smsQuota > 0 ? `${props.smsQuota} segments included/month` : 'Billed at $0.04/segment',
    icon: SmsIcon,
  },
  {
    id: 'in_app',
    name: 'In-App',
    desc: 'Notification in the customer portal',
    icon: BellIcon,
  },
];

function toggleChannel(id: string) {
  const idx = form.channels.indexOf(id);
  if (idx === -1) {
    form.channels.push(id);
  } else {
    form.channels.splice(idx, 1);
  }
}

function isSelected(id: string) {
  return form.channels.includes(id);
}

// ── Computed ─────────────────────────────────────────────────────────────────

const charCount = computed(() => form.body.length);
const maxCharsForPlan = computed(() => 1600); // 10 SMS segments max
const broadcastSegments = computed(() => Math.max(1, Math.ceil(form.body.length / 160)));
const broadcastSegmentsEstimate = computed(() => broadcastSegments.value * props.customersCount);

const smsOnly = computed(() =>
  form.channels.length > 0 &&
  form.channels.every(c => c === 'sms')
);

const remaining = computed(() => props.smsQuota - props.smsUsed);

const usedPct = computed(() => {
  if (props.smsQuota === 0) return 0;
  return Math.min(100, (props.smsUsed / props.smsQuota) * 100);
});

const projectedPct = computed(() => {
  if (props.smsQuota === 0) return 0;
  const projectedTotal = props.smsUsed + broadcastSegmentsEstimate.value;
  const projected = Math.min(100, (projectedTotal / props.smsQuota) * 100);
  return Math.max(0, projected - usedPct.value);
});

const wouldExceedQuota = computed(() =>
  props.smsQuota > 0 &&
  props.smsUsed + broadcastSegmentsEstimate.value > props.smsQuota
);

const overageEstimate = computed(() =>
  Math.max(0, props.smsUsed + broadcastSegmentsEstimate.value - props.smsQuota)
);

const counterClass = computed(() => {
  if (charCount.value > 1440) return 'bc-counter--danger';
  if (charCount.value > 960) return 'bc-counter--warn';
  return '';
});

const currentMonthLabel = computed(() => {
  return new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const canSubmit = computed(() => {
  const hasChannels = form.channels.length > 0;
  const hasBody = form.body.trim().length > 0;
  const hasSubject = smsOnly.value || form.subject.trim().length > 0;
  const billingOk = !isSelected('sms') || props.hasBillingConfigured;
  return hasChannels && hasBody && hasSubject && billingOk;
});

// ── Preview ───────────────────────────────────────────────────────────────────

const activePreviewTab = ref('email');

const previewTabs = computed(() => {
  const tabs: { id: string; label: string }[] = [];
  if (isSelected('email')) tabs.push({ id: 'email', label: 'Email' });
  if (isSelected('sms')) tabs.push({ id: 'sms', label: 'SMS' });
  if (isSelected('in_app')) tabs.push({ id: 'in_app', label: 'In-App' });
  // auto-select first available tab
  if (tabs.length > 0 && !tabs.find(t => t.id === activePreviewTab.value)) {
    activePreviewTab.value = tabs[0].id;
  }
  return tabs;
});

const selectedChannelLabels = computed(() => {
  return form.channels.map(c => {
    const def = channelDefs.find(d => d.id === c);
    return def?.name ?? c;
  });
});

function onBodyInput() {
  // noop — just to trigger reactivity
}

function submit() {
  form.post(route('admin.notifications.broadcast.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
    },
  });
}
</script>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.bc-page {
  max-width: 1100px;
}

.bc-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 2rem;
}

.bc-header-icon {
  width: 2.75rem;
  height: 2.75rem;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.bc-header-icon svg {
  width: 1.375rem;
  height: 1.375rem;
}

.bc-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #2a2522;
  letter-spacing: -0.02em;
  line-height: 1.2;
}
.bc-subtitle {
  font-size: 0.875rem;
  color: #6b6560;
  margin-top: 0.125rem;
}

.bc-usage-link {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #6b6560;
  text-decoration: none;
  padding: 0.375rem 0.75rem;
  border: 1px solid #e5e0d8;
  border-radius: 0.5rem;
  transition: all 150ms;
}
.bc-usage-link:hover {
  background: #faf9f6;
  color: #2a2522;
}
.bc-usage-icon {
  width: 1rem;
  height: 1rem;
}

/* ── Grid ────────────────────────────────────────────────────────────────── */
.bc-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 1.5rem;
  align-items: start;
}
@media (max-width: 900px) {
  .bc-layout { grid-template-columns: 1fr; }
}

.bc-compose { display: flex; flex-direction: column; gap: 1.25rem; }
.bc-sidebar { display: flex; flex-direction: column; gap: 1rem; position: sticky; top: 1.5rem; }

/* ── Card ────────────────────────────────────────────────────────────────── */
.bc-card {
  background: #ffffff;
  border: 1px solid #e5e0d8;
  border-radius: 0.875rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
}
.bc-card-head {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f0ede8;
}
.bc-card-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #2a2522;
}

.bc-step-num {
  width: 1.5rem;
  height: 1.5rem;
  background: #2a2522;
  color: white;
  border-radius: 50%;
  font-size: 0.75rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ── Channels ────────────────────────────────────────────────────────────── */
.bc-channels {
  padding: 0.875rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.bc-ch {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.875rem 1rem;
  border: 1.5px solid #e5e0d8;
  border-radius: 0.625rem;
  background: #faf9f6;
  cursor: pointer;
  text-align: left;
  transition: all 180ms ease;
  width: 100%;
}
.bc-ch:hover {
  border-color: #c8c3ba;
  background: #f5f2ed;
}

.bc-ch--on.bc-ch--email { border-color: #4f46e5; background: rgba(79, 70, 229, 0.04); }
.bc-ch--on.bc-ch--sms   { border-color: #d97706; background: rgba(217, 119, 6, 0.04); }
.bc-ch--on.bc-ch--in_app { border-color: #16a34a; background: rgba(22, 163, 74, 0.04); }

.bc-ch-icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: #ede9e3;
  color: #6b6560;
  transition: all 180ms ease;
}
.bc-ch-icon svg { width: 1.125rem; height: 1.125rem; }

.bc-ch--on.bc-ch--email   .bc-ch-icon { background: rgba(79, 70, 229, 0.12); color: #4f46e5; }
.bc-ch--on.bc-ch--sms     .bc-ch-icon { background: rgba(217, 119, 6, 0.12); color: #d97706; }
.bc-ch--on.bc-ch--in_app  .bc-ch-icon { background: rgba(22, 163, 74, 0.12); color: #16a34a; }

.bc-ch-body {
  flex: 1;
  min-width: 0;
}
.bc-ch-name {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #2a2522;
}
.bc-ch-desc {
  display: block;
  font-size: 0.75rem;
  color: #6b6560;
  margin-top: 0.125rem;
}

.bc-ch-check {
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 50%;
  border: 1.5px solid #d0cbc4;
  flex-shrink: 0;
  transition: all 180ms ease;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}
.bc-ch-check svg { width: 0.75rem; height: 0.75rem; }
.bc-ch--on.bc-ch--email   .bc-ch-check { background: #4f46e5; border-color: #4f46e5; }
.bc-ch--on.bc-ch--sms     .bc-ch-check { background: #d97706; border-color: #d97706; }
.bc-ch--on.bc-ch--in_app  .bc-ch-check { background: #16a34a; border-color: #16a34a; }

/* ── Warning ─────────────────────────────────────────────────────────────── */
.bc-warn {
  margin: 0 0.875rem 0.875rem;
  padding: 0.75rem;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 0.5rem;
  display: flex;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: #92400e;
}
.bc-warn-icon { width: 1rem; height: 1rem; flex-shrink: 0; margin-top: 0.125rem; color: #d97706; }
.bc-warn-link { font-weight: 600; text-decoration: underline; color: inherit; }

/* ── SMS Quota ───────────────────────────────────────────────────────────── */
.bc-quota-icon { width: 1rem; height: 1rem; color: #d97706; }

.bc-quota-stats {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1rem 1.25rem;
}
.bc-qstat { text-align: center; }
.bc-qstat-num {
  display: block;
  font-size: 1.5rem;
  font-weight: 700;
  color: #2a2522;
  letter-spacing: -0.02em;
  line-height: 1;
}
.bc-qstat-label {
  display: block;
  font-size: 0.6875rem;
  color: #6b6560;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-weight: 600;
  margin-top: 0.25rem;
}
.bc-qstat-div {
  width: 1px;
  height: 2rem;
  background: #e5e0d8;
}

.bc-quota-bar-wrap {
  padding: 0 1.25rem 0.75rem;
}
.bc-quota-bar-track {
  position: relative;
  height: 0.5rem;
  background: #f0ede8;
  border-radius: 99px;
  overflow: visible;
}
.bc-quota-bar-fill {
  position: absolute;
  top: 0;
  height: 100%;
  border-radius: 99px;
  transition: width 400ms ease;
}
.bc-quota-bar-used {
  background: linear-gradient(90deg, #22c55e 0%, #f59e0b 70%, #ef4444 100%);
  background-size: 600px 100%;
  background-position: 0 0;
  z-index: 1;
}
.bc-quota-bar-projected {
  background: rgba(217, 119, 6, 0.4);
  z-index: 2;
}
.bc-quota-bar-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.6875rem;
  color: #6b6560;
  margin-top: 0.375rem;
}

.bc-quota-note {
  margin: 0 1.25rem 1rem;
  padding: 0.625rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
}
.bc-quota-note--ok {
  background: rgba(22, 163, 74, 0.06);
  color: #15803d;
  border: 1px solid rgba(22, 163, 74, 0.2);
}
.bc-quota-note--warn {
  background: rgba(239, 68, 68, 0.06);
  color: #b91c1c;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

/* ── Fields ──────────────────────────────────────────────────────────────── */
.bc-field {
  padding: 1rem 1.25rem;
  border-top: 1px solid #f0ede8;
}
.bc-field:first-of-type { border-top: none; }

.bc-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b6560;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 0.5rem;
}
.bc-field-note {
  font-size: 0.6875rem;
  font-weight: 400;
  color: #a09890;
  text-transform: none;
  letter-spacing: 0;
  background: #f0ede8;
  padding: 0.125rem 0.5rem;
  border-radius: 99px;
}
.bc-input {
  width: 100%;
  border: 1.5px solid #e5e0d8;
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.9375rem;
  color: #2a2522;
  background: #faf9f6;
  outline: none;
  transition: border-color 150ms, box-shadow 150ms;
}
.bc-input:focus {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
  background: #fff;
}
.bc-input--error { border-color: #ef4444; }

.bc-textarea-wrap { position: relative; }
.bc-textarea {
  width: 100%;
  border: 1.5px solid #e5e0d8;
  border-radius: 0.5rem;
  padding: 0.75rem 0.875rem;
  padding-bottom: 2.25rem;
  font-size: 0.9375rem;
  color: #2a2522;
  background: #faf9f6;
  outline: none;
  resize: vertical;
  transition: border-color 150ms, box-shadow 150ms;
  font-family: inherit;
  line-height: 1.6;
  min-height: 140px;
}
.bc-textarea:focus {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
  background: #fff;
}

.bc-counter {
  position: absolute;
  bottom: 0.625rem;
  right: 0.875rem;
  font-size: 0.75rem;
  color: #a09890;
  pointer-events: none;
}
.bc-counter-segs { font-weight: 600; }
.bc-counter-sep { margin: 0 0.25rem; }
.bc-counter--warn .bc-counter { color: #d97706; }
.bc-counter--danger .bc-counter { color: #ef4444; }

.bc-field-err {
  font-size: 0.8125rem;
  color: #ef4444;
  margin-top: 0.375rem;
}

/* ── Segment dots ────────────────────────────────────────────────────────── */
.bc-seg-dots {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-top: 0.625rem;
}
.bc-seg-dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 2px;
  background: #e5e0d8;
  transition: background 180ms;
}
.bc-seg-dot--on { background: #d97706; }
.bc-seg-more {
  font-size: 0.75rem;
  color: #d97706;
  font-weight: 600;
}

/* ── Preview ─────────────────────────────────────────────────────────────── */
.bc-preview-head {
  flex-wrap: wrap;
  gap: 0.5rem;
}
.bc-preview-tabs {
  display: flex;
  gap: 0.25rem;
  margin-left: auto;
}
.bc-preview-tab {
  padding: 0.25rem 0.625rem;
  font-size: 0.75rem;
  font-weight: 500;
  border-radius: 0.375rem;
  border: 1px solid #e5e0d8;
  background: transparent;
  color: #6b6560;
  cursor: pointer;
  transition: all 150ms;
}
.bc-preview-tab--on {
  background: #2a2522;
  color: white;
  border-color: #2a2522;
}

.bc-preview-body { padding: 1rem 1.25rem; }

.bc-preview-email {
  font-size: 0.875rem;
}
.bc-pe-from { color: #6b6560; font-size: 0.8125rem; margin-bottom: 0.5rem; }
.bc-pe-subject {
  font-weight: 700;
  font-size: 0.9375rem;
  color: #2a2522;
  margin-bottom: 0.625rem;
  padding-bottom: 0.625rem;
  border-bottom: 1px solid #f0ede8;
}
.bc-pe-body { color: #3a3330; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }

.bc-preview-sms { display: flex; }
.bc-ps-bubble {
  background: #4f46e5;
  color: white;
  padding: 0.75rem 1rem;
  border-radius: 1rem 1rem 0.25rem 1rem;
  max-width: 240px;
  font-size: 0.875rem;
  line-height: 1.5;
}
.bc-ps-bubble p { margin: 0; white-space: pre-wrap; word-break: break-word; }
.bc-ps-meta { display: block; font-size: 0.6875rem; opacity: 0.7; margin-top: 0.375rem; }

.bc-preview-inapp {
  background: #faf9f6;
  border-radius: 0 0 0.875rem 0.875rem;
}
.bc-pia-notification {
  display: flex;
  gap: 0.75rem;
  background: white;
  padding: 0.875rem;
  border-radius: 0.625rem;
  border: 1px solid #e5e0d8;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.bc-pia-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: #4f46e5;
  flex-shrink: 0;
  margin-top: 0.375rem;
}
.bc-pia-title { font-size: 0.875rem; font-weight: 600; color: #2a2522; margin: 0 0 0.25rem; }
.bc-pia-body { font-size: 0.8125rem; color: #6b6560; margin: 0; white-space: pre-wrap; word-break: break-word; }

/* ── Audience card ───────────────────────────────────────────────────────── */
.bc-audience-card {
  padding: 1.25rem;
  text-align: center;
}
.bc-audience-num {
  font-size: 3rem;
  font-weight: 800;
  color: #2a2522;
  letter-spacing: -0.04em;
  line-height: 1;
}
.bc-audience-label {
  font-size: 0.875rem;
  color: #6b6560;
  margin-top: 0.375rem;
}
.bc-audience-channels {
  display: flex;
  gap: 0.375rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 0.75rem;
}
.bc-audience-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  background: #f0ede8;
  color: #6b6560;
  padding: 0.25rem 0.625rem;
  border-radius: 99px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* ── Send button ─────────────────────────────────────────────────────────── */
.bc-send-btn {
  width: 100%;
  padding: 0.875rem 1.25rem;
  background: #2a2522;
  color: white;
  font-weight: 700;
  font-size: 0.9375rem;
  border: none;
  border-radius: 0.75rem;
  cursor: pointer;
  transition: background 150ms, transform 100ms, box-shadow 150ms;
  box-shadow: 0 4px 14px rgba(42, 37, 34, 0.25);
}
.bc-send-btn:hover:not(:disabled) {
  background: #1a1514;
  box-shadow: 0 6px 20px rgba(42, 37, 34, 0.35);
  transform: translateY(-1px);
}
.bc-send-btn:active:not(:disabled) { transform: translateY(0); }
.bc-send-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}
.bc-send-btn--loading { opacity: 0.7; }

.bc-send-content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.625rem;
}
.bc-send-icon { width: 1.125rem; height: 1.125rem; }

.bc-send-spinner {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 600ms linear infinite;
}

.bc-hint {
  font-size: 0.75rem;
  color: #a09890;
  text-align: center;
}

/* ── Transition ──────────────────────────────────────────────────────────── */
.bc-slide-enter-active,
.bc-slide-leave-active {
  transition: all 220ms ease;
}
.bc-slide-enter-from,
.bc-slide-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
