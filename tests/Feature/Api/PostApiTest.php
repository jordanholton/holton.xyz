<?php

namespace Tests\Feature\Api;

use App\Blog\PostRepository;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\IssuesOidcTokens;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use IssuesOidcTokens, RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRealm();

        $this->path = storage_path('framework/testing/api-posts');
        File::deleteDirectory($this->path);
        File::ensureDirectoryExists($this->path);

        $this->app->singleton(PostRepository::class, fn () => new PostRepository($this->path));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_published_post_appears_on_the_site(): void
    {
        $this->withToken($this->realmToken())->postJson('/api/v1/posts', [
            'title' => 'Shipping from my phone',
            'body' => "Posted **remotely**.\n\n![Rack](/images/2026/rack.jpg)",
            'tags' => ['mobile', 'api'],
            'published_at' => '2026-11-20 08:30:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', '2026-11-20-1')
            ->assertJsonPath('data.tags', ['mobile', 'api'])
            ->assertJsonPath('data.url', url('/posts/2026.php').'#2026-11-20-1');

        $this->assertSame('user-123', Entry::query()->sole()->user->oidc_subject);

        $this->get('/posts/2026.php')
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', '2026-11-20-1')
                ->where('posts.0.title', 'Shipping from my phone')
                ->where('posts.0.html', '<p>Posted <strong>remotely</strong>.</p>'."\n".'<p><img loading="lazy" decoding="async" src="/images/2026/rack.jpg" alt="Rack" /></p>'."\n"));
    }

    public function test_ids_continue_the_days_sequence_across_files_and_api(): void
    {
        File::put("{$this->path}/2026-11-20-1.md", "---\ntitle: From git\n---\n\nBody.\n");

        $this->withToken($this->realmToken())
            ->postJson('/api/v1/posts', ['title' => 'From API', 'body' => 'Body.', 'published_at' => '2026-11-20 12:00'])
            ->assertJsonPath('data.id', '2026-11-20-2');

        $this->artisan('post:new', ['title' => 'From git again', '--date' => '2026-11-20 13:00'])->assertSuccessful();
        $this->assertFileExists("{$this->path}/2026-11-20-3.md");
    }

    public function test_post_defaults_to_now_and_validates_input(): void
    {
        $this->travelTo('2026-11-20 21:15:00');
        $token = $this->realmToken();

        // Late evening Central time is still the 20th.
        $this->withToken($token)->postJson('/api/v1/posts', ['title' => 'Now', 'body' => 'Body.'])
            ->assertCreated()
            ->assertJsonPath('data.id', '2026-11-20-1')
            ->assertJsonPath('data.tags', []);

        $this->withToken($token)->postJson('/api/v1/posts', ['tags' => 'not-a-list'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body', 'tags']);
    }

    public function test_author_can_update_and_delete_their_post(): void
    {
        $user = User::factory()->create(['oidc_subject' => 'user-123', 'email' => 'jordan@example.com']);
        $entry = Entry::factory()->for($user)->create(['id' => '2026-11-20-1', 'published_at' => '2026-11-20 08:00']);
        $token = $this->realmToken();

        $this->withToken($token)->getJson('/api/v1/posts')->assertOk()->assertJsonPath('data.0.id', $entry->id);

        $this->withToken($token)->patchJson("/api/v1/posts/{$entry->id}", ['title' => 'Edited', 'tags' => ['fixed']])
            ->assertOk()
            ->assertJsonPath('data.title', 'Edited')
            ->assertJsonPath('data.body', $entry->body)
            ->assertJsonPath('data.tags', ['fixed']);

        $this->get('/posts/2026.php')->assertInertia(fn (Assert $page) => $page->where('posts.0.title', 'Edited'));

        $this->withToken($token)->deleteJson("/api/v1/posts/{$entry->id}")->assertNoContent();
        $this->assertModelMissing($entry);
        $this->get('/posts/2026.php')->assertNotFound();
    }

    public function test_users_cannot_touch_each_others_posts(): void
    {
        $entry = Entry::factory()->create();
        $token = $this->realmToken();

        $this->withToken($token)->getJson('/api/v1/posts')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson("/api/v1/posts/{$entry->id}")->assertNotFound();
        $this->withToken($token)->patchJson("/api/v1/posts/{$entry->id}", ['title' => 'Mine now'])->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/posts/{$entry->id}")->assertNotFound();
        $this->assertModelExists($entry);
    }
}
