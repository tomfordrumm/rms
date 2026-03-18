<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Clock3, Minus, Plus, Sparkles, ShoppingBag, Stars, Trash2, WandSparkles } from 'lucide-vue-next';

type RestaurantPayload = {
    slug: string;
    name: string;
    description: string | null;
    work_hours: string | null;
    open_time: string | null;
    close_time: string | null;
    logo_url: string | null;
    cover_url: string | null;
};

type DishPayload = {
    id: number;
    name: string;
    description: string | null;
    weight: string;
    price: string;
    image_url: string | null;
};

type CategoryPayload = {
    id: number;
    name: string;
    description: string | null;
    dishes: DishPayload[];
};

type CartItem = DishPayload & {
    quantity: number;
};

type MagicOrderItem = DishPayload & {
    dish_id: number;
    quantity: number;
    reason: string;
};

const props = defineProps<{
    restaurant: RestaurantPayload;
    categories: CategoryPayload[];
}>();

const selectedCategoryId = ref<number | null>(null);
const isCartOpen = ref(false);
const isMagicOrderOpen = ref(false);
const isMagicOrderLoading = ref(false);
const magicOrderPreferences = ref('');
const magicOrderError = ref<string | null>(null);
const magicOrderResult = ref<{
    summary: string;
    items: MagicOrderItem[];
} | null>(null);
const workingHoursPrimary = computed(() => props.restaurant.work_hours?.trim() || null);
const workingHoursRange = computed(() => {
    if (!props.restaurant.open_time || !props.restaurant.close_time) {
        return null;
    }

    return `${props.restaurant.open_time} - ${props.restaurant.close_time}`;
});

function formatPrice(value: string): string {
    const parsed = Number(value);

    if (Number.isNaN(parsed)) {
        return value;
    }

    return `$${parsed.toFixed(2)}`;
}

const allDishes = computed(() => {
    const seen = new Set<number>();

    return props.categories.flatMap((category) =>
        category.dishes.filter((dish) => {
            if (seen.has(dish.id)) {
                return false;
            }

            seen.add(dish.id);

            return true;
        }),
    );
});

const selectedCategory = computed(() =>
    props.categories.find((category) => category.id === selectedCategoryId.value) ?? null,
);

const visibleDishes = computed(() => selectedCategory.value?.dishes ?? allDishes.value);
const cartStorageKey = computed(() => `restaurant-menu-cart:${props.restaurant.slug}`);
const storedCart = useStorage<Record<string, CartItem[]>>('restaurant-menu-carts', {});
const cartItems = ref<CartItem[]>(storedCart.value[cartStorageKey.value] ?? []);

watch(
    cartStorageKey,
    (nextKey) => {
        cartItems.value = storedCart.value[nextKey] ?? [];
    },
    { immediate: true },
);

watch(
    cartItems,
    (items) => {
        storedCart.value = {
            ...storedCart.value,
            [cartStorageKey.value]: items,
        };
    },
    { deep: true },
);

const cartItemsCount = computed(() =>
    cartItems.value.reduce((total, item) => total + item.quantity, 0),
);

const cartSubtotal = computed(() =>
    cartItems.value.reduce((total, item) => total + Number(item.price) * item.quantity, 0),
);

function addToCart(dish: DishPayload): void {
    mergeCartItems([{ ...dish, quantity: 1 }]);
    isCartOpen.value = true;
}

function mergeCartItems(itemsToMerge: Array<DishPayload & { quantity: number }>): void {
    const nextItems = [...cartItems.value];

    for (const incomingItem of itemsToMerge) {
        const existingItem = nextItems.find((item) => item.id === incomingItem.id);

        if (existingItem) {
            existingItem.quantity += incomingItem.quantity;
            continue;
        }

        nextItems.push({ ...incomingItem });
    }

    cartItems.value = nextItems;
}

function openMagicOrder(): void {
    isMagicOrderOpen.value = true;
    magicOrderError.value = null;
    magicOrderResult.value = null;
}

