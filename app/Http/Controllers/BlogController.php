<?php

namespace App\Http\Controllers;

use App\Blog\Post;
use App\Blog\PostRepository;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(private readonly PostRepository $posts) {}

    /**
     * Front page: the most recent posts across all years.
     */
    public function home(): Response
    {
        return Inertia::render('Home', [
            'posts' => $this->posts->latest()->map(fn (Post $post) => $post->toArray()),
            'years' => $this->years(),
        ]);
    }

    /**
     * /posts — the list of years.
     */
    public function index(): Response
    {
        return Inertia::render('posts/Index', [
            'years' => $this->years(),
        ]);
    }

    /**
     * /posts/{year}.php — every post published that year.
     */
    public function year(int $year): Response
    {
        $posts = $this->posts->forYear($year);

        abort_if($posts->isEmpty(), 404);

        return Inertia::render('posts/Year', [
            'year' => $year,
            'posts' => $posts->map(fn (Post $post) => $post->toArray()),
            'years' => $this->years(),
        ]);
    }

    /**
     * @return list<array{year: int, count: int, url: string}>
     */
    private function years(): array
    {
        return array_values($this->posts->years()
            ->map(fn (int $count, int $year) => [
                'year' => $year,
                'count' => $count,
                'url' => route('posts.year', ['year' => $year]),
            ])
            ->all());
    }
}
