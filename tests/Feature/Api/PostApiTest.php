<?php

namespace Tests\Feature\Api;

use App\Blog\PostRepository;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_device_can_exchange_credentials_for_a_token(): void
    {
        $user = User::factory()->create(['email' => 'jordan@example.com']);

        $token = $this->postJson('/api/v1/tokens', [
            'email' => 'jordan@example.com',
            'password' => 'password',
            'device_name' => 'iPhone',
        ])
            ->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'jordan@example.com')
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_signing_in_again_from_a_device_replaces_its_token(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'password'];

        $this->postJson('/api/v1/tokens', [...$credentials, 'device_name' => 'iPhone'])->assertCreated();
        $this->postJson('/api/v1/tokens', [...$credentials, 'device_name' => 'iPhone'])->assertCreated();
        $this->postJson('/api/v1/tokens', [...$credentials, 'device_name' => 'iPad'])->assertCreated();

        $this->assertSame(['iPad', 'iPhone'], $user->tokens()->orderBy('name')->pluck('name')->all());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'nope',
            'device_name' => 'iPhone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_token_requests_are_rate_limited(): void
    {
        $user = User::factory()->create();
        $attempt = ['email' => $user->email, 'password' => 'nope', 'device_name' => 'iPhone'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/tokens', $attempt)->assertUnprocessable();
        }

        $this->postJson('/api/v1/tokens', $attempt)->assertTooManyRequests();
    }

    public function test_api_requires_a_token_and_answers_with_json(): void
    {
        // No Accept header, as a bare HTTP client would send.
        $this->get('/api/v1/posts')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
        $this->postJson('/api/v1/posts', ['title' => 'x', 'body' => 'y'])->assertUnauthorized();
    }

    public function test_read_only_token_cannot_publish(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['posts:read']);

        $this->getJson('/api/v1/posts')->assertOk();
        $this->postJson('/api/v1/posts', ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_published_post_appears_on_the_site(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['posts:read', 'posts:write']);

        $this->postJson('/api/v1/posts', [
            'title' => 'Shipping from my phone',
            'body' => "Posted **remotely**.\n\n![Rack](/images/2026/rack.jpg)",
            'tags' => ['mobile', 'api'],
            'published_at' => '2026-11-20 08:30:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', '2026-11-20-1')
            ->assertJsonPath('data.tags', ['mobile', 'api'])
            ->assertJsonPath('data.url', url('/posts/2026.php').'#2026-11-20-1');

        $this->get('/posts/2026.php')
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', '2026-11-20-1')
                ->where('posts.0.title', 'Shipping from my phone')
                ->where('posts.0.html', '<p>Posted <strong>remotely</strong>.</p>'."\n".'<p><img loading="lazy" decoding="async" src="/images/2026/rack.jpg" alt="Rack" /></p>'."\n"));
    }

    public function test_ids_continue_the_days_sequence_across_files_and_api(): void
    {
        File::put("{$this->path}/2026-11-20-1.md", "---\ntitle: From git\n---\n\nBody.\n");
        Sanctum::actingAs(User::factory()->create(), ['posts:read', 'posts:write']);

        $this->postJson('/api/v1/posts', ['title' => 'From API', 'body' => 'Body.', 'published_at' => '2026-11-20 12:00'])
            ->assertJsonPath('data.id', '2026-11-20-2');

        $this->artisan('post:new', ['title' => 'From git again', '--date' => '2026-11-20 13:00'])->assertSuccessful();
        $this->assertFileExists("{$this->path}/2026-11-20-3.md");
    }

    public function test_post_defaults_to_now_and_validates_input(): void
    {
        $this->travelTo('2026-11-20 09:15:00');
        Sanctum::actingAs(User::factory()->create(), ['posts:read', 'posts:write']);

        $this->postJson('/api/v1/posts', ['title' => 'Now', 'body' => 'Body.'])
            ->assertCreated()
            ->assertJsonPath('data.id', '2026-11-20-1')
            ->assertJsonPath('data.tags', []);

        $this->postJson('/api/v1/posts', ['tags' => 'not-a-list'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body', 'tags']);
    }

    public function test_author_can_update_and_delete_their_post(): void
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->for($user)->create(['id' => '2026-11-20-1', 'published_at' => '2026-11-20 08:00']);
        Sanctum::actingAs($user, ['posts:read', 'posts:write']);

        $this->getJson('/api/v1/posts')->assertOk()->assertJsonPath('data.0.id', $entry->id);

        $this->patchJson("/api/v1/posts/{$entry->id}", ['title' => 'Edited', 'tags' => ['fixed']])
            ->assertOk()
            ->assertJsonPath('data.title', 'Edited')
            ->assertJsonPath('data.body', $entry->body)
            ->assertJsonPath('data.tags', ['fixed']);

        $this->get('/posts/2026.php')->assertInertia(fn (Assert $page) => $page->where('posts.0.title', 'Edited'));

        $this->deleteJson("/api/v1/posts/{$entry->id}")->assertNoContent();
        $this->assertModelMissing($entry);
        $this->get('/posts/2026.php')->assertNotFound();
    }

    public function test_users_cannot_touch_each_others_posts(): void
    {
        $entry = Entry::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['posts:read', 'posts:write']);

        $this->getJson('/api/v1/posts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/posts/{$entry->id}")->assertNotFound();
        $this->patchJson("/api/v1/posts/{$entry->id}", ['title' => 'Mine now'])->assertNotFound();
        $this->deleteJson("/api/v1/posts/{$entry->id}")->assertNotFound();
        $this->assertModelExists($entry);
    }

    public function test_signing_out_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone', ['posts:read'])->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_user_create_command_makes_an_api_account(): void
    {
        $this->artisan('user:create', ['name' => 'Jordan', 'email' => 'jordan@example.com'])
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertSuccessful();

        $this->postJson('/api/v1/tokens', [
            'email' => 'jordan@example.com',
            'password' => 'correct-horse-battery',
            'device_name' => 'iPhone',
        ])->assertCreated();
    }
}
