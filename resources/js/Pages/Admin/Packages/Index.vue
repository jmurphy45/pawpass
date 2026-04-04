<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-text-body">Packages</h1>
        <Link :href="route('admin.packages.create')"><AppButton variant="primary">Add Package</AppButton></Link>
      </div>
      <AppCard class="overflow-hidden">
        <ul>
          <li v-for="pkg in packages" :key="pkg.id" class="flex items-center border-b border-border-warm px-5 py-3 transition-colors hover:bg-surface last:border-b-0 gap-3">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-text-body truncate">{{ pkg.name }}</p>
              <p class="text-xs text-text-muted">${{ Number(pkg.price).toFixed(2) }} · {{ pkg.type === 'unlimited' ? 'Unlimited pass' : `${pkg.credit_count} credits` }} · {{ pkg.dog_limit }} {{ pkg.dog_limit === 1 ? 'dog' : 'dogs' }} · {{ pkg.type === 'subscription' ? 'Monthly subscription' : pkg.is_recurring_enabled ? 'Recurring' : 'One-time' }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <AppBadge v-if="pkg.archived_at" color="gray">Archived</AppBadge>
              <Link v-else :href="route('admin.packages.edit', { package: pkg.id })" class="text-sm text-indigo-600 hover:underline">Edit</Link>
            </div>
          </li>
        </ul>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
  packages: Array<{ id: string; name: string; type: string; price: number; credit_count: number; dog_limit: number; is_active: boolean; archived_at: string | null; is_recurring_enabled: boolean }>;
}>();
</script>
