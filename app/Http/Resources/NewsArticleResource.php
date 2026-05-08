<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\NewsArticleImageResource;
use App\Http\Resources\NewsArticleTranslationResource;

class NewsArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'slug' => $this->slug,

            'is_published' => $this->is_published,

            'published_at' => $this->published_at,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

            'translations' =>
                NewsArticleTranslationResource::collection(
                    $this->whenLoaded('translations')
                ),

            'cover_image' =>
                new NewsArticleImageResource(
                    $this->whenLoaded('coverImage')
                ),

            'content_images' =>
                NewsArticleImageResource::collection(
                    $this->whenLoaded('contentImages')
                ),
        ];
    }
}
