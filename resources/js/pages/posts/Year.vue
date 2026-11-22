<script setup lang="ts">
import BlogLayout from '@/components/BlogLayout.vue';
import PostEntry from '@/components/PostEntry.vue';
import type { Post, PostYear } from '@/types';

defineProps<{
    year: number;
    posts: Post[];
    years: PostYear[];
}>();
</script>

<template>
    <BlogLayout :title="`${year}`" :years="years" :current-year="year">
        <h1 class="mb-8 text-2xl font-semibold">{{ year }}</h1>
        <div class="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
            <PostEntry v-for="post in posts" :key="post.id" :post="post" />
        </div>
    </BlogLayout>
</template>
