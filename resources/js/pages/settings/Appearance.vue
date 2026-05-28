<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/common/AppearanceTabs.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Aparência',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Aparência" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Aparência" description="Personalize a aparência da interface" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
