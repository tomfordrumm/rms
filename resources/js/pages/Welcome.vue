<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import {
    UtensilsCrossed,
    QrCode,
    CalendarCheck,
    Sparkles,
    ChefHat,
    ArrowRight,
    Check,
    Menu,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const mobileMenuOpen = ref(false);
</script>

<template>
    <Head title="RMS — Restaurant Management System">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
            href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <!-- Navigation -->
        <nav class="fixed top-0 z-50 w-full border-b border-gray-100 bg-white/80 backdrop-blur-lg dark:border-gray-800 dark:bg-gray-950/80">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="flex items-center gap-2 text-xl font-bold tracking-tight">
                    <ChefHat class="h-7 w-7 text-orange-500" />
                    <span>RMS</span>
                </Link>

                <!-- Desktop nav -->
                <div class="hidden items-center gap-6 md:flex">
                    <a href="#features" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        Features
                    </a>
                    <a href="#how-it-works" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        How it works
                    </a>
                    <a href="#pricing" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        Pricing
                    </a>

                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="dashboard()"
                            class="rounded-lg bg-orange-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-orange-600"
                        >
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-lg bg-orange-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-orange-600"
                        >
                            Get Started Free
                        </Link>
                    </template>
                </div>

                <!-- Mobile menu button -->
                <button
                    class="md:hidden"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X v-if="mobileMenuOpen" class="h-6 w-6" />
                    <Menu v-else class="h-6 w-6" />
                </button>
            </div>

            <!-- Mobile menu -->
            <div
                v-if="mobileMenuOpen"
                class="border-t border-gray-100 bg-white px-6 py-4 md:hidden dark:border-gray-800 dark:bg-gray-950"
            >
                <div class="flex flex-col gap-4">
                    <a href="#features" class="text-sm font-medium text-gray-600 dark:text-gray-400" @click="mobileMenuOpen = false">Features</a>
                    <a href="#how-it-works" class="text-sm font-medium text-gray-600 dark:text-gray-400" @click="mobileMenuOpen = false">How it works</a>
                    <a href="#pricing" class="text-sm font-medium text-gray-600 dark:text-gray-400" @click="mobileMenuOpen = false">Pricing</a>
                    <template v-if="$page.props.auth.user">
                        <Link :href="dashboard()" class="rounded-lg bg-orange-500 px-5 py-2.5 text-center text-sm font-semibold text-white">Dashboard</Link>
                    </template>
                    <template v-else>
                        <Link :href="login()" class="text-sm font-medium text-gray-600 dark:text-gray-400">Log in</Link>
                        <Link v-if="canRegister" :href="register()" class="rounded-lg bg-orange-500 px-5 py-2.5 text-center text-sm font-semibold text-white">Get Started Free</Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <section class="relative overflow-hidden pt-32 pb-20 lg:pt-44 lg:pb-32">
            <div class="absolute inset-0 -z-10">
                <div class="absolute top-0 left-1/2 h-[600px] w-[600px] -translate-x-1/2 rounded-full bg-orange-100 opacity-40 blur-3xl dark:bg-orange-900/20"></div>
            </div>

            <div class="mx-auto max-w-6xl px-6 text-center">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-1.5 text-sm font-medium text-orange-700 dark:border-orange-800 dark:bg-orange-950 dark:text-orange-300">
                    <Sparkles class="h-4 w-4" />
                    AI-powered restaurant management
                </div>

                <h1 class="mx-auto max-w-4xl text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl lg:text-7xl lg:leading-[1.1]">
                    Your restaurant,
                    <span class="bg-gradient-to-r from-orange-500 to-amber-500 bg-clip-text text-transparent">fully digital</span>
                </h1>

                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                    Digital menu with QR code, online booking, and AI recommendations — everything your restaurant needs to grow, in one platform.
                </p>

                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        :href="canRegister ? register() : login()"
                        class="group inline-flex items-center gap-2 rounded-xl bg-orange-500 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600 hover:shadow-xl hover:shadow-orange-500/30"
                    >
                        Register your restaurant
                        <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                    </Link>
                    <a
                        href="#features"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-8 py-3.5 text-base font-semibold transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900"
                    >
                        Learn more
                    </a>
                </div>

                <!-- Hero visual -->
                <div class="relative mx-auto mt-16 max-w-4xl">
                    <div class="rounded-2xl border border-gray-200 bg-gradient-to-b from-gray-50 to-white p-2 shadow-2xl dark:border-gray-800 dark:from-gray-900 dark:to-gray-950">
                        <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-900">
                            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                                <div class="h-3 w-3 rounded-full bg-red-400"></div>
                                <div class="h-3 w-3 rounded-full bg-yellow-400"></div>
                                <div class="h-3 w-3 rounded-full bg-green-400"></div>
                                <span class="ml-3 text-xs text-gray-400">rms.app/r/your-restaurant/menu</span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 p-6">
                                <div v-for="i in 3" :key="i" class="space-y-3">
                                    <div class="h-28 rounded-lg bg-gradient-to-br from-orange-100 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/10"></div>
                                    <div class="h-3 w-3/4 rounded bg-gray-100 dark:bg-gray-800"></div>
                                    <div class="h-2 w-1/2 rounded bg-gray-100 dark:bg-gray-800"></div>
                                    <div class="h-3 w-1/3 rounded bg-orange-100 dark:bg-orange-900/30"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="scroll-mt-20 py-20 lg:py-32">
            <div class="mx-auto max-w-6xl px-6">
                <div class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Everything for your restaurant
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                        Three powerful tools that work together to help you manage your business and delight your guests.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <!-- Feature 1: Digital Menu -->
                    <div class="group rounded-2xl border border-gray-100 bg-white p-8 transition hover:border-orange-200 hover:shadow-lg dark:border-gray-800 dark:bg-gray-900 dark:hover:border-orange-800">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                            <UtensilsCrossed class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-semibold">Digital Menu</h3>
                        <p class="mt-3 leading-relaxed text-gray-600 dark:text-gray-400">
                            Create a beautiful menu with categories, photos, descriptions, and prices. Guests can browse from their phone — no app required.
                        </p>
                        <ul class="mt-5 space-y-2">
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Categories &amp; dishes
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Photos &amp; descriptions
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Built-in shopping cart
                            </li>
                        </ul>
                    </div>

                    <!-- Feature 2: QR Code -->
                    <div class="group rounded-2xl border border-gray-100 bg-white p-8 transition hover:border-orange-200 hover:shadow-lg dark:border-gray-800 dark:bg-gray-900 dark:hover:border-orange-800">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                            <QrCode class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-semibold">QR Codes</h3>
                        <p class="mt-3 leading-relaxed text-gray-600 dark:text-gray-400">
                            Generate QR codes for your menu and booking page. Place them on tables and let guests access everything instantly.
                        </p>
                        <ul class="mt-5 space-y-2">
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Auto-generated codes
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Menu &amp; booking links
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Print-ready
                            </li>
                        </ul>
                    </div>

                    <!-- Feature 3: Online Booking -->
                    <div class="group rounded-2xl border border-gray-100 bg-white p-8 transition hover:border-orange-200 hover:shadow-lg dark:border-gray-800 dark:bg-gray-900 dark:hover:border-orange-800">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <CalendarCheck class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-semibold">Online Booking</h3>
                        <p class="mt-3 leading-relaxed text-gray-600 dark:text-gray-400">
                            Accept reservations 24/7. Guests pick a date, time, and party size — the system checks availability automatically.
                        </p>
                        <ul class="mt-5 space-y-2">
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Real-time availability
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Self-service management
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Check class="h-4 w-4 text-orange-500" /> Email notifications
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- AI Feature highlight -->
                <div class="mt-12 rounded-2xl border border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50 p-8 md:p-12 dark:border-orange-900/30 dark:from-orange-950/30 dark:to-amber-950/30">
                    <div class="flex flex-col items-start gap-6 md:flex-row md:items-center">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-500 text-white shadow-lg shadow-orange-500/25">
                            <Sparkles class="h-7 w-7" />
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold">Magic Order — AI Recommendations</h3>
                            <p class="mt-2 leading-relaxed text-gray-600 dark:text-gray-400">
                                Your guests tell the AI what they're in the mood for, and it suggests dishes from your menu. A personal sommelier and chef in every phone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="scroll-mt-20 bg-gray-50 py-20 lg:py-32 dark:bg-gray-900/50">
            <div class="mx-auto max-w-6xl px-6">
                <div class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Up and running in 3 steps
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                        No developers needed. Set up your digital presence in minutes.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <div v-for="(step, index) in [
                        { title: 'Register', desc: 'Create an account and fill in your restaurant details — name, address, working hours, contacts.' },
                        { title: 'Build your menu', desc: 'Add categories and dishes with photos, descriptions, and prices. Your digital menu is ready.' },
                        { title: 'Share with guests', desc: 'Print the QR code, put it on your tables. Guests browse the menu and book tables from their phone.' },
                    ]" :key="index" class="relative text-center">
                        <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-orange-500 text-lg font-bold text-white">
                            {{ index + 1 }}
                        </div>
                        <h3 class="text-lg font-semibold">{{ step.title }}</h3>
                        <p class="mt-2 leading-relaxed text-gray-600 dark:text-gray-400">
                            {{ step.desc }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="scroll-mt-20 py-20 lg:py-32">
            <div class="mx-auto max-w-6xl px-6">
                <div class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Simple pricing
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                        Start for free. No credit card required.
                    </p>
                </div>

                <div class="mx-auto mt-16 grid max-w-4xl gap-8 md:grid-cols-2">
                    <!-- Free plan -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-lg font-semibold">Free</h3>
                        <div class="mt-4">
                            <span class="text-4xl font-bold">$0</span>
                            <span class="text-gray-500">/month</span>
                        </div>
                        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                            Perfect for trying out the platform.
                        </p>
                        <ul class="mt-6 space-y-3">
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> Digital menu
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> QR codes
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> Up to 20 dishes
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> Online booking
                            </li>
                        </ul>
                        <Link
                            :href="canRegister ? register() : login()"
                            class="mt-8 block rounded-xl border border-gray-200 py-3 text-center text-sm font-semibold transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            Get Started
                        </Link>
                    </div>

                    <!-- Pro plan -->
                    <div class="relative rounded-2xl border-2 border-orange-500 bg-white p-8 shadow-lg dark:bg-gray-900">
                        <div class="absolute -top-3 right-6 rounded-full bg-orange-500 px-3 py-1 text-xs font-semibold text-white">
                            Popular
                        </div>
                        <h3 class="text-lg font-semibold">Pro</h3>
                        <div class="mt-4">
                            <span class="text-4xl font-bold">$29</span>
                            <span class="text-gray-500">/month</span>
                        </div>
                        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                            For restaurants ready to grow.
                        </p>
                        <ul class="mt-6 space-y-3">
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> Everything in Free
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> Unlimited dishes
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> AI Magic Order
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Check class="h-4 w-4 text-orange-500" /> Priority support
                            </li>
                        </ul>
                        <Link
                            :href="canRegister ? register() : login()"
                            class="mt-8 block rounded-xl bg-orange-500 py-3 text-center text-sm font-semibold text-white transition hover:bg-orange-600"
                        >
                            Start Free Trial
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-gray-950 py-20 text-white lg:py-28 dark:bg-white dark:text-gray-900">
            <div class="mx-auto max-w-6xl px-6 text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                    Ready to go digital?
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-lg text-gray-400 dark:text-gray-600">
                    Join hundreds of restaurants already using RMS. Set up takes less than 10 minutes.
                </p>
                <Link
                    :href="canRegister ? register() : login()"
                    class="group mt-8 inline-flex items-center gap-2 rounded-xl bg-orange-500 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600"
                >
                    Create your restaurant
                    <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-100 py-10 dark:border-gray-800">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 md:flex-row">
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <ChefHat class="h-5 w-5 text-orange-500" />
                    RMS
                </div>
                <p class="text-sm text-gray-500">
                    &copy; {{ new Date().getFullYear() }} RMS. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</template>
