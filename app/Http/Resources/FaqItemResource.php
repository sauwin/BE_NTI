<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FaqItemResource extends JsonResource
{
    public function toArray($request)
    {
        $translation = $this->translations->firstWhere('language', 'en');

        return [
            'id' => $this->id,
            'page_context' => $this->page_context,
            'order_position' => $this->order_position,
            'is_active' => $this->is_active,
            'question' => $translation?->question,
            'answer' => $translation?->answer,
            'translation_id' => $translation?->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
