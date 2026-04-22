<template>
  <div>
    <!-- Mobile slide-over sidebar -->
    <TransitionRoot as="template" :show="sidebarOpen">
      <Dialog class="relative z-50 lg:hidden" @close="sidebarOpen = false">
        <TransitionChild
          as="template"
          enter="transition-opacity ease-linear duration-300"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="transition-opacity ease-linear duration-300"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-gray-900/80" />
        </TransitionChild>

        <div class="fixed inset-0 flex">
          <TransitionChild
            as="template"
            enter="transition ease-in-out duration-300 transform"
            enter-from="-translate-x-full"
            enter-to="translate-x-0"
            leave="transition ease-in-out duration-300 transform"
            leave-from="translate-x-0"
            leave-to="-translate-x-full"
          >
            <DialogPanel class="relative mr-16 flex w-full max-w-xs flex-1">
              <TransitionChild
                as="template"
                enter="ease-in-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in-out duration-300"
                leave-from="opacity-100"
                leave-to="opacity-0"
              >
                <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                  <button type="button" class="-m-2.5 p-2.5" @click="sidebarOpen = false">
                    <span class="sr-only">Close sidebar</span>
                    <XMarkIcon class="size-6 text-white" aria-hidden="true" />
                  </button>
                </div>
              </TransitionChild>

              <!-- Mobile sidebar content -->
              <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-indigo-800 px-6 pb-4 ring-1 ring-white/10">
                <div class="flex h-16 shrink-0 items-center gap-2.5">
                  <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="14" cy="14" r="14" fill="white" fill-opacity="0.2"/>
                    <ellipse cx="10" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
                    <ellipse cx="18" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
                    <ellipse cx="7" cy="15" rx="2.2" ry="3" transform="rotate(-20 7 15)" fill="white" opacity="0.9"/>
                    <ellipse cx="21" cy="15" rx="2.2" ry="3" transform="rotate(20 21 15)" fill="white" opacity="0.9"/>
                    <ellipse cx="14" cy="19" rx="5" ry="4" fill="white"/>
                  </svg>
                  <span class="text-white font-bold text-lg tracking-tight">{{ tenant?.name ?? 'PawPass' }}</span>
                  <span
                    v-if="tenantPlan === 'trialing' || tenantPlan === 'free_tier'"
                    class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-400/10 text-yellow-300"
                  >{{ tenantPlan === 'trialing' ? 'Trial' : 'Free' }}</span>
                </div>
                <nav class="flex flex-1 flex-col">
                  <ul role="list" class="flex flex-1 flex-col gap-y-7">
                    <!-- Main nav -->
                    <li>
                      <ul role="list" class="-mx-2 space-y-1">
                        <li>
                          <Link
                            :href="route('admin.dashboard')"
                            :class="[isActive('admin.dashboard') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']"
                            @click="sidebarOpen = false"
                          >
                            <HomeIcon class="size-6 shrink-0" aria-hidden="true" />
                            Dashboard
                          </Link>
                        </li>
                      </ul>
                    </li>

                    <!-- Operations -->
                    <li>
                      <div class="text-xs/6 font-semibold text-indigo-200">Operations</div>
                      <ul role="list" class="-mx-2 mt-2 space-y-1">
                        <li>
                          <Link :href="route('admin.roster.index')" :class="[isActive('admin.roster.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <ListBulletIcon class="size-6 shrink-0" aria-hidden="true" />
                            Roster
                          </Link>
                        </li>
                        <li v-if="hasBoarding">
                          <Link :href="route('admin.boarding.reservations')" :class="[isActive('admin.boarding.reservations*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <HomeModernIcon class="size-6 shrink-0" aria-hidden="true" />
                            Boarding
                          </Link>
                        </li>
                        <li v-if="hasBoarding">
                          <Link :href="route('admin.boarding.units')" :class="[isActive('admin.boarding.units*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <Squares2X2Icon class="size-6 shrink-0" aria-hidden="true" />
                            Kennel Units
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('admin.customers.index')" :class="[isActive('admin.customers.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <UsersIcon class="size-6 shrink-0" aria-hidden="true" />
                            Customers
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('admin.dogs.index')" :class="[isActive('admin.dogs.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <UserGroupIcon class="size-6 shrink-0" aria-hidden="true" />
                            Dogs
                          </Link>
                        </li>
                      </ul>
                    </li>

                    <!-- Business -->
                    <li>
                      <div class="text-xs/6 font-semibold text-indigo-200">Business</div>
                      <ul role="list" class="-mx-2 mt-2 space-y-1">
                        <li>
                          <Link :href="route('admin.payments.index')" :class="[isActive('admin.payments.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <CreditCardIcon class="size-6 shrink-0" aria-hidden="true" />
                            Payments
                          </Link>
                        </li>
                        <li v-if="hasManagePromotions">
                          <Link :href="route('admin.promotions.index')" :class="[isActive('admin.promotions.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <TagIcon class="size-6 shrink-0" aria-hidden="true" />
                            Promotions
                          </Link>
                        </li>
                        <li v-if="hasReports">
                          <Link :href="route('admin.reports.index')" :class="[isActive('admin.reports.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <ChartBarIcon class="size-6 shrink-0" aria-hidden="true" />
                            Reports
                          </Link>
                        </li>
                      </ul>
                    </li>

                    <!-- Communications -->
                    <li v-if="hasBroadcast">
                      <div class="text-xs/6 font-semibold text-indigo-200">Communications</div>
                      <ul role="list" class="-mx-2 mt-2 space-y-1">
                        <li>
                          <Link :href="route('admin.notifications.broadcast')" :class="[isActive('admin.notifications.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <MegaphoneIcon class="size-6 shrink-0" aria-hidden="true" />
                            Broadcast
                          </Link>
                        </li>
                      </ul>
                    </li>

                    <!-- Owner -->
                    <li v-if="isOwner">
                      <div class="text-xs/6 font-semibold text-indigo-200">Owner</div>
                      <ul role="list" class="-mx-2 mt-2 space-y-1">
                        <li v-if="hasManagePackages">
                          <Link :href="route('admin.packages.index')" :class="[isActive('admin.packages.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <ArchiveBoxIcon class="size-6 shrink-0" aria-hidden="true" />
                            Packages
                          </Link>
                        </li>
                        <li v-if="hasAddonServices">
                          <Link :href="route('admin.services.index')" :class="[isActive('admin.services.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <SparklesIcon class="size-6 shrink-0" aria-hidden="true" />
                            Services
                          </Link>
                        </li>
                        <li v-if="hasVaccinationManagement">
                          <Link :href="route('admin.vaccination-requirements.index')" :class="[isActive('admin.vaccination-requirements.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <ShieldCheckIcon class="size-6 shrink-0" aria-hidden="true" />
                            Vaccinations
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('admin.settings.index')" :class="[isActive('admin.settings.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <Cog6ToothIcon class="size-6 shrink-0" aria-hidden="true" />
                            Settings
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('admin.billing.index')" :class="[isActive('admin.billing.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <CurrencyDollarIcon class="size-6 shrink-0" aria-hidden="true" />
                            Billing
                          </Link>
                        </li>
                        <li>
                          <Link :href="route('admin.tax.index')" :class="[isActive('admin.tax.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']" @click="sidebarOpen = false">
                            <ReceiptPercentIcon class="size-6 shrink-0" aria-hidden="true" />
                            Tax
                          </Link>
                        </li>
                      </ul>
                    </li>

                    <!-- User footer -->
                    <li class="-mx-6 mt-auto">
                      <div class="flex items-center gap-x-4 px-6 py-3 border-t border-white/10">
                        <div
                          class="size-8 rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0 bg-indigo-600"
                        >{{ userInitial }}</div>
                        <div class="min-w-0 flex-1">
                          <p class="text-sm/6 font-semibold text-white truncate">{{ auth.user?.name }}</p>
                          <p class="text-xs text-indigo-200 truncate">{{ auth.user?.role?.replace('_', ' ') }}</p>
                        </div>
                        <form @submit.prevent="logout">
                          <button type="submit" class="text-xs text-indigo-200 hover:text-red-400 transition-colors">
                            Sign out
                          </button>
                        </form>
                      </div>
                    </li>
                  </ul>
                </nav>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Desktop sidebar -->
    <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
      <div class="relative flex grow flex-col gap-y-5 overflow-y-auto bg-indigo-800 px-6 after:pointer-events-none after:absolute after:inset-y-0 after:right-0 after:w-px after:bg-white/10">
        <!-- Logo -->
        <div class="flex h-16 shrink-0 items-center gap-2.5">
          <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="14" cy="14" r="14" fill="white" fill-opacity="0.2"/>
              <ellipse cx="10" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
              <ellipse cx="18" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
              <ellipse cx="7" cy="15" rx="2.2" ry="3" transform="rotate(-20 7 15)" fill="white" opacity="0.9"/>
              <ellipse cx="21" cy="15" rx="2.2" ry="3" transform="rotate(20 21 15)" fill="white" opacity="0.9"/>
              <ellipse cx="14" cy="19" rx="5" ry="4" fill="white"/>
            </svg>
            <span class="text-white font-bold text-lg tracking-tight">{{ tenant?.name ?? 'PawPass' }}</span>
          </Link>
          <span
            v-if="tenantPlan === 'trialing' || tenantPlan === 'free_tier'"
            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-400/10 text-yellow-300"
          >{{ tenantPlan === 'trialing' ? 'Trial' : 'Free' }}</span>
        </div>

        <nav class="flex flex-1 flex-col">
          <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <!-- Dashboard -->
            <li>
              <ul role="list" class="-mx-2 space-y-1">
                <li>
                  <Link
                    :href="route('admin.dashboard')"
                    :class="[isActive('admin.dashboard') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']"
                  >
                    <HomeIcon class="size-6 shrink-0" aria-hidden="true" />
                    Dashboard
                  </Link>
                </li>
              </ul>
            </li>

            <!-- Operations -->
            <li>
              <div class="text-xs/6 font-semibold text-indigo-200">Operations</div>
              <ul role="list" class="-mx-2 mt-2 space-y-1">
                <li>
                  <Link :href="route('admin.roster.index')" :class="[isActive('admin.roster.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <ListBulletIcon class="size-6 shrink-0" aria-hidden="true" />
                    Roster
                  </Link>
                </li>
                <li v-if="hasBoarding">
                  <Link :href="route('admin.boarding.reservations')" :class="[isActive('admin.boarding.reservations*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <HomeModernIcon class="size-6 shrink-0" aria-hidden="true" />
                    Boarding
                  </Link>
                </li>
                <li v-if="hasBoarding">
                  <Link :href="route('admin.boarding.units')" :class="[isActive('admin.boarding.units*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <Squares2X2Icon class="size-6 shrink-0" aria-hidden="true" />
                    Kennel Units
                  </Link>
                </li>
                <li>
                  <Link :href="route('admin.customers.index')" :class="[isActive('admin.customers.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <UsersIcon class="size-6 shrink-0" aria-hidden="true" />
                    Customers
                  </Link>
                </li>
                <li>
                  <Link :href="route('admin.dogs.index')" :class="[isActive('admin.dogs.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <UserGroupIcon class="size-6 shrink-0" aria-hidden="true" />
                    Dogs
                  </Link>
                </li>
              </ul>
            </li>

            <!-- Business -->
            <li>
              <div class="text-xs/6 font-semibold text-indigo-200">Business</div>
              <ul role="list" class="-mx-2 mt-2 space-y-1">
                <li>
                  <Link :href="route('admin.payments.index')" :class="[isActive('admin.payments.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <CreditCardIcon class="size-6 shrink-0" aria-hidden="true" />
                    Payments
                  </Link>
                </li>
                <li>
                  <Link :href="route('admin.promotions.index')" :class="[isActive('admin.promotions.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <TagIcon class="size-6 shrink-0" aria-hidden="true" />
                    Promotions
                  </Link>
                </li>
                <li v-if="hasReports">
                  <Link :href="route('admin.reports.index')" :class="[isActive('admin.reports.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <ChartBarIcon class="size-6 shrink-0" aria-hidden="true" />
                    Reports
                  </Link>
                </li>
              </ul>
            </li>

            <!-- Communications -->
            <li v-if="hasBroadcast">
              <div class="text-xs/6 font-semibold text-indigo-200">Communications</div>
              <ul role="list" class="-mx-2 mt-2 space-y-1">
                <li>
                  <Link :href="route('admin.notifications.broadcast')" :class="[isActive('admin.notifications.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <MegaphoneIcon class="size-6 shrink-0" aria-hidden="true" />
                    Broadcast
                  </Link>
                </li>
              </ul>
            </li>

            <!-- Owner -->
            <li v-if="isOwner">
              <div class="text-xs/6 font-semibold text-indigo-200">Owner</div>
              <ul role="list" class="-mx-2 mt-2 space-y-1">
                <li>
                  <Link :href="route('admin.packages.index')" :class="[isActive('admin.packages.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <ArchiveBoxIcon class="size-6 shrink-0" aria-hidden="true" />
                    Packages
                  </Link>
                </li>
                <li v-if="hasAddonServices">
                  <Link :href="route('admin.services.index')" :class="[isActive('admin.services.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <SparklesIcon class="size-6 shrink-0" aria-hidden="true" />
                    Services
                  </Link>
                </li>
                <li>
                  <Link :href="route('admin.vaccination-requirements.index')" :class="[isActive('admin.vaccination-requirements.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <ShieldCheckIcon class="size-6 shrink-0" aria-hidden="true" />
                    Vaccinations
                  </Link>
                </li>
                <li>
                  <Link :href="route('admin.settings.index')" :class="[isActive('admin.settings.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <Cog6ToothIcon class="size-6 shrink-0" aria-hidden="true" />
                    Settings
                  </Link>
                </li>
                <li>
                  <Link :href="route('admin.billing.index')" :class="[isActive('admin.billing.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <CurrencyDollarIcon class="size-6 shrink-0" aria-hidden="true" />
                    Billing
                  </Link>
                </li>
                <li>
                  <Link :href="route('admin.tax.index')" :class="[isActive('admin.tax.*') ? 'bg-indigo-950/25 text-white' : 'text-indigo-100 hover:bg-indigo-950/25 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                    <ReceiptPercentIcon class="size-6 shrink-0" aria-hidden="true" />
                    Tax
                  </Link>
                </li>
              </ul>
            </li>

            <!-- User footer -->
            <li class="-mx-6 mt-auto">
              <div class="flex items-center gap-x-4 px-6 py-3 border-t border-white/10">
                <div class="size-8 rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0 bg-indigo-600">
                  {{ userInitial }}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm/6 font-semibold text-white truncate">{{ auth.user?.name }}</p>
                  <p class="text-xs text-indigo-200 truncate">{{ auth.user?.role?.replace('_', ' ') }}</p>
                </div>
                <form @submit.prevent="logout">
                  <button type="submit" class="text-xs text-indigo-200 hover:text-red-400 transition-colors">
                    Sign out
                  </button>
                </form>
              </div>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Mobile top bar -->
    <div class="sticky top-0 z-40 flex items-center gap-x-6 bg-indigo-800 px-4 py-4 shadow-sm after:pointer-events-none after:absolute after:inset-x-0 after:bottom-0 after:h-px after:bg-white/10 sm:px-6 lg:hidden">
      <button type="button" class="-m-2.5 p-2.5 text-indigo-200 hover:text-white" @click="sidebarOpen = true">
        <span class="sr-only">Open sidebar</span>
        <Bars3Icon class="size-6" aria-hidden="true" />
      </button>
      <div class="flex-1 text-sm/6 font-semibold text-white">{{ tenant?.name ?? 'PawPass' }}</div>
      <div class="size-8 rounded-full flex items-center justify-center text-white font-semibold text-sm bg-indigo-600">
        {{ userInitial }}
      </div>
    </div>

    <!-- Main content -->
    <main class="py-10 lg:pl-72">
      <div class="px-4 sm:px-6 lg:px-8">
        <!-- Flash messages -->
        <div v-if="flash.success || flash.error" class="mb-6 space-y-2">
          <AppAlert v-if="flash.success" type="success" :message="flash.success" @dismiss="dismissFlash('success')" />
          <AppAlert v-if="flash.error" type="error" :message="flash.error" @dismiss="dismissFlash('error')" />
        </div>

        <slot />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Dialog, DialogPanel, TransitionRoot, TransitionChild } from '@headlessui/vue';
import {
  ArchiveBoxIcon,
  Bars3Icon,
  ChartBarIcon,
  Cog6ToothIcon,
  CreditCardIcon,
  CurrencyDollarIcon,
  HomeIcon,
  HomeModernIcon,
  ListBulletIcon,
  MegaphoneIcon,
  ShieldCheckIcon,
  SparklesIcon,
  Squares2X2Icon,
  ReceiptPercentIcon,
  TagIcon,
  UserGroupIcon,
  UsersIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import AppAlert from '@/Components/AppAlert.vue';
import type { PageProps } from '@/types';
import { useFeatures } from '@/composables/useFeatures';

const page = usePage<PageProps>();
const tenant = computed(() => page.props.tenant);
const auth = computed(() => page.props.auth);
const flash = computed(() => page.props.flash ?? { success: null, error: null });
const userInitial = computed(() => auth.value.user?.name?.[0]?.toUpperCase() ?? '?');

const sidebarOpen = ref(false);

const isOwner = computed(() => auth.value.user?.role === 'business_owner');
const tenantPlan = computed(() => page.props.tenantPlan);
const tenantStatus = computed(() => page.props.tenantStatus);
const { hasFeature } = useFeatures();
const hasReports = computed(() => hasFeature('basic_reporting'));
const hasBoarding = computed(() => hasFeature('boarding'));
const hasAddonServices = computed(() => hasFeature('addon_services'));
const hasBroadcast = computed(() => hasFeature('broadcast_notifications'));
const hasManagePackages = computed(() => hasFeature('manage_packages'));
const hasManagePromotions = computed(() => hasFeature('manage_promotions'));
const hasVaccinationManagement = computed(() => hasFeature('vaccination_management'));

const dismissedFlash = ref<Record<string, boolean>>({});
function dismissFlash(key: string) { dismissedFlash.value[key] = true; }

function isActive(pattern: string): boolean {
  try { return !!(route as any)().current(pattern); } catch { return false; }
}

const logoutForm = useForm({});
function logout() { logoutForm.post(route('admin.logout')); }
</script>
