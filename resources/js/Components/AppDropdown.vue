<script setup lang="ts">
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

defineProps<{
  items?: { label: string; href?: string; onClick?: () => void; danger?: boolean }[];
}>();
</script>

<template>
  <Menu as="div" class="relative inline-block text-left">
    <MenuButton as="template">
      <slot name="trigger">
        <button class="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text-body transition-colors px-2 py-1 rounded-md hover:bg-surface-subtle">
          <slot name="trigger-label">Options</slot>
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
          </svg>
        </button>
      </slot>
    </MenuButton>

    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <MenuItems class="absolute right-0 z-20 mt-1 w-48 origin-top-right bg-white rounded-lg shadow-card-md border border-border-warm focus:outline-none py-1">
        <slot>
          <MenuItem
            v-for="item in items"
            :key="item.label"
            v-slot="{ active }"
          >
            <component
              :is="item.href ? 'a' : 'button'"
              :href="item.href"
              @click="item.onClick"
              :class="[
                'w-full text-left flex items-center px-4 py-2 text-sm transition-colors',
                active ? 'bg-surface-subtle' : '',
                item.danger ? 'text-red-600' : 'text-text-body',
              ]"
            >
              {{ item.label }}
            </component>
          </MenuItem>
        </slot>
      </MenuItems>
    </transition>
  </Menu>
</template>
