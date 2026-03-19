<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, Download, QrCode } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    restaurant_name: string;
    slug: string;
    menu_url: string;
    booking_url: string;
    menu_qr_svg: string;
    booking_qr_svg: string;
};

type QrCard = {
    key: 'menu' | 'booking';
    title: string;
    description: string;
    url: string;
    svg: string;
    filename: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'QR',
        href: '/admin/qr',
    },
];

const { copy, copied } = useClipboard();
const activeCopiedKey = ref<QrCard['key'] | null>(null);

const cards = computed<QrCard[]>(() => [
    {
        key: 'menu',
        title: 'QR-код для меню',
        description: 'Публичная ссылка на цифровое меню ресторана.',
        url: props.menu_url,
        svg: props.menu_qr_svg,
        filename: `${props.slug}-menu-qr.png`,
    },
    {
        key: 'booking',
        title: 'QR-код для Booking',
        description: 'Публичная ссылка на страницу бронирования столика.',
        url: props.booking_url,
        svg: props.booking_qr_svg,
        filename: `${props.slug}-booking-qr.png`,
    },
]);

async function copyLink(card: QrCard): Promise<void> {
    await copy(card.url);
    activeCopiedKey.value = card.key;
}

async function downloadPng(card: QrCard): Promise<void> {
    const imageUrl = createSvgObjectUrl(card.svg);

    try {
        const image = await loadImage(imageUrl);
        const canvas = document.createElement('canvas');
        const size = 1024;

        canvas.width = size;
        canvas.height = size;

        const context = canvas.getContext('2d');

        if (context === null) {
            throw new Error('Canvas context is unavailable.');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size, size);
        context.drawImage(image, 0, 0, size, size);

        const pngUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.href = pngUrl;
        link.download = card.filename;
        link.click();
    } finally {
        URL.revokeObjectURL(imageUrl);
    }
}

function createSvgObjectUrl(svg: string): string {
    const blob = new Blob([svg], {
        type: 'image/svg+xml;charset=utf-8',
    });

    return URL.createObjectURL(blob);
}

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Unable to load QR image.'));
        image.src = src;
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="QR" />

        <div class="space-y-6 px-4 py-6">
            <Heading
                title="QR"
                :description="`QR-коды и публичные ссылки для ${restaurant_name}.`"
            />

            <div class="grid gap-6 md:grid-cols-2">
                <Card
                    v-for="card in cards"
                    :key="card.key"
                    class="rounded-[1.5rem] border-border/70"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle class="flex items-center gap-2">
                            <QrCode class="h-5 w-5 text-muted-foreground" />
                            {{ card.title }}
                        </CardTitle>
                        <CardDescription>
                            {{ card.description }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div
                            class="flex min-h-80 items-center justify-center rounded-[1.5rem] border border-dashed border-border/70 bg-muted/20 p-6"
                        >
                            <div
                                class="w-full max-w-xs rounded-2xl bg-white p-5 shadow-sm"
                                v-html="card.svg"
                            />
                        </div>

                        <div class="rounded-xl border border-border/70 bg-muted/20 px-4 py-3">
                            <div class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">
                                Public URL
                            </div>
                            <div class="mt-2 break-all text-sm text-foreground">
                                {{ card.url }}
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter class="flex flex-col gap-3 sm:flex-row">
                        <Button
                            type="button"
                            class="w-full sm:flex-1"
                            @click="downloadPng(card)"
                        >
                            <Download class="mr-2 h-4 w-4" />
                            Download PNG
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            class="w-full sm:flex-1"
                            @click="copyLink(card)"
                        >
                            <Check
                                v-if="copied && activeCopiedKey === card.key"
                                class="mr-2 h-4 w-4 text-emerald-600"
                            />
                            <Copy v-else class="mr-2 h-4 w-4" />
                            Copy link
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
