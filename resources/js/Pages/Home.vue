<template>
  <div class="min-h-screen font-sans" style="background-color: #faf9f6;">
    <!-- ===== NAV ===== -->
    <nav
      class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
      :class="navScrolled
        ? 'bg-white border-b border-gray-200 shadow-sm'
        : 'bg-transparent border-b border-white/10'"
    >
      <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
        <!-- Logo -->
        <a href="/" class="flex items-center gap-2.5">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="14" cy="14" r="14" fill="#4f46e5"/>
            <ellipse cx="10" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
            <ellipse cx="18" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
            <ellipse cx="7" cy="15" rx="2.2" ry="3" transform="rotate(-20 7 15)" fill="white" opacity="0.9"/>
            <ellipse cx="21" cy="15" rx="2.2" ry="3" transform="rotate(20 21 15)" fill="white" opacity="0.9"/>
            <ellipse cx="14" cy="19" rx="5" ry="4" fill="white"/>
          </svg>
          <span
            class="text-lg font-bold tracking-tight transition-colors duration-300"
            :class="navScrolled ? 'text-gray-900' : 'text-white'"
          >PawPass</span>
        </a>

        <!-- Center links -->
        <div class="hidden items-center gap-8 md:flex">
          <a
            href="#features"
            class="text-sm font-medium transition-colors duration-300"
            :class="navScrolled ? 'text-gray-600 hover:text-gray-900' : 'text-white/70 hover:text-white'"
          >Features</a>
          <a
            href="#pricing"
            class="text-sm font-medium transition-colors duration-300"
            :class="navScrolled ? 'text-gray-600 hover:text-gray-900' : 'text-white/70 hover:text-white'"
          >Pricing</a>
        </div>

        <!-- Right: login + CTA -->
        <div class="flex items-center gap-3">
          <div class="relative" ref="loginDropdownRef">
            <button
              class="text-sm font-medium px-3 py-2 rounded-lg transition-all duration-200"
              :class="navScrolled
                ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                : 'text-white/80 hover:text-white hover:bg-white/10'"
              @click="loginOpen = !loginOpen"
            >
              Log in
            </button>
            <div
              v-if="loginOpen"
              class="absolute right-0 top-full mt-2 w-72 rounded-xl border border-gray-200 bg-white p-4 shadow-xl z-50"
            >
              <p class="text-xs text-gray-500 mb-2 font-medium">Enter your business subdomain</p>
              <div class="flex items-center gap-2">
                <input
                  v-model="loginSlug"
                  type="text"
                  placeholder="your-daycare"
                  class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  @keydown.enter="goToLogin"
                />
                <span class="text-xs text-gray-400 whitespace-nowrap">.pawpass.com</span>
              </div>
              <button
                class="mt-3 w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-40 transition-colors"
                :disabled="!loginSlug.trim()"
                @click="goToLogin"
              >
                Go to login
              </button>
            </div>
          </div>

          <a
            href="/register"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition-colors shadow-sm"
          >
            Start free trial
          </a>
        </div>
      </div>
    </nav>

    <!-- ===== HERO ===== -->
    <section style="background-color: #0a0908;" class="relative overflow-hidden px-6 pt-32 pb-24 md:pt-40 md:pb-32">
      <!-- Subtle grain texture overlay -->
      <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><filter id=%22n%22><feTurbulence type=%22fractalNoise%22 baseFrequency=%220.75%22 numOctaves=%224%22 stitchTiles=%22stitch%22/></filter><rect width=%22200%22 height=%22200%22 filter=%22url(%23n)%22 opacity=%221%22/></svg>');"></div>
      <!-- Ambient glow -->
      <div class="absolute top-0 left-1/4 w-[600px] h-[400px] rounded-full opacity-10" style="background: radial-gradient(circle, #4f46e5 0%, transparent 70%); filter: blur(80px);"></div>

      <div class="relative mx-auto max-w-7xl">
        <div class="grid items-center gap-16 md:grid-cols-2">
          <!-- Left: copy -->
          <div class="hero-copy">
            <!-- Social proof badge -->
            <div class="hero-item mb-8 inline-flex items-center gap-2 rounded-full border border-amber-500/30 bg-amber-500/10 px-4 py-1.5">
              <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
              <span class="text-xs font-semibold tracking-wide text-amber-400 uppercase">Join 500+ daycares</span>
            </div>

            <h1 class="hero-item text-5xl font-extrabold leading-[1.05] tracking-tight text-white md:text-6xl lg:text-7xl">
              The all-in-one platform for doggy daycare businesses.
            </h1>

            <p class="hero-item mt-6 text-lg leading-relaxed text-white/60 max-w-lg">
              Manage check-ins, credits, and Stripe payments — all from one dashboard. Start your 21-day free trial.
            </p>

            <div class="hero-item mt-10 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
              <a
                href="/register"
                class="rounded-lg bg-indigo-600 px-8 py-3.5 text-base font-semibold text-white shadow-lg hover:bg-indigo-500 transition-all hover:shadow-indigo-500/25 hover:shadow-xl"
              >
                Start free trial
              </a>
              <a
                href="#pricing"
                class="flex items-center gap-2 rounded-lg border border-white/20 px-8 py-3.5 text-base font-semibold text-white/80 hover:border-white/40 hover:text-white transition-all"
              >
                See pricing
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
              </a>
            </div>
          </div>

          <!-- Right: CSS product mockup -->
          <div class="hero-item relative">
            <div class="relative rounded-2xl overflow-hidden shadow-2xl shadow-black/50" style="background: #111110; border: 1px solid rgba(255,255,255,0.08);">
              <!-- Mockup header bar -->
              <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div class="flex items-center gap-3">
                  <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  <span class="text-sm font-semibold text-white">Today's Roster</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="rounded-md px-2.5 py-1 text-xs font-medium" style="background: rgba(79,70,229,0.2); color: #a5b4fc;">Mon, Mar 16</span>
                  <span class="rounded-md px-2.5 py-1 text-xs font-medium" style="background: rgba(245,158,11,0.15); color: #fcd34d;">12 dogs today</span>
                </div>
              </div>

              <!-- Column headers -->
              <div class="grid grid-cols-3 px-5 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: rgba(255,255,255,0.25); border-bottom: 1px solid rgba(255,255,255,0.04);">
                <span>Dog</span>
                <span class="text-center">Status</span>
                <span class="text-right">Credits</span>
              </div>

              <!-- Mock rows -->
              <div class="divide-y" style="divide-color: rgba(255,255,255,0.04);">
                <div v-for="dog in mockDogs" :key="dog.name" class="grid grid-cols-3 items-center px-5 py-3.5 transition-colors hover:bg-white/[0.02]">
                  <div class="flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold" :style="{ background: dog.avatarBg, color: dog.avatarColor }">
                      {{ dog.initial }}
                    </div>
                    <div>
                      <div class="text-sm font-medium text-white">{{ dog.name }}</div>
                      <div class="text-xs" style="color: rgba(255,255,255,0.35);">{{ dog.breed }}</div>
                    </div>
                  </div>
                  <div class="flex justify-center">
                    <span
                      class="rounded-full px-2.5 py-1 text-xs font-semibold"
                      :style="dog.status === 'Checked In'
                        ? 'background: rgba(34,197,94,0.15); color: #86efac;'
                        : 'background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.4);'"
                    >{{ dog.status }}</span>
                  </div>
                  <div class="text-right">
                    <span class="text-sm font-mono font-medium" :style="dog.credits <= 2 ? 'color: #fca5a5;' : 'color: rgba(255,255,255,0.7);'">
                      {{ dog.credits }} days
                    </span>
                  </div>
                </div>
              </div>

              <!-- Mockup footer -->
              <div class="flex items-center justify-between px-5 py-3" style="border-top: 1px solid rgba(255,255,255,0.06);">
                <span class="text-xs" style="color: rgba(255,255,255,0.3);">5 checked in · 3 pending</span>
                <button class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition-colors" style="background: #4f46e5;">+ Check in</button>
              </div>
            </div>

            <!-- Floating stat cards -->
            <div class="absolute -bottom-4 -left-6 hidden lg:block rounded-xl px-4 py-3 shadow-xl" style="background: #1a1918; border: 1px solid rgba(255,255,255,0.08);">
              <div class="text-xs text-white/40 mb-0.5">Revenue this month</div>
              <div class="text-lg font-bold text-white">$4,820</div>
              <div class="text-xs text-green-400 flex items-center gap-1 mt-0.5">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                +18% vs last month
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sentinel for nav scroll detection -->
      <div ref="heroSentinel" class="absolute bottom-0 left-0 right-0 h-px"></div>
    </section>

    <!-- ===== LOGO BAR ===== -->
    <section style="background-color: #faf9f6;" class="border-b border-gray-200 px-6 py-12">
      <div class="mx-auto max-w-7xl text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-8">Trusted by daycares across the country</p>
        <div class="flex flex-wrap items-center justify-center gap-4">
          <span v-for="biz in trustedBusinesses" :key="biz" class="rounded-full border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-600 shadow-sm">
            {{ biz }}
          </span>
        </div>
      </div>
    </section>

    <!-- ===== HOW IT WORKS ===== -->
    <section class="bg-white px-6 py-24">
      <div class="mx-auto max-w-7xl">
        <div class="text-center mb-16">
          <h2 class="text-4xl font-extrabold tracking-tight text-gray-900 md:text-5xl">Up and running in minutes</h2>
          <p class="mt-4 text-lg text-gray-500 max-w-xl mx-auto">No complicated setup. No dev team needed. Just sign up and go.</p>
        </div>
        <div class="grid gap-8 md:grid-cols-3">
          <div v-for="step in howItWorks" :key="step.number" class="relative flex flex-col">
            <!-- Connector line -->
            <div v-if="step.number < 3" class="absolute top-7 left-[calc(50%+2rem)] right-0 hidden h-px bg-gray-200 md:block"></div>
            <div class="flex flex-col items-center text-center">
              <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-extrabold text-white shadow-lg" style="background: #4f46e5;">
                {{ step.number }}
              </div>
              <h3 class="mb-3 text-xl font-bold text-gray-900">{{ step.title }}</h3>
              <p class="text-gray-500 leading-relaxed text-sm">{{ step.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== FEATURES ===== -->
    <section id="features" style="background-color: #faf9f6;" class="px-6 py-24">
      <div class="mx-auto max-w-7xl">
        <!-- Alternating block 1 -->
        <div class="grid items-center gap-16 md:grid-cols-2 mb-24">
          <div>
            <span class="inline-block rounded-full bg-indigo-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-700 mb-5">Attendance</span>
            <h2 class="text-4xl font-extrabold tracking-tight text-gray-900 md:text-5xl leading-[1.1]">
              Effortless check-ins and attendance tracking.
            </h2>
            <p class="mt-6 text-lg text-gray-600 leading-relaxed">
              One-click roster for daily check-ins. Credits deduct automatically after each visit so your balance stays perfectly accurate — no manual entry, no errors.
            </p>
            <ul class="mt-8 space-y-3">
              <li v-for="item in checkInFeatures" :key="item" class="flex items-start gap-3 text-gray-700">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-sm font-medium">{{ item }}</span>
              </li>
            </ul>
          </div>
          <!-- Feature mockup 1 -->
          <div class="rounded-2xl overflow-hidden border border-gray-200 shadow-lg bg-white">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
              <span class="text-sm font-semibold text-gray-800">Check-in Roster</span>
              <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Live</span>
            </div>
            <div class="p-6 space-y-3">
              <div v-for="dog in featureMockDogs" :key="dog.name" class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold text-white" :style="{ background: dog.avatarBg }">{{ dog.initial }}</div>
                  <span class="text-sm font-medium text-gray-800">{{ dog.name }}</span>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-xs text-gray-400">{{ dog.credits }} credits</span>
                  <button class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="dog.checked ? 'bg-green-100 text-green-700' : 'bg-indigo-600 text-white hover:bg-indigo-700'">
                    {{ dog.checked ? '✓ In' : 'Check in' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Alternating block 2 -->
        <div class="grid items-center gap-16 md:grid-cols-2 mb-24">
          <!-- Payment mockup -->
          <div class="order-2 md:order-1 rounded-2xl overflow-hidden border border-gray-200 shadow-lg bg-white">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
              <span class="text-sm font-semibold text-gray-800">Package Purchase</span>
              <span class="flex items-center gap-1.5 text-xs text-gray-400">
                <svg class="w-3.5 h-3.5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Stripe secured
              </span>
            </div>
            <div class="p-6">
              <div class="space-y-3 mb-5">
                <div v-for="pkg in paymentMockPackages" :key="pkg.name" class="flex items-center justify-between rounded-xl border-2 px-4 py-3 cursor-pointer transition-colors"
                  :class="pkg.selected ? 'border-indigo-500 bg-indigo-50' : 'border-gray-100 bg-gray-50 hover:border-gray-200'">
                  <div>
                    <div class="text-sm font-semibold text-gray-800">{{ pkg.name }}</div>
                    <div class="text-xs text-gray-500">{{ pkg.credits }} · {{ pkg.type }}</div>
                  </div>
                  <span class="text-sm font-bold text-gray-900">{{ pkg.price }}</span>
                </div>
              </div>
              <button class="w-full rounded-xl bg-indigo-600 py-3 text-sm font-bold text-white hover:bg-indigo-700 transition-colors shadow-sm">
                Purchase with Stripe →
              </button>
              <p class="mt-3 text-center text-xs text-gray-400">Payouts go directly to your bank</p>
            </div>
          </div>
          <div class="order-1 md:order-2">
            <span class="inline-block rounded-full bg-amber-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-amber-700 mb-5">Payments</span>
            <h2 class="text-4xl font-extrabold tracking-tight text-gray-900 md:text-5xl leading-[1.1]">
              Payments that just work.
            </h2>
            <p class="mt-6 text-lg text-gray-600 leading-relaxed">
              Sell day packs or subscriptions. Stripe Connect routes payouts directly to your bank account. Automatic invoicing — zero manual billing.
            </p>
            <ul class="mt-8 space-y-3">
              <li v-for="item in paymentFeatures" :key="item" class="flex items-start gap-3 text-gray-700">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-sm font-medium">{{ item }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- 4-card grid -->
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="card in featureCards"
            :key="card.title"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-md cursor-default"
          >
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl" :style="{ background: card.iconBg }">
              <svg class="h-5 w-5" :style="{ color: card.iconColor }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" v-html="card.iconPath"></svg>
            </div>
            <h3 class="mb-2 text-base font-bold text-gray-900">{{ card.title }}</h3>
            <p class="text-sm text-gray-500 leading-relaxed">{{ card.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== TESTIMONIALS ===== -->
    <section class="bg-white px-6 py-24">
      <div class="mx-auto max-w-7xl">
        <div class="text-center mb-16">
          <h2 class="text-4xl font-extrabold tracking-tight text-gray-900 md:text-5xl">Loved by daycare owners</h2>
          <p class="mt-4 text-lg text-gray-500">Real stories from real businesses.</p>
        </div>
        <div class="grid gap-8 md:grid-cols-3">
          <div
            v-for="t in testimonials"
            :key="t.name"
            class="relative rounded-2xl border border-gray-100 bg-white p-8 shadow-sm transition-all duration-200 hover:shadow-md"
          >
            <!-- Big quote mark -->
            <div class="absolute top-6 right-6 text-5xl font-black leading-none" style="color: #f59e0b; opacity: 0.3;">"</div>
            <p class="text-gray-700 leading-relaxed mb-8 relative z-10">"{{ t.quote }}"</p>
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white" :style="{ background: t.avatarBg }">
                {{ t.initials }}
              </div>
              <div>
                <div class="text-sm font-bold text-gray-900">{{ t.name }}</div>
                <div class="text-xs text-gray-400">{{ t.business }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== PRICING ===== -->
    <section id="pricing" style="background-color: #faf9f6;" class="px-6 py-24">
      <div class="mx-auto max-w-7xl">
        <div class="text-center mb-4">
          <h2 class="text-4xl font-extrabold tracking-tight text-gray-900 md:text-5xl">Simple, transparent pricing</h2>
        </div>
        <p class="text-center text-gray-500 mb-16">All plans include a 21-day free trial. No credit card required.</p>

        <div class="grid gap-8 md:grid-cols-3 max-w-5xl mx-auto">
          <div
            v-for="plan in plans"
            :key="plan.name"
            class="relative flex flex-col rounded-2xl border p-8 transition-all"
            :class="plan.featured
              ? 'border-indigo-600 shadow-xl shadow-indigo-100 bg-white scale-[1.02]'
              : 'border-gray-200 bg-white shadow-sm'"
          >
            <div v-if="plan.featured" class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-4 py-1 text-xs font-bold text-white shadow">
              Most popular
            </div>

            <div class="mb-6">
              <h3 class="text-lg font-bold text-gray-900">{{ plan.name }}</h3>
              <div class="mt-3 flex items-baseline gap-1">
                <span class="text-5xl font-extrabold tracking-tight text-gray-900">{{ plan.price }}</span>
                <span class="text-sm text-gray-500 font-medium">/mo</span>
              </div>
              <p class="mt-2 text-xs text-indigo-600 font-semibold">21-day free trial</p>
            </div>

            <ul class="flex-1 space-y-3 mb-8">
              <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2.5 text-sm text-gray-600">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0" :class="plan.featured ? 'text-indigo-600' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                {{ feature }}
              </li>
            </ul>

            <a
              :href="plan.cta === 'Contact sales' ? '#' : '/register'"
              class="block rounded-xl py-3 text-center text-sm font-bold transition-all"
              :class="plan.featured
                ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg shadow-indigo-200'
                : 'border-2 border-indigo-600 text-indigo-600 hover:bg-indigo-50'"
            >
              {{ plan.cta }}
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== BOTTOM CTA ===== -->
    <section style="background-color: #0a0908;" class="relative overflow-hidden px-6 py-32 text-center">
      <div class="absolute inset-0 opacity-[0.04]" style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><filter id=%22n%22><feTurbulence type=%22fractalNoise%22 baseFrequency=%220.75%22 numOctaves=%224%22 stitchTiles=%22stitch%22/></filter><rect width=%22200%22 height=%22200%22 filter=%22url(%23n)%22 opacity=%221%22/></svg>');"></div>
      <div class="absolute inset-0 opacity-10" style="background: radial-gradient(circle at center, #4f46e5 0%, transparent 65%); filter: blur(60px);"></div>
      <div class="relative mx-auto max-w-3xl">
        <h2 class="text-5xl font-extrabold tracking-tight text-white md:text-6xl leading-tight">
          Ready to transform your daycare?
        </h2>
        <p class="mt-6 text-xl text-white/50">Join hundreds of doggy daycares already using PawPass.</p>
        <a
          href="/register"
          class="mt-10 inline-block rounded-xl bg-indigo-600 px-12 py-4 text-lg font-bold text-white shadow-xl hover:bg-indigo-500 transition-all hover:shadow-indigo-500/30 hover:shadow-2xl"
        >
          Start your free trial today
        </a>
      </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer style="background-color: #0a0908; border-top: 1px solid rgba(255,255,255,0.06);" class="px-6 py-16">
      <div class="mx-auto max-w-7xl">
        <div class="grid gap-12 md:grid-cols-3 mb-12">
          <!-- Brand col -->
          <div>
            <a href="/" class="flex items-center gap-2.5 mb-4">
              <svg width="24" height="24" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="14" cy="14" r="14" fill="#4f46e5"/>
                <ellipse cx="10" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
                <ellipse cx="18" cy="9" rx="2.5" ry="3" fill="white" opacity="0.9"/>
                <ellipse cx="7" cy="15" rx="2.2" ry="3" transform="rotate(-20 7 15)" fill="white" opacity="0.9"/>
                <ellipse cx="21" cy="15" rx="2.2" ry="3" transform="rotate(20 21 15)" fill="white" opacity="0.9"/>
                <ellipse cx="14" cy="19" rx="5" ry="4" fill="white"/>
              </svg>
              <span class="text-base font-bold text-white">PawPass</span>
            </a>
            <p class="text-sm text-white/40 leading-relaxed max-w-xs">The all-in-one platform for doggy daycare businesses.</p>
          </div>
          <!-- Product col -->
          <div>
            <h4 class="text-xs font-bold uppercase tracking-widest text-white/40 mb-5">Product</h4>
            <ul class="space-y-3">
              <li><a href="#features" class="text-sm text-white/60 hover:text-white transition-colors">Features</a></li>
              <li><a href="#pricing" class="text-sm text-white/60 hover:text-white transition-colors">Pricing</a></li>
              <li><a href="/register" class="text-sm text-white/60 hover:text-white transition-colors">Register</a></li>
            </ul>
          </div>
          <!-- Company col -->
          <div>
            <h4 class="text-xs font-bold uppercase tracking-widest text-white/40 mb-5">Company</h4>
            <ul class="space-y-3">
              <li><a href="#" class="text-sm text-white/60 hover:text-white transition-colors">About</a></li>
              <li><a href="#" class="text-sm text-white/60 hover:text-white transition-colors">Blog</a></li>
              <li><a href="#" class="text-sm text-white/60 hover:text-white transition-colors">Contact</a></li>
            </ul>
          </div>
        </div>
        <div style="border-top: 1px solid rgba(255,255,255,0.06);" class="pt-8">
          <p class="text-xs text-white/30 text-center">&copy; 2026 PawPass. All rights reserved.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

// ── Nav state ────────────────────────────────────────────────
const navScrolled = ref(false)
const heroSentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

// ── Login dropdown ───────────────────────────────────────────
const loginOpen = ref(false)
const loginSlug = ref('')
const loginDropdownRef = ref<HTMLElement | null>(null)

function goToLogin() {
  const slug = loginSlug.value.trim()
  if (!slug) return
  window.location.href = `https://${slug}.pawpass.com/admin/login`
}

function handleOutsideClick(e: MouseEvent) {
  if (loginDropdownRef.value && !loginDropdownRef.value.contains(e.target as Node)) {
    loginOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)

  if (heroSentinel.value) {
    observer = new IntersectionObserver(
      ([entry]) => { navScrolled.value = !entry.isIntersecting },
      { threshold: 0 }
    )
    observer.observe(heroSentinel.value)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
  observer?.disconnect()
})

// ── Hero product mockup data ─────────────────────────────────
const mockDogs = [
  { name: 'Biscuit', breed: 'Golden Retriever', initial: 'B', status: 'Checked In', credits: 8, avatarBg: '#fde68a', avatarColor: '#92400e' },
  { name: 'Luna',    breed: 'Border Collie',    initial: 'L', status: 'Checked In', credits: 3, avatarBg: '#ddd6fe', avatarColor: '#4c1d95' },
  { name: 'Mochi',   breed: 'Shih Tzu',         initial: 'M', status: 'Not Arrived', credits: 12, avatarBg: '#fbcfe8', avatarColor: '#9d174d' },
  { name: 'Rex',     breed: 'German Shepherd',  initial: 'R', status: 'Checked In', credits: 2, avatarBg: '#a7f3d0', avatarColor: '#065f46' },
  { name: 'Pepper',  breed: 'Dachshund',        initial: 'P', status: 'Not Arrived', credits: 6, avatarBg: '#fed7aa', avatarColor: '#9a3412' },
]

// ── Trusted businesses ───────────────────────────────────────
const trustedBusinesses = [
  'Paws & Play PDX',
  'Happy Tails Austin',
  'Bark Ave NYC',
  'Golden Paws Denver',
  'Sunny Side Seattle',
]

// ── How it works ─────────────────────────────────────────────
const howItWorks = [
  {
    number: 1,
    title: 'Create your account',
    description: 'Sign up in minutes. Add your business details, customers, and their dogs to get your roster ready.',
  },
  {
    number: 2,
    title: 'Set up packages',
    description: 'Create day packs or monthly subscriptions. Connect Stripe in one click for instant bank payouts.',
  },
  {
    number: 3,
    title: 'Start checking in',
    description: 'Use the live roster to check dogs in and out. Credits deduct automatically — no manual tracking.',
  },
]

// ── Feature blocks ───────────────────────────────────────────
const checkInFeatures = [
  'One-click check-in from any device',
  'Credits deduct automatically per visit',
  'Full visit history per dog',
  'Real-time roster view for staff',
]

const paymentFeatures = [
  'Accept cards via Stripe Connect',
  'Payouts go directly to your bank',
  'Sell day packs or subscriptions',
  'Automatic renewal invoicing',
]

const featureMockDogs = [
  { name: 'Biscuit', initial: 'B', credits: 8, checked: true, avatarBg: '#4f46e5' },
  { name: 'Luna',    initial: 'L', credits: 3, checked: true, avatarBg: '#7c3aed' },
  { name: 'Mochi',   initial: 'M', credits: 12, checked: false, avatarBg: '#db2777' },
  { name: 'Rex',     initial: 'R', credits: 2, checked: false, avatarBg: '#059669' },
]

const paymentMockPackages = [
  { name: '10-Day Pack', credits: '10 credits', type: 'One-time', price: '$120', selected: false },
  { name: '20-Day Pack', credits: '20 credits', type: 'One-time', price: '$220', selected: true },
  { name: 'Monthly Unlimited', credits: 'Unlimited days', type: 'Subscription', price: '$180/mo', selected: false },
]

// ── Feature cards ────────────────────────────────────────────
const featureCards = [
  {
    title: 'Customer Portal',
    description: 'Dog owners manage their own profiles, purchases, and visit history. Zero extra work for your staff.',
    iconBg: '#eef2ff',
    iconColor: '#4f46e5',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
  },
  {
    title: 'Smart Notifications',
    description: 'Automated SMS and email alerts for low credits, payment confirmations, and subscription renewals.',
    iconBg: '#fef3c7',
    iconColor: '#d97706',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
  },
  {
    title: 'Financial Reports',
    description: 'Revenue dashboards, attendance analytics, and per-dog credit reports. Always know your business.',
    iconBg: '#f0fdf4',
    iconColor: '#059669',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
  },
  {
    title: 'Multi-Dog Support',
    description: 'One customer account for multiple dogs. Credits, history, and check-ins tracked perfectly per dog.',
    iconBg: '#fdf4ff',
    iconColor: '#9333ea',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
  },
]

// ── Testimonials ─────────────────────────────────────────────
const testimonials = [
  {
    quote: 'PawPass replaced three separate tools for us. Our staff figured it out in an afternoon.',
    name: 'Sarah M.',
    business: 'Paws & Play Portland',
    initials: 'SM',
    avatarBg: '#4f46e5',
  },
  {
    quote: 'The credit system is genius. No more manual spreadsheets tracking who has days left.',
    name: 'James K.',
    business: 'Happy Tails Austin',
    initials: 'JK',
    avatarBg: '#059669',
  },
  {
    quote: 'Stripe payouts hit our account instantly. Setup took 20 minutes.',
    name: 'Priya L.',
    business: 'Bark Ave NYC',
    initials: 'PL',
    avatarBg: '#db2777',
  },
]

// ── Pricing ──────────────────────────────────────────────────
interface Plan {
  name: string
  price: string
  featured?: boolean
  cta: string
  features: string[]
}

const plans: Plan[] = [
  {
    name: 'Starter',
    price: '$29',
    cta: 'Start free trial',
    features: [
      'Unlimited customers & dogs',
      'Customer portal',
      'Check-in roster',
      'Day pack credits',
      'Email notifications',
      'Basic reports',
    ],
  },
  {
    name: 'Pro',
    price: '$79',
    featured: true,
    cta: 'Start free trial',
    features: [
      'Everything in Starter',
      'SMS notifications',
      'Advanced financial reports',
      'White-label branding',
      'Priority support',
    ],
  },
  {
    name: 'Business',
    price: '$149',
    cta: 'Contact sales',
    features: [
      'Everything in Pro',
      'Dedicated onboarding',
      'SLA support',
      'Custom integrations',
    ],
  },
]
</script>

<style scoped>
/* Hero entrance animations */
@keyframes heroFadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

.hero-item {
  opacity: 0;
  animation: heroFadeUp 0.6s ease forwards;
}

.hero-copy .hero-item:nth-child(1) { animation-delay: 0.1s; }
.hero-copy .hero-item:nth-child(2) { animation-delay: 0.25s; }
.hero-copy .hero-item:nth-child(3) { animation-delay: 0.4s; }
.hero-copy .hero-item:nth-child(4) { animation-delay: 0.55s; }

/* The mockup panel fades in slightly later */
.hero-item:not(.hero-copy .hero-item) {
  animation-delay: 0.5s;
}
</style>
