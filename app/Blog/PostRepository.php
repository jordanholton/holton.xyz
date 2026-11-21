<?php

namespace App\Blog;

use App\Models\Entry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use RuntimeException;

/**
 * Reads flat-file posts from resources/posts/YYYY-MM-DD-N.md, mirroring
 * php.net's archive/entries/*.xml layout, merged with posts published
 * through the API (App\Models\Entry). Both share one id space.
 */
class PostRepository
{
    public const string FILENAME_PATTERN = '/^(\d{4}-\d{2}-\d{2})-(\d+)\.md$/';

    /** @var Collection<int, Post>|null */
    private ?Collection $posts = null;

    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * All posts, newest first.
     *
     * @return Collection<int, Post>
     */
    public function all(): Collection
    {
        return $this->posts ??= $this->load();
    }

    /**
     * @return Collection<int, Post>
     */
    public function latest(int $limit = 10): Collection
    {
        return $this->all()->take($limit)->values();
    }

    /**
     * @return Collection<int, Post>
     */
    public function forYear(int $year): Collection
    {
        return $this->all()->filter(fn (Post $post) => $post->year() === $year)->values();
    }

    /**
     * Years that have at least one post, newest first, with post counts.
     *
     * @return Collection<int, int>
     */
    public function years(): Collection
    {
        return $this->all()
            ->countBy(fn (Post $post) => $post->year())
            ->sortKeysDesc();
    }

    /**
     * The next free id for a given date, e.g. "2026-11-19-2" when "-1" exists.
     */
    public function nextIdFor(CarbonImmutable $date): string
    {
        $prefix = $date->format('Y-m-d');
        $sequence = 1;

        while ($this->exists("{$prefix}-{$sequence}")) {
            $sequence++;
        }

        return "{$prefix}-{$sequence}";
    }

    /**
     * Whether an id is taken by either a post file or an API entry.
     */
    public function exists(string $id): bool
    {
        return File::exists($this->path."/{$id}.md") || Entry::query()->whereKey($id)->exists();
    }

    /**
     * Forget the loaded posts so the next read picks up API writes.
     */
    public function flush(): void
    {
        $this->posts = null;
    }

    /**
     * @return Collection<int, Post>
     */
    private function load(): Collection
    {
        $converter = $this->converter();

        $files = File::isDirectory($this->path)
            ? collect(File::files($this->path))
                ->filter(fn (\SplFileInfo $file) => preg_match(self::FILENAME_PATTERN, $file->getFilename()) === 1)
                ->map(fn (\SplFileInfo $file) => $this->parse($file, $converter))
            : collect();

        $entries = Entry::query()->get()->map(fn (Entry $entry) => $this->fromEntry($entry, $converter));

        return $files->concat($entries)
            ->sortByDesc(fn (Post $post) => [$post->publishedAt->getTimestamp(), $post->id])
            ->values();
    }

    private function fromEntry(Entry $entry, MarkdownConverter $converter): Post
    {
        return new Post(
            id: $entry->id,
            title: $entry->title,
            publishedAt: $entry->published_at,
            html: $converter->convert($entry->body)->getContent(),
            tags: $entry->tags,
        );
    }

    private function parse(\SplFileInfo $file, MarkdownConverter $converter): Post
    {
        $id = $file->getBasename('.md');
        $date = substr($id, 0, 10);

        $result = $converter->convert(File::get($file->getPathname()));

        /** @var array<string, mixed> $meta */
        $meta = $result instanceof RenderedContentWithFrontMatter
            ? (array) $result->getFrontMatter()
            : [];

        if (! isset($meta['title']) || ! is_string($meta['title'])) {
            throw new RuntimeException("Post [{$id}] is missing a `title` in its front matter.");
        }

        $publishedAt = isset($meta['published'])
            ? CarbonImmutable::parse(is_int($meta['published']) ? '@'.$meta['published'] : (string) $meta['published'])
            : CarbonImmutable::parse($date);

        $tags = is_array($meta['tags'] ?? null) ? array_values(array_map('strval', $meta['tags'])) : [];

        return new Post(
            id: $id,
            title: $meta['title'],
            publishedAt: $publishedAt,
            html: $result->getContent(),
            tags: $tags,
        );
    }

    private function converter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            // Closures, because plain 'lazy' is callable (Laravel's global lazy() helper) and would be invoked.
            'default_attributes' => [
                Image::class => [
                    'loading' => fn () => 'lazy',
                    'decoding' => fn () => 'async',
                ],
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);
        $environment->addExtension(new DefaultAttributesExtension);

        return new MarkdownConverter($environment);
    }
}
