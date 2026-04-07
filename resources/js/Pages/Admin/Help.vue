<template>
  <AdminLayout>
    <div class="space-y-6 max-w-3xl">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-text-body">Help & FAQ</h1>
        <p class="mt-1 text-sm text-text-muted">Answers to common questions about running your daycare on PawPass.</p>
      </div>

      <!-- Search -->
      <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input
          v-model="search"
          type="search"
          placeholder="Search questions..."
          class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-border-warm bg-white text-sm text-text-body placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      <!-- No results -->
      <p v-if="search && visibleSections.length === 0" class="text-sm text-text-muted py-4">
        No results for "{{ search }}". Try a different search term.
      </p>

      <!-- Sections -->
      <div v-for="section in visibleSections" :key="section.title" class="bg-white rounded-xl border border-border-warm overflow-hidden">
        <!-- Section header -->
        <button
          class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-surface transition-colors"
          @click="toggleSection(section.title)"
        >
          <div class="flex items-center gap-3">
            <span class="text-xl">{{ section.icon }}</span>
            <span class="font-semibold text-text-body">{{ section.title }}</span>
            <span class="text-xs text-text-muted bg-surface px-2 py-0.5 rounded-full">{{ section.visibleItems.length }}</span>
          </div>
          <svg
            class="h-4 w-4 text-text-muted transition-transform"
            :class="openSections.has(section.title) ? 'rotate-180' : ''"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </button>

        <!-- FAQ items -->
        <div v-if="openSections.has(section.title)" class="divide-y divide-border-warm border-t border-border-warm">
          <div
            v-for="item in section.visibleItems"
            :key="item.q"
            class="px-5 py-4"
          >
            <button
              class="w-full flex items-start justify-between gap-4 text-left"
              @click="toggleItem(item.q)"
            >
              <span class="text-sm font-medium text-text-body" v-html="highlight(item.q)" />
              <svg
                class="h-4 w-4 text-text-muted shrink-0 mt-0.5 transition-transform"
                :class="openItems.has(item.q) ? 'rotate-45' : ''"
                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
            </button>
            <div v-if="openItems.has(item.q)" class="mt-3 text-sm text-text-muted leading-relaxed" v-html="highlight(item.a)" />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const search = ref('');
const openSections = ref(new Set<string>());
const openItems = ref(new Set<string>());

function toggleSection(title: string) {
  if (openSections.value.has(title)) {
    openSections.value.delete(title);
  } else {
    openSections.value.add(title);
  }
}

function toggleItem(q: string) {
  if (openItems.value.has(q)) {
    openItems.value.delete(q);
  } else {
    openItems.value.add(q);
  }
}

function highlight(text: string): string {
  if (!search.value.trim()) return text;
  const escaped = search.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-100 text-yellow-900 rounded px-0.5">$1</mark>');
}

interface FaqItem { q: string; a: string }
interface Section { title: string; icon: string; items: FaqItem[] }

