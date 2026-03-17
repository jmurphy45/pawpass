<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-white rounded-xl border border-gray-200 p-10 text-center">
      <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
        <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <h1 class="text-2xl font-bold text-gray-900 mb-2">You're all set!</h1>
      <p class="text-gray-600 mb-6">
        Your free trial is ready. Head to your dashboard to start setting up your daycare.
      </p>

      <div class="bg-gray-50 rounded-lg px-4 py-3 mb-6 text-sm text-gray-700 font-mono break-all">
        {{ adminUrl }}
      </div>

      <a
        :href="adminUrl"
        class="inline-block w-full py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition"
      >
        Go to your dashboard
      </a>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  slug: string
  adminUrl: string
}>()
</script>
