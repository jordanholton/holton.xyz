<?php

namespace Tests\Feature;

use App\Blog\PostRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/posts');
        File::deleteDirectory($this->path);
        File::ensureDirectoryExists($this->path);

        $this->app->singleton(PostRepository::class, fn () => new PostRepository($this->path));

        $this->writePost('2026-11-19-1', 'First of the day', '2026-11-19 09:00:00');
        $this->writePost('2026-11-19-2', 'Second of the day', '2026-11-19 15:00:00');
        $this->writePost('2025-06-01-1', 'Last year', '2025-06-01 12:00:00');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_home_lists_latest_posts_newest_first(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('posts', 3)
                ->where('posts.0.id', '2026-11-19-2')
                ->where('posts.2.id', '2025-06-01-1'));
    }

    public function test_posts_index_lists_years_with_counts(): void
    {
        $this->get('/posts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('posts/Index')
                ->where('years.0', ['year' => 2026, 'count' => 2, 'url' => url('/posts/2026.php')])
                ->where('years.1.year', 2025));
    }

    public function test_year_page_shows_only_that_years_posts(): void
    {
        $this->get('/posts/2026.php')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('posts/Year')
                ->where('year', 2026)
                ->has('posts', 2)
                ->where('posts.0.url', url('/posts/2026.php').'#2026-11-19-2')
                ->where('posts.0.html', "<p>Body of Second of the day.</p>\n"));
    }

    public function test_post_images_are_lazy_loaded(): void
    {
        File::put("{$this->path}/2026-11-20-1.md", "---\ntitle: Photo\n---\n\n![Kids](/images/2026/kids.jpg)\n");

        $this->get('/posts/2026.php')
            ->assertInertia(fn (Assert $page) => $page->where(
                'posts.0.html',
                '<p><img loading="lazy" decoding="async" src="/images/2026/kids.jpg" alt="Kids" /></p>'."\n",
            ));
    }

    public function test_year_without_posts_is_not_found(): void
    {
        $this->get('/posts/1999.php')->assertNotFound();
        $this->get('/posts/abcd.php')->assertNotFound();
        $this->get('/2026.php')->assertNotFound();
    }

    public function test_new_post_command_picks_next_sequence(): void
    {
        $this->artisan('post:new', ['title' => "Don't \"panic\" in prod", '--date' => '2026-11-19 18:00'])
            ->assertSuccessful();

        $this->assertFileExists("{$this->path}/2026-11-19-3.md");
        $this->assertStringStartsWith("---\ntitle: 'Don''t \"panic\" in prod'\n", File::get("{$this->path}/2026-11-19-3.md"));
        $this->assertSame("Don't \"panic\" in prod", app(PostRepository::class)->forYear(2026)->first()?->title);
    }

    private function writePost(string $id, string $title, string $published): void
    {
        File::put("{$this->path}/{$id}.md", <<<MD
            ---
            title: "{$title}"
            published: {$published}
            ---

            Body of {$title}.
            MD);
    }
}