const sections: Section[] = [
  {
    title: 'Credits & Check-In',
    icon: '🐾',
    items: [
      {
        q: 'How do credits work?',
        a: 'Credits are the attendance currency in PawPass. Each dog has a credit balance. When a dog is checked in for the day, one credit is automatically deducted from their balance. Customers purchase credits in advance by buying a package.',
      },
      {
        q: 'What happens when a dog reaches zero credits?',
        a: 'By default, check-in is blocked when a dog has no credits. You can change this behavior in Settings → Business: enable "Auto-charge on zero credits" to automatically charge the customer\'s card on file for the lowest-priced package when their balance hits zero.',
      },
      {
        q: 'How do I check a dog in or out?',
        a: 'Go to Roster. Dogs available for check-in appear there. Click the check-in button next to the dog\'s name. When they leave, use the check-out button. The roster reflects today\'s attendance in real time.',
      },
      {
        q: 'Can I add paid services (add-ons) during a visit?',
        a: 'Yes. On the Roster page, open the attendance record for a checked-in dog and add any services you\'ve configured (e.g., bath, nail trim). Add-ons create a charge on the customer\'s card and are listed on the order receipt.',
      },
      {
        q: 'How do I give a customer extra credits as a goodwill gesture?',
        a: 'Go to the dog\'s profile → Credits → Goodwill. Enter the number of credits and a note explaining the reason (required). The credits are added immediately and the note is recorded in the ledger for your records.',
      },
      {
        q: 'How do I correct an accidental credit entry?',
        a: 'Go to the dog\'s profile → Credits → Correction. You can add or remove credits and must include a note. All corrections are permanently logged in the credit ledger — they cannot be deleted.',
      },
      {
        q: 'Can I move credits from one dog to another?',
        a: 'Yes, but only between dogs that belong to the same customer. Go to the dog\'s profile → Credits → Transfer, select the destination dog, and enter the amount. Both dogs must be under the same customer account.',
      },
      {
        q: 'Why did a customer get a low-credit alert?',
        a: 'PawPass automatically sends a low-credit notification when a dog\'s balance drops to or below the threshold set in Settings → Business (default: 2 credits). Alerts are grouped per customer and sent at most once every 24 hours per dog to avoid spam.',
      },
    ],
  },
  {
    title: 'Packages & Billing',
    icon: '💳',
    items: [
      {
        q: 'What package types are available?',
        a: 'There are three types: <strong>One-time</strong> — a fixed number of credits sold once (e.g., "10-day pack"). <strong>Subscription</strong> — a recurring monthly or annual charge that grants credits each period. <strong>Unlimited</strong> — a flat monthly fee with no per-visit deductions.',
      },
      {
        q: 'Do I need to connect Stripe before selling packages?',
        a: 'Yes. Go to Billing → Stripe Connect and complete the onboarding flow. This connects your bank account so customer payments are routed to you. You cannot create packages or accept payments until Stripe Connect is set up.',
      },
      {
        q: 'How does pricing work? What fees are charged?',
        a: 'You set the price customers pay. PawPass deducts a platform fee (typically 5%) and Stripe\'s card processing fee (2.9% + $0.30) from each transaction. The remainder is paid out to your connected bank account.',
      },
      {
        q: 'What is auto-replenish?',
        a: 'Auto-replenish is an option on one-time packages. When enabled, PawPass automatically charges the customer for the same package whenever their credit balance reaches zero. Customers must have a card on file and must have opted in.',
      },
      {
        q: 'How do I refund an order?',
        a: 'Go to Payments, find the order, and click Refund. PawPass will process the refund through Stripe and remove all remaining credits from the dog\'s balance — regardless of how many were originally purchased or how many have been used. Refunds remove all credits, not a proportional amount.',
      },
      {
        q: 'Can I archive a package I no longer want to sell?',
        a: 'Yes. On the Packages page, use the Archive option on any package. Archived packages are hidden from the customer portal but all historical orders that reference them are preserved. Packages are never permanently deleted.',
      },
      {
        q: 'How do subscriptions renew?',
        a: 'Stripe handles subscription renewals automatically on the billing date. When a payment succeeds, PawPass receives a webhook and credits are issued to the dog\'s balance automatically. You can see renewal events in the credit ledger on the dog\'s profile.',
      },
      {
        q: 'Can I set up sales tax collection?',
        a: 'Yes. Go to Tax (Owner only) and enable tax collection. This uses Stripe Tax to automatically calculate and collect the appropriate tax rate based on the customer\'s location. Once enabled, tax is applied to all new package purchases.',
      },
    ],
  },
  {
    title: 'Boarding & Reservations',
    icon: '🏠',
    items: [
      {
        q: 'How do I create a boarding reservation?',
        a: 'Customers can request boarding through their portal, or staff can create one manually. Go to Boarding → Reservations → New Reservation, select the customer and dog, choose check-in and check-out dates, and assign a kennel unit.',
      },
      {
        q: 'What are kennel units?',
        a: 'Kennel units are the physical spaces (kennels, suites, runs) in your facility. Go to Boarding → Kennel Units to add, name, and manage them. Units must be set up before you can assign reservations. The Occupancy view shows which units are booked on any given date.',
      },
      {
        q: 'How do I check if a vaccination requirement is met before boarding?',
        a: 'Go to Owner → Vaccinations to define required vaccines for boarding. When a reservation is created, PawPass checks the dog\'s vaccination records against these requirements. If any are missing or expired, a warning is shown on the reservation.',
      },
      {
        q: 'How do I charge add-on services during a boarding stay?',
        a: 'Open the reservation and use the Add-ons panel to attach services (e.g., grooming, medication administration). Add-ons are charged to the customer\'s card immediately if the reservation is already checked in, or at checkout otherwise.',
      },
      {
        q: 'What is a report card?',
        a: 'A report card is a daily update you can send to the customer during their dog\'s boarding stay — including notes, photos, and a rating. Open the reservation and use the Report Cards section. Customers receive the update via their portal and email/SMS notifications.',
      },
      {
        q: 'How do I complete a boarding checkout?',
        a: 'Open the reservation and click Checkout. This finalizes the stay, processes any outstanding add-on charges, and marks the reservation as completed. Make sure all add-ons are added before checking out — you cannot edit a completed reservation.',
      },
      {
        q: 'How do I cancel a reservation?',
        a: 'Open the reservation and use the Cancel option. Cancellation does not automatically issue a refund — you will need to process any refund separately from the Payments page if applicable.',
      },
      {
        q: 'How do I see what kennels are available on a given date?',
        a: 'Go to Boarding → Occupancy and select a date. The occupancy dashboard shows each kennel unit and which reservations are assigned to it, making it easy to spot open slots.',
      },
    ],
  },
  {
    title: 'Account & Staff Setup',
    icon: '⚙️',
    items: [
      {
        q: 'How do I invite a staff member?',
        a: 'Go to Settings → Staff and click Invite Staff. Enter their email address. They will receive an email with a link to set their password and join your account. Staff can check in dogs, manage customers, and adjust credits but cannot access billing or packages.',
      },
      {
        q: 'What is the difference between Owner and Staff roles?',
        a: '<strong>Business Owner</strong> — full access including packages, services, vaccination requirements, settings, billing, tax, and staff management. <strong>Staff</strong> — operational access: roster, customers, dogs, payments view, and credit adjustments. Staff cannot change prices, billing, or business settings.',
      },
      {
        q: 'How do I deactivate a staff member?',
        a: 'Go to Settings → Staff, find the staff member, and click Deactivate. Deactivated users can no longer log in. Their past activity and attendance records are preserved.',
      },
      {
        q: 'How do I change my business name, timezone, or theme color?',
        a: 'Go to Settings → Business. You can update your business name, timezone (used for scheduling and report timestamps), primary brand color (used throughout the admin and customer portal), and low-credit alert threshold.',
      },
      {
        q: 'How do I upload or change my logo?',
        a: 'Go to Settings → Business and scroll to the Logo section. Upload a PNG or SVG file. Your logo appears in the sidebar and on customer portal pages. Click Delete to remove the current logo and revert to the default PawPass icon.',
      },
      {
        q: 'How do I upgrade or change my plan?',
        a: 'Go to Billing (Owner only). The billing page shows available plans and your current subscription. Click the plan you want and follow the prompts. You can switch between monthly and annual billing, and upgrades take effect immediately.',
      },
      {
        q: 'What features are included on each plan?',
        a: '<strong>Free</strong> — basic roster and attendance. <strong>Starter ($49/mo)</strong> — add customers and dogs, customer portal, email notifications, basic reports, up to 5 staff. <strong>Pro ($99/mo)</strong> — financial reports, custom branding, 500 SMS/month, up to 15 staff. <strong>Business ($199/mo)</strong> — unlimited staff, 1,000 SMS/month, priority support.',
      },
      {
        q: 'How do I manage email and SMS notification settings?',
        a: 'Go to Settings → Notifications. You can enable or disable specific notification types (low credit warnings, subscription renewals, etc.) for your tenants. Note: critical notifications like payment confirmations and empty credit alerts are always sent regardless of these settings.',
      },
    ],
  },
];

const visibleSections = computed(() => {
  const q = search.value.toLowerCase().trim();
  return sections
    .map(section => {
      const visibleItems = q
        ? section.items.filter(item => item.q.toLowerCase().includes(q) || item.a.toLowerCase().includes(q))
        : section.items;
      return { ...section, visibleItems };
    })
    .filter(section => section.visibleItems.length > 0);
});

// Auto-open all sections when searching, close them when search is cleared
const manuallyToggled = ref(false);

import { watch } from 'vue';

watch(search, (val) => {
  if (val.trim()) {
    visibleSections.value.forEach(s => openSections.value.add(s.title));
    visibleSections.value.forEach(s => s.visibleItems.forEach(i => openItems.value.add(i.q)));
  } else if (!manuallyToggled.value) {
    openSections.value.clear();
    openItems.value.clear();
  }
});
</script>
