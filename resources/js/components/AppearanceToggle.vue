<script setup lang="ts">
import type { Appearance } from '@/composables/useAppearance';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const options: { value: Appearance; label: string; icon: string }[] = [
    {
        value: 'system',
        label: 'System',
        icon: 'M4 5h16v11H4zM8 20h8M12 16v4',
    },
    {
        value: 'light',
        label: 'Light',
        icon: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
    },
    {
        value: 'dark',
        label: 'Dark',
        icon: 'M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z',
    },
];
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Color theme"
        class="inline-flex gap-0.5 rounded-full border border-[#e3e3e0] p-0.5 dark:border-[#3E3E3A]"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="appearance === option.value"
            :title="option.label"
            class="rounded-full p-1.5 text-[#706f6c] transition-colors hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
            :class="{
                'bg-[#f3f3f1] text-[#1b1b18] dark:bg-[#1f1f1d] dark:text-[#EDEDEC]':
                    appearance === option.value,
            }"
            @click="updateAppearance(option.value)"
        >
            <span class="sr-only">{{ option.label }}</span>
            <svg
                viewBox="0 0 24 24"
                class="size-4"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path :d="option.icon" />
            </svg>
        </button>
    </div>
</template>
