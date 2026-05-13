<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsArticleResource;
use App\Models\NewsArticle;
use App\Services\ArticleImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = NewsArticle::with(['translations', 'coverImage'])
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(10);

        return NewsArticleResource::collection($posts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ArticleImageService $imageService)
    {
        $validated = $request->validate([
            'image' => 'required|file|max:2048|mimes:png,jpg,jpeg,webp',

            'slug' => 'required|string|max:255|unique:news_articles,slug',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',

            'translations' => 'required|array|min:1',
            'translations.*.language' => 'sometimes|in:sk,en',
            'translations.*.title' => 'required|string|max:255',
            'translations.*.excerpt' => 'required|string|max:255',
            'translations.*.content' => 'required|string',
        ]);

        // $validated['author_id'] = $request->user()->id;
        $validated['is_published'] = 1;
        $validated['published_at'] = $validated['published_at'] ?? now();
        $validated['author_id'] = 1;

        foreach ($request->translations as $lang => $data) {
            if (! in_array($lang, ['en', 'sk'])) {
                return response()->json([
                    'error' => "Invalid language: $lang",
                ], 422);
            }
        }

        $article = DB::transaction(function () use (
            $validated,
            $request,
            $imageService
        ) {

            $article = NewsArticle::create($validated);

            foreach ($validated['translations'] as $lang => $translationData) {
                $article->translations()->create([
                    'language' => $lang,
                    ...$translationData,
                ]);
            }

            if ($request->hasFile('image')) {

                $imageService->createCover(
                    $request->file('image'),
                    $article->id
                );
            }

            return $article;
        });

        return new NewsArticleResource(
            $article->load([
                'translations',
                'coverImage',
            ])
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(NewsArticle $article)
    {
        return new NewsArticleResource($article->load(['author', 'translations']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, NewsArticle $article, ArticleImageService $imageService)
    {
        $validated = $request->validate([
            'image' => 'sometimes|file|max:2048|mimes:png,jpg,jpeg,webp',

            'slug' => 'sometimes|string|max:255|unique:news_articles,slug,'.$article->id,

            'translations' => 'sometimes|array',
            'translations.*.id' => 'sometimes|exists:news_article_translations,id',
            'translations.*.language' => 'sometimes|in:sk,en',
            'translations.*.title' => 'required|string|max:255',
            'translations.*.excerpt' => 'required|string|max:255',
            'translations.*.content' => 'required|string',
        ]);

        DB::transaction(function () use (
            $validated,
            $article,
            $request,
            $imageService
        ) {

            $article->update($validated);

            $existingIds = $article->translations()->pluck('id')->toArray();
            $incomingIds = [];

            foreach ($validated['translations'] ?? [] as $data) {

                if (isset($data['id'])) {
                    $translation = $article->translations()->find($data['id']);
                    $translation?->update($data);

                    $incomingIds[] = $data['id'];
                } else {
                    $new = $article->translations()->create($data);
                    $incomingIds[] = $new->id;
                }
            }

            $toDelete = array_diff($existingIds, $incomingIds);
            $article->translations()->whereIn('id', $toDelete)->delete();

            if ($request->hasFile('image')) {

                if ($article->coverImage) {

                    $imageService->replaceCover(
                        $request->file('image'),
                        $article->coverImage
                    );

                } else {

                    $imageService->createCover(
                        $request->file('image'),
                        $article->id
                    );
                }
            }
        });

        return response()->json($article->load('translations'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(NewsArticle $article, ArticleImageService $imageService)
    {
        DB::transaction(function () use (
            $article,
            $imageService
        ) {
            if ($article->coverImage) {
                $imageService->deleteCover($article->coverImage);
            }

            $article->delete();
        });

        return response()->noContent();
    }
}