function closeMagicOrder(): void {
    isMagicOrderOpen.value = false;
    isMagicOrderLoading.value = false;
    magicOrderError.value = null;
    magicOrderResult.value = null;
}

async function submitMagicOrder(): Promise<void> {
    if (magicOrderPreferences.value.trim() === '' || isMagicOrderLoading.value) {
        return;
    }

    isMagicOrderLoading.value = true;
    magicOrderError.value = null;
    magicOrderResult.value = null;

    try {
        const csrfToken =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        const response = await fetch(`/r/${props.restaurant.slug}/magic-order`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                preferences: magicOrderPreferences.value.trim(),
            }),
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            if (payload?.errors?.preferences?.[0]) {
                magicOrderError.value = payload.errors.preferences[0] as string;
            } else if (payload?.message) {
                magicOrderError.value = payload.message as string;
            } else {
                magicOrderError.value = 'Magic order is unavailable right now.';
            }

            return;
        }

        magicOrderResult.value = payload as {
            summary: string;
            items: MagicOrderItem[];
        };
    } catch {
        magicOrderError.value = 'Magic order is unavailable right now.';
    } finally {
        isMagicOrderLoading.value = false;
    }
}

function addMagicOrderToCart(): void {
    if (!magicOrderResult.value) {
        return;
    }

    mergeCartItems(
        magicOrderResult.value.items.map((item) => ({
            id: item.dish_id,
            name: item.name,
            description: item.description,
            weight: item.weight,
            price: item.price,
            image_url: item.image_url,
            quantity: item.quantity,
        })),
    );

    closeMagicOrder();
    isCartOpen.value = true;
}
function updateQuantity(dishId: number, nextQuantity: number): void {
    if (nextQuantity <= 0) {
        removeFromCart(dishId);

        return;
    }

    cartItems.value = cartItems.value.map((item) =>
        item.id === dishId ? { ...item, quantity: nextQuantity } : item,
    );
}

function removeFromCart(dishId: number): void {
    cartItems.value = cartItems.value.filter((item) => item.id !== dishId);
}
</script>

