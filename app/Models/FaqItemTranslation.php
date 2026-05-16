<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqItemTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'faq_item_id',
        'language',
        'question',
        'answer',
    ];

    public function faqItem()
    {
        return $this->belongsTo(FaqItem::class, 'faq_item_id');
    }
}
