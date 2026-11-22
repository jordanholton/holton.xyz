<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BlogLayout from '@/components/BlogLayout.vue';
import type { PostYear } from '@/types';

defineProps<{
    years: PostYear[];
}>();
</script>

<template>
    <BlogLayout title="All posts" :years="years">
        <h1 class="mb-6 text-2xl font-semibold">All posts</h1>
        <ul class="space-y-2">
            <li v-for="item in years" :key="item.year">
                <Link :href="item.url" class="font-medium hover:underline">
                    {{ item.year }}
                </Link>
                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    &middot; {{ item.count }}
                    {{ item.count === 1 ? 'post' : 'posts' }}
                </span>
            </li>
        </ul>
    </BlogLayout>
</template>
