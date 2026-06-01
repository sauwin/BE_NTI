<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\NewsArticleTranslation;
use App\Http\Resources\NewsArticleTranslationResource;

/**
 * @tags Content Management
 * Endpoints for retrieving localized news article versions and facilitating the addition of translated content for specific article entries.
 */
class TranslationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return NewsArticleTranslationResource::collection(
            NewsArticleTranslation::latest()->get()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'language' => 'sometimes|in:sk,en',
            'article_id' => 'required|exists:news_articles,id',
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $translation = NewsArticleTranslation::create($validated);

        return response()->json(new NewsArticleTranslationResource($translation), 201);
    }
}
