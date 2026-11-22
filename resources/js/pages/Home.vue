<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BlogLayout from '@/components/BlogLayout.vue';
import PostEntry from '@/components/PostEntry.vue';
import { index as postsIndex } from '@/routes/posts';
import type { Post, PostYear } from '@/types';

defineProps<{
    posts: Post[];
    years: PostYear[];
}>();
</script>

<template>
    <BlogLayout :years="years">
        <template #hero>
            <section
                class="border-b border-[#e3e3e0] bg-[#f8f8f6] dark:border-[#3E3E3A] dark:bg-[#121211]"
            >
                <div class="mx-auto w-full max-w-5xl px-4 py-16 text-center">
                    <h1
                        class="mx-auto max-w-2xl text-3xl font-semibold sm:text-4xl"
                    >
                        Software, infrastructure, and notes from the workbench.
                    </h1>
                </div>
            </section>
        </template>

        <div class="mb-6 flex items-baseline justify-between">
            <h2 class="text-lg font-semibold">Latest posts</h2>
            <Link
                :href="postsIndex()"
                class="text-sm text-[#706f6c] hover:underline dark:text-[#A1A09A]"
            >
                Archive
            </Link>
        </div>
        <div
            v-if="posts.length"
            class="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]"
        >
            <PostEntry v-for="post in posts" :key="post.id" :post="post" />
        </div>
        <p v-else class="text-[#706f6c] dark:text-[#A1A09A]">
            Nothing here yet.
        </p>
    </BlogLayout>
</template>
