<?php

namespace App\Http\Controllers;

use App\Http\Resources\FaqItemResource;
use App\Models\FaqItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @tags System Configuration
 * Endpoints for managing frequently asked questions (FAQs), localized item text translations, and display sort order.
 */
class FaqItemController extends Controller
{
    /**
     * Select all FAQ Items
     */
    public function index(Request $request)
    {
        $query = FaqItem::with('translations')->where('is_active', true);

        if ($request->filled('page_context')) {
            $query->where('page_context', $request->query('page_context'));
        }

        $items = $query->orderBy('order_position')->get();

        return FaqItemResource::collection($items);
    }

    /**
     * Create new FAQ item
     */
    public function store(Request $request)
    {
        Gate::authorize('create', FaqItem::class);

        $validated = $request->validate([
            'page_context' => 'required|in:program_a,program_b,general',
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'is_active' => 'sometimes|boolean',
            'order_position' => 'sometimes|integer|min:0',
        ]);

        $faqItem = DB::transaction(function () use ($validated) {
            $orderPosition = $validated['order_position'] ?? (FaqItem::max('order_position') ?? 0) + 1;

            $item = FaqItem::create([
                'page_context' => $validated['page_context'],
                'order_position' => $orderPosition,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $item->translations()->create([
                'language' => 'en',
                'question' => $validated['question'],
                'answer' => $validated['answer'],
            ]);

            return $item;
        });

        return new FaqItemResource($faqItem->load('translations'));
    }

    /**
     * Update FAQ item
     */
    public function update(Request $request, FaqItem $faqItem)
    {
        Gate::authorize('update', $faqItem);

        $validated = $request->validate([
            'page_context' => 'sometimes|in:program_a,program_b,general',
            'question' => 'sometimes|string|max:255',
            'answer' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            'order_position' => 'sometimes|integer|min:0',
        ]);

        DB::transaction(function () use ($faqItem, $validated) {
            $faqItem->update(array_filter([
                'page_context' => $validated['page_context'] ?? null,
                'order_position' => isset($validated['order_position']) ? $validated['order_position'] : null,
                'is_active' => $validated['is_active'] ?? null,
            ], fn ($value) => $value !== null));

            $translation = $faqItem->translations()->firstWhere('language', 'en');

            if ($translation) {
                $translation->update(array_filter([
                    'question' => $validated['question'] ?? null,
                    'answer' => $validated['answer'] ?? null,
                ], fn ($value) => $value !== null));
            } else {
                $faqItem->translations()->create([
                    'language' => 'en',
                    'question' => $validated['question'] ?? '',
                    'answer' => $validated['answer'] ?? '',
                ]);
            }
        });

        return new FaqItemResource($faqItem->load('translations'));
    }

    /**
     * Delete FAQ item
     */
    public function destroy(FaqItem $faqItem)
    {
        Gate::authorize('forceDelete', $faqItem);

        $faqItem->delete();

        return response()->noContent();
    }

    public function storeTranslation(Request $request, FaqItem $faqItem)
    {
        Gate::authorize('update', $faqItem);

        $validated = $request->validate([
            'language' => 'required|in:sk,en',
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
        ]);

        $existing = $faqItem->translations()->where('language', $validated['language'])->first();
        if ($existing) {
            return response()->json(['message' => 'Translation already exists. Use PUT to update.'], 409);
        }

        $translation = $faqItem->translations()->create($validated);

        return response()->json($translation, 201);
    }

    /**
     * Update translation for FAQ item
     */
    public function updateTranslation(Request $request, FaqItem $faqItem, string $language)
    {
        Gate::authorize('update', $faqItem);

        $validated = $request->validate([
            'question' => 'sometimes|string|max:255',
            'answer' => 'sometimes|string',
        ]);

        $translation = $faqItem->translations()->where('language', $language)->firstOrFail();
        $translation->update($validated);

        return response()->json($translation);
    }


}
