<script setup lang="ts">
import type { Post } from '@/types';

defineProps<{
    post: Post;
}>();
</script>

<template>
    <article :id="post.id" class="scroll-mt-6 py-8 first:pt-0">
        <header class="mb-3">
            <h2 class="text-xl font-semibold">
                <a :href="post.url" class="hover:underline">{{ post.title }}</a>
            </h2>
            <time
                :datetime="post.published_at"
                class="text-sm text-[#706f6c] dark:text-[#A1A09A]"
            >
                {{ post.date }}
            </time>
        </header>
        <!-- Posts are trusted, author-written Markdown rendered server-side. -->
        <div class="post-content" v-html="post.html" />
    </article>
</template>
