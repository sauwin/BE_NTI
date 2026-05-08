<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsArticleImageResource extends JsonResource
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

            'image_path' => asset(
                'storage/' . $this->image_path
            ),

            'image_alt' => $this->image_alt,

            'image_description' => $this->image_description,

            'type' => $this->type,

            'article_id' => $this->article_id,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
