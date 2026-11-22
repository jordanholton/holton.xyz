<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import { home } from '@/routes';
import { index as postsIndex } from '@/routes/posts';
import type { PostYear } from '@/types';

defineProps<{
    title?: string;
    years: PostYear[];
    currentYear?: number;
}>();

const copyrightYear = new Date().getFullYear();
</script>

<template>
    <Head :title="title" />
    <div
        class="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
    >
        <header class="border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
            <nav
                class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-4"
            >
                <Link :href="home()" class="font-semibold">holton.xyz</Link>
                <div class="flex items-center gap-4">
                    <Link
                        :href="postsIndex()"
                        class="text-sm text-[#706f6c] hover:underline dark:text-[#A1A09A]"
                    >
                        Blog
                    </Link>
                    <AppearanceToggle />
                </div>
            </nav>
        </header>

        <slot name="hero" />

        <div
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-10 px-4 py-10 md:flex-row"
        >
            <main class="min-w-0 flex-1">
                <slot />
            </main>

            <aside class="md:w-48 md:shrink-0">
                <h2
                    class="mb-3 text-xs font-semibold tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]"
                >
                    Posts by year
                </h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="item in years" :key="item.year">
                        <Link
                            :href="item.url"
                            class="hover:underline"
                            :class="{
                                'font-semibold': item.year === currentYear,
                            }"
                        >
                            {{ item.year }}
                        </Link>
                        <span class="text-[#706f6c] dark:text-[#A1A09A]">
                            ({{ item.count }})
                        </span>
                    </li>
                </ul>
            </aside>
        </div>

        <footer class="border-t border-[#e3e3e0] dark:border-[#3E3E3A]">
            <div
                class="mx-auto w-full max-w-5xl px-4 py-6 text-sm text-[#706f6c] dark:text-[#A1A09A]"
            >
                &copy; {{ copyrightYear }} Holton LLC
            </div>
        </footer>
    </div>
</template>
