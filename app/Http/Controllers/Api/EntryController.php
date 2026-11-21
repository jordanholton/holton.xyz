<?php

namespace App\Http\Controllers\Api;

use App\Blog\PostRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEntryRequest;
use App\Http\Requests\Api\UpdateEntryRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * CRUD for posts published through the API. Each user only sees and
 * manages their own entries; flat-file posts are managed in git.
 */
class EntryController extends Controller
{
    public function __construct(private readonly PostRepository $posts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = $this->user($request)->entries()
            ->latest('published_at')
            ->paginate(20);

        return EntryResource::collection($entries);
    }

    public function store(StoreEntryRequest $request): JsonResponse
    {
        $publishedAt = $request->filled('published_at')
            ? CarbonImmutable::parse($request->string('published_at')->toString())
            : CarbonImmutable::now();

        $entry = $this->user($request)->entries()->create([
            'id' => $this->posts->nextIdFor($publishedAt),
            'title' => $request->string('title')->toString(),
            'body' => $request->string('body')->toString(),
            'tags' => $request->tags(),
            'published_at' => $publishedAt,
        ]);

        $this->posts->flush();

        return (new EntryResource($entry))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): EntryResource
    {
        return new EntryResource($this->find($request, $id));
    }

    public function update(UpdateEntryRequest $request, string $id): EntryResource
    {
        $entry = $this->find($request, $id);

        $entry->fill($request->safe()->only(['title', 'body', 'published_at']));

        if ($request->has('tags')) {
            $entry->tags = $request->tags();
        }

        $entry->save();
        $this->posts->flush();

        return new EntryResource($entry);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->find($request, $id)->delete();
        $this->posts->flush();

        return response()->noContent();
    }

    private function find(Request $request, string $id): Entry
    {
        return $this->user($request)->entries()->findOrFail($id);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