<template>
    <Head :title="`${restaurant.name} Menu`">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link
            href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(196,168,120,0.25),_transparent_28%),linear-gradient(180deg,_#f7f2e8_0%,_#efe6d8_36%,_#fbfaf8_100%)] text-slate-900"
        style="font-family: 'Manrope', var(--font-sans)"
    >
        <Dialog :open="isMagicOrderOpen" @update:open="(open) => (open ? openMagicOrder() : closeMagicOrder())">
            <DialogContent
                class="max-h-[90vh] max-w-2xl overflow-y-auto rounded-[2rem] border-stone-200 bg-[#faf6ef] p-0 shadow-[0_40px_120px_-60px_rgba(15,23,42,0.65)]"
            >
                <DialogHeader class="border-b border-stone-200/80 px-6 py-6 text-left sm:px-8">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-[#12192b] text-white shadow-[0_18px_40px_-24px_rgba(15,23,42,0.85)]"
                        >
                            <WandSparkles class="size-5" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-2xl tracking-[-0.03em] text-slate-950"
                                style="font-family: 'Cormorant Garamond', ui-serif, Georgia, serif"
                            >
                                Magic order
                            </DialogTitle>
                            <DialogDescription class="text-slate-500">
                                Tell us what you want and we will assemble a menu suggestion for you.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="space-y-6 px-6 py-6 sm:px-8 sm:py-8">
                    <div
                        v-if="magicOrderResult"
                        class="space-y-5"
                    >
                        <div class="rounded-[1.5rem] border border-stone-200 bg-white p-5 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.35)]">
                            <div class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <Stars class="size-4 text-emerald-500" />
                                Why this set works
                            </div>
                            <p class="text-sm leading-7 text-slate-700">
                                {{ magicOrderResult.summary }}
                            </p>
                        </div>

                        <div class="space-y-4">
                            <article
                                v-for="item in magicOrderResult.items"
                                :key="item.dish_id"
                                class="rounded-[1.5rem] border border-stone-200 bg-white p-5 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.35)]"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold text-slate-950">
                                            {{ item.name }}
                                        </h3>
                                        <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">
                                            {{ item.weight }} · Qty {{ item.quantity }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-lg font-extrabold text-emerald-600">
                                        {{ formatPrice(item.price) }}
                                    </span>
                                </div>

                                <p
                                    v-if="item.reason"
                                    class="mt-4 text-sm leading-6 text-slate-600"
                                >
                                    {{ item.reason }}
                                </p>
                            </article>
                        </div>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                class="rounded-full"
                                @click="closeMagicOrder()"
                            >
                                Отмена
                            </Button>
                            <Button
                                type="button"
                                class="rounded-full bg-[#12192b] text-white hover:bg-[#182038]"
                                @click="addMagicOrderToCart()"
                            >
                                Добавить всё в корзину
                            </Button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="space-y-5"
                    >
                        <div class="space-y-3">
                            <label
                                for="magic-order-preferences"
                                class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500"
                            >
                                Preferences and restrictions
                            </label>
                            <textarea
                                id="magic-order-preferences"
                                v-model="magicOrderPreferences"
                                class="min-h-40 w-full rounded-[1.5rem] border border-stone-200 bg-white px-5 py-4 text-sm leading-6 text-slate-700 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.35)] outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                                placeholder="Например: хочу легкий ужин без красного мяса, люблю морепродукты, острое не хочу, на двоих."
                            />
                        </div>

                        <div
                            v-if="magicOrderError"
                            class="rounded-[1.5rem] border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700"
                        >
                            {{ magicOrderError }}
                        </div>

                        <div
                            v-if="isMagicOrderLoading"
                            class="rounded-[1.5rem] border border-stone-200 bg-white px-5 py-4 text-sm leading-6 text-slate-600 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.35)]"
                        >
                            Собираем рекомендацию по вашему запросу.
                        </div>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                class="rounded-full"
                                :disabled="isMagicOrderLoading"
                                @click="closeMagicOrder()"
                            >
                                Отмена
                            </Button>
                            <Button
                                type="button"
                                class="rounded-full bg-[#12192b] text-white hover:bg-[#182038]"
                                :disabled="magicOrderPreferences.trim() === '' || isMagicOrderLoading"
                                @click="submitMagicOrder()"
                            >
                                {{ isMagicOrderLoading ? 'Собираем заказ...' : 'Соберите заказ за меня' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <Sheet v-model:open="isCartOpen">
            <SheetContent
                side="right"
                class="w-full border-l border-stone-200 bg-[#faf6ef] p-0 sm:max-w-md"
            >
                <SheetHeader class="border-b border-stone-200/80 px-6 py-6 text-left">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-[#12192b] text-white shadow-[0_18px_40px_-24px_rgba(15,23,42,0.85)]"
                        >
                            <ShoppingBag class="size-5" />
                        </div>
                        <div>
                            <SheetTitle
                                class="text-xl tracking-[-0.03em] text-slate-950"
                                style="font-family: 'Cormorant Garamond', ui-serif, Georgia, serif"
                            >
                                Your cart
                            </SheetTitle>
                            <SheetDescription class="text-slate-500">
                                {{ cartItemsCount }} item{{ cartItemsCount === 1 ? '' : 's' }} selected
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div class="flex min-h-0 flex-1 flex-col">
                    <div
                        v-if="cartItems.length > 0"
                        class="flex-1 space-y-4 overflow-y-auto px-6 py-6"
                    >
                        <article
                            v-for="item in cartItems"
                            :key="item.id"
                            class="rounded-[1.5rem] border border-stone-200 bg-white p-4 shadow-[0_24px_50px_-40px_rgba(15,23,42,0.35)]"
                        >
                            <div class="flex gap-4">
                                <div
                                    class="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-[#151a14]"
                                >
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        :alt="item.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-end bg-[radial-gradient(circle_at_top,_rgba(53,104,84,0.38),_transparent_34%),linear-gradient(135deg,_#20251d,_#10120d_55%,_#1e2b1f)] p-3"
                                    >
                                        <span
                                            class="line-clamp-2 text-xs font-semibold leading-4 text-white"
                                        >
                                            {{ item.name }}
                                        </span>
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-semibold text-slate-950">
                                                {{ item.name }}
                                            </h3>
                                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">
                                                {{ item.weight }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="rounded-full p-2 text-slate-400 transition hover:bg-stone-100 hover:text-red-500"
                                            @click="removeFromCart(item.id)"
                                        >
                                            <Trash2 class="size-4" />
                                            <span class="sr-only">Remove item</span>
                                        </button>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <div
                                            class="inline-flex items-center rounded-full border border-stone-200 bg-stone-50 p-1"
                                        >
                                            <button
                                                type="button"
                                                class="flex size-8 items-center justify-center rounded-full text-slate-600 transition hover:bg-white"
                                                @click="updateQuantity(item.id, item.quantity - 1)"
                                            >
                                                <Minus class="size-4" />
                                                <span class="sr-only">Decrease quantity</span>
                                            </button>
                                            <span class="min-w-10 text-center text-sm font-semibold text-slate-950">
                                                {{ item.quantity }}
                                            </span>
                                            <button
                                                type="button"
                                                class="flex size-8 items-center justify-center rounded-full text-slate-600 transition hover:bg-white"
                                                @click="updateQuantity(item.id, item.quantity + 1)"
                                            >
                                                <Plus class="size-4" />
                                                <span class="sr-only">Increase quantity</span>
                                            </button>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">
                                                Total
                                            </p>
                                            <p class="text-lg font-extrabold text-emerald-600">
                                                {{ formatPrice(String(Number(item.price) * item.quantity)) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="flex flex-1 items-center justify-center px-6 py-12"
                    >
                        <div class="max-w-xs text-center">
                            <div
                                class="mx-auto flex size-16 items-center justify-center rounded-full bg-stone-200/70 text-slate-500"
                            >
                                <ShoppingBag class="size-7" />
                            </div>
                            <h3
                                class="mt-5 text-2xl font-semibold text-slate-950"
                                style="font-family: 'Cormorant Garamond', ui-serif, Georgia, serif"
                            >
                                Cart is empty
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Add dishes from the menu and they will appear here automatically.
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-stone-200/80 bg-white/85 px-6 py-5">
                        <div class="mb-4 flex items-center justify-between gap-4">
                            <span class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
                                Subtotal
                            </span>
                            <span class="text-2xl font-extrabold text-slate-950">
                                {{ formatPrice(String(cartSubtotal)) }}
                            </span>
                        </div>

                        <p class="text-xs leading-5 text-slate-500">
                            Cart is stored locally in this browser for this restaurant only.
                        </p>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8 lg:py-8">
            <section
                class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-[#11130f] shadow-[0_45px_120px_-55px_rgba(29,21,8,0.9)]"
            >
                <div
                    class="absolute inset-0 bg-cover bg-center opacity-45"
                    :style="restaurant.cover_url ? { backgroundImage: `url(${restaurant.cover_url})` } : undefined"
                />
                <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(17,19,15,0.2),rgba(17,19,15,0.78)_50%,rgba(6,20,12,0.92)_100%)]" />
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(237,196,114,0.32),_transparent_26%)]" />
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,_rgba(66,132,97,0.22),_transparent_28%)]" />

                <div class="relative px-6 py-7 sm:px-8 sm:py-10 lg:px-12 lg:py-14">
                    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_240px] lg:items-end">
                        <div class="space-y-6">
                            <Badge
                                variant="secondary"
                                class="w-fit border border-white/15 bg-white/10 px-4 py-2 text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-white"
                            >
                                Signature Menu
                            </Badge>

                            <div class="space-y-4">
                                <div
                                    v-if="restaurant.logo_url"
                                    class="flex h-18 w-18 items-center justify-center overflow-hidden rounded-3xl border border-white/15 bg-white/12 p-3 shadow-[0_14px_40px_-24px_rgba(0,0,0,0.7)] backdrop-blur-sm sm:h-22 sm:w-22"
                                >
                                    <img
                                        :src="restaurant.logo_url"
                                        :alt="`${restaurant.name} logo`"
                                        class="h-full w-full object-contain"
                                    />
                                </div>

                                <div class="max-w-3xl space-y-3">
                                    <h1
                                        class="text-4xl font-semibold leading-[0.9] tracking-[-0.04em] text-white sm:text-5xl lg:text-7xl"
                                        style="font-family: 'Cormorant Garamond', ui-serif, Georgia, serif"
                                    >
                                        {{ restaurant.name }}
                                    </h1>
                                    <p
                                        v-if="restaurant.description"
                                        class="max-w-2xl text-sm leading-7 text-stone-200/84 sm:text-base"
                                    >
                                        {{ restaurant.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-[1.75rem] border border-white/12 bg-white/8 p-5 text-white shadow-[0_20px_60px_-40px_rgba(0,0,0,0.9)] backdrop-blur-md"
                        >
                            <div class="mb-3 flex items-center gap-3 text-sm font-semibold tracking-[0.2em] text-stone-100 uppercase">
                                <Clock3 class="h-4 w-4 text-amber-200" />
                                Hours
                            </div>
                            <div class="space-y-2">
                                <p
                                    v-if="workingHoursPrimary"
                                    class="text-lg font-semibold leading-7 text-white"
                                >
                                    {{ workingHoursPrimary }}
                                </p>
                                <p
                                    v-if="workingHoursRange"
                                    class="text-sm leading-6 text-stone-200/80"
                                >
                                    {{ workingHoursRange }}
                                </p>
                                <p
                                    v-if="!workingHoursPrimary && !workingHoursRange"
                                    class="text-sm leading-6 text-stone-200/70"
                                >
                                    Hours will be announced soon.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <main class="mt-8 space-y-8 sm:mt-10 sm:space-y-10">
                <section
                    class="rounded-[2rem] border border-stone-200/80 bg-white/92 p-5 shadow-[0_35px_90px_-70px_rgba(15,23,42,0.45)] backdrop-blur-sm sm:p-7 lg:p-8"
                >
                    <div
                        class="mb-6 flex flex-col gap-5 border-b border-stone-200/80 pb-5 sm:mb-8 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-4">
                            <div class="flex justify-start">
                                <Button
                                    type="button"
                                    class="rounded-full bg-[#12192b] px-5 text-white shadow-[0_18px_40px_-24px_rgba(15,23,42,0.85)] hover:bg-[#182038]"
                                    @click="openMagicOrder()"
                                >
                                    <WandSparkles class="size-4" />
                                    Magic order
                                </Button>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    class="rounded-full px-5 py-3 text-sm font-semibold transition"
                                    :class="
                                        selectedCategoryId === null
                                            ? 'bg-emerald-500 text-white shadow-[0_18px_40px_-24px_rgba(16,185,129,0.9)]'
                                            : 'border border-stone-200 bg-stone-100 text-slate-600 hover:border-stone-300 hover:bg-stone-200/80'
                                    "
                                    @click="selectedCategoryId = null"
                                >
                                    All items
                                </button>

                                <button
                                    v-for="category in categories"
                                    :key="category.id"
                                    type="button"
                                    class="rounded-full px-5 py-3 text-sm font-semibold transition"
                                    :class="
                                        selectedCategoryId === category.id
                                            ? 'bg-[#12192b] text-white shadow-[0_18px_40px_-24px_rgba(15,23,42,0.85)]'
                                            : 'border border-stone-200 bg-stone-100 text-slate-600 hover:border-stone-300 hover:bg-stone-200/80'
                                    "
                                    @click="selectedCategoryId = category.id"
                                >
                                    {{ category.name }}
                                </button>
                            </div>
                        </div>

                        <div
                            class="inline-flex w-fit items-center gap-2 rounded-full border border-stone-200 bg-stone-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500"
                        >
                            <Sparkles class="h-3.5 w-3.5 text-emerald-500" />
                            {{ visibleDishes.length }} items
                        </div>
                    </div>

                    <div
                        v-if="visibleDishes.length > 0"
                        class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="dish in visibleDishes"
                            :key="dish.id"
                            class="group overflow-hidden rounded-[1.75rem] border border-stone-200/80 bg-stone-50/80 shadow-[0_28px_60px_-45px_rgba(15,23,42,0.4)] transition-transform duration-300 hover:-translate-y-1"
                        >
                            <div class="relative aspect-[16/11] overflow-hidden bg-[#141712]">
                                <img
                                    v-if="dish.image_url"
                                    :src="dish.image_url"
                                    :alt="dish.name"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-end bg-[radial-gradient(circle_at_top,_rgba(53,104,84,0.4),_transparent_34%),linear-gradient(135deg,_#20251d,_#10120d_55%,_#1e2b1f)] p-5"
                                >
                                    <div class="max-w-[12rem]">
                                        <p
                                            class="text-lg font-semibold leading-6 text-white"
                                            style="font-family: 'Cormorant Garamond', ui-serif, Georgia, serif"
                                        >
                                            {{ dish.name }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="absolute right-4 top-4 rounded-full border border-white/15 bg-[#111827]/88 px-3 py-1 text-[0.63rem] font-semibold uppercase tracking-[0.22em] text-white shadow-lg"
                                >
                                    {{ dish.weight }}
                                </div>
                            </div>

                            <div class="space-y-5 px-5 py-5">
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <h3
                                            class="text-xl font-semibold leading-6 tracking-[-0.03em] text-slate-950"
                                        >
                                            {{ dish.name }}
                                        </h3>
                                        <span class="shrink-0 text-lg font-extrabold text-emerald-500">
                                            {{ formatPrice(dish.price) }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="dish.description"
                                        class="text-sm leading-6 text-slate-600"
                                    >
                                        {{ dish.description }}
                                    </p>
                                    <p
                                        v-else
                                        class="text-sm leading-6 text-slate-400"
                                    >
                                        Seasonal preparation crafted by the kitchen for today's service.
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    class="h-11 w-full rounded-full bg-[#12192b] text-sm font-semibold text-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.85)] transition hover:bg-[#182038]"
                                    @click="addToCart(dish)"
                                >
                                    Добавить в корзину
                                </Button>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-[1.75rem] border border-dashed border-stone-200 bg-stone-50/80 px-6 py-14 text-center"
                    >
                        <p
                            class="text-2xl font-semibold tracking-[-0.03em] text-slate-900"
                            style="font-family: 'Cormorant Garamond', ui-serif, Georgia, serif"
                        >
                            Nothing to show in this category yet
                        </p>
                        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-500">
                            Switch to another filter or come back later when the kitchen updates the selection.
                        </p>
                    </div>
                </section>
            </main>
        </div>

        <button
            type="button"
            class="fixed right-5 bottom-5 z-40 flex items-center gap-3 rounded-full bg-[#12192b] px-5 py-4 text-sm font-semibold text-white shadow-[0_28px_60px_-30px_rgba(15,23,42,0.9)] transition hover:bg-[#182038] sm:right-8 sm:bottom-8"
            @click="isCartOpen = true"
        >
            <span class="relative flex size-11 items-center justify-center rounded-full bg-white/10">
                <ShoppingBag class="size-5" />
                <span
                    v-if="cartItemsCount > 0"
                    class="absolute -top-1 -right-1 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-500 px-1.5 py-0.5 text-[0.65rem] font-bold leading-none text-white"
                >
                    {{ cartItemsCount }}
                </span>
            </span>
            <span class="hidden sm:block">
                {{ cartItemsCount > 0 ? 'Open cart' : 'Cart' }}
            </span>
        </button>
    </div>
</template>
