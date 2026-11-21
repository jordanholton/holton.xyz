<?php

namespace App\Blog;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A single blog post, identified php.net-style as "YYYY-MM-DD-N".
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Post implements Arrayable
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public CarbonImmutable $publishedAt,
        public string $html,
        public array $tags = [],
    ) {}

    public function year(): int
    {
        return $this->publishedAt->year;
    }

    /**
     * Permalink: /posts/2026.php#2026-11-19-1
     */
    public function url(): string
    {
        return route('posts.year', ['year' => $this->year()]).'#'.$this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'published_at' => $this->publishedAt->toIso8601String(),
            'date' => $this->publishedAt->format('d M Y'),
            'url' => $this->url(),
            'html' => $this->html,
            'tags' => $this->tags,
        ];
    }
}
