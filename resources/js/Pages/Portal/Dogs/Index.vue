<template>
  <PortalLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-text-body">My Dogs</h1>
        <Link :href="route('portal.dogs.create')">
          <AppButton variant="primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Dog
          </AppButton>
        </Link>
      </div>

      <AppCard v-if="dogs.length === 0" class="p-12 text-center border-dashed">
        <div class="flex justify-center mb-3">
          <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-border-warm">
            <ellipse cx="24" cy="16" rx="7" ry="9" fill="currentColor" opacity="0.4"/>
            <ellipse cx="40" cy="16" rx="7" ry="9" fill="currentColor" opacity="0.4"/>
            <ellipse cx="12" cy="32" rx="6" ry="8" transform="rotate(-15 12 32)" fill="currentColor" opacity="0.4"/>
            <ellipse cx="52" cy="32" rx="6" ry="8" transform="rotate(15 52 32)" fill="currentColor" opacity="0.4"/>
            <ellipse cx="32" cy="46" rx="14" ry="12" fill="currentColor" opacity="0.5"/>
          </svg>
        </div>
        <p class="font-semibold text-text-body">No dogs registered yet</p>
        <p class="text-sm text-text-muted mt-1">Add your first dog to start tracking daycare visits</p>
        <Link :href="route('portal.dogs.create')"><AppButton variant="primary" class="mt-4">Add your first dog</AppButton></Link>
      </AppCard>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link
          v-for="dog in dogs"
          :key="dog.id"
          :href="route('portal.dogs.show', { dog: dog.id })"
          class="block"
        >
          <AppCard class="overflow-hidden hover:-translate-y-0.5 hover:shadow-card-md transition-all duration-200">
            <!-- Gradient header -->
            <div
              class="h-24 relative flex items-end px-4 pb-3"
              :style="dog.color
                ? { background: `linear-gradient(135deg, ${dog.color}ee 0%, ${dog.color}88 100%)` }
                : { background: `linear-gradient(135deg, ${accentColor}ee 0%, ${accentColor}88 100%)` }"
            >
              <div
                class="h-12 w-12 rounded-full flex items-center justify-center text-xl font-bold text-white border-2"
                style="background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3);"
              >
                {{ dog.name[0]?.toUpperCase() }}
              </div>
              <!-- Fur color swatch if set -->
              <span
                v-if="dog.color"
                class="absolute top-3 right-3 h-5 w-5 rounded-full border-2"
                style="border-color: rgba(255,255,255,0.5);"
                :style="{ backgroundColor: dog.color }"
              />
            </div>

            <div class="p-4">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="font-semibold text-text-body truncate">{{ dog.name }}</p>
                  <p v-if="dog.breed" class="text-xs text-text-muted">{{ dog.breed }}</p>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-2xl font-black text-text-body leading-none">{{ dog.credit_balance }}</p>
                  <p class="text-xs text-text-muted">credits</p>
                </div>
              </div>

              <div v-if="dog.unlimited_pass_expires_at" class="mt-2.5 flex items-center gap-1 text-xs text-indigo-600">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Unlimited pass · expires {{ formatDate(dog.unlimited_pass_expires_at) }}
              </div>
              <div v-else-if="dog.credits_expire_at" class="mt-2.5 flex items-center gap-1 text-xs text-amber-600">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ dog.credit_balance }} credits expire {{ formatDate(dog.credits_expire_at) }}
              </div>

              <div class="mt-3 flex items-center gap-1.5 text-xs text-indigo-600 font-medium">
                View profile
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
              </div>
            </div>
          </AppCard>
        </Link>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Dog, PageProps } from '@/types';

defineProps<{ dogs: Dog[] }>();

const page = usePage<PageProps>();
const accentColor = computed(() => page.props.tenant?.primary_color ?? '#4f46e5');

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
