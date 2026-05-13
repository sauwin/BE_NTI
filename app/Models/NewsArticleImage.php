<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsArticleImage extends Model
{
    protected $fillable = [
        'image_path',
        'image_alt',
        'image_description',
        'type',
        'article_id',
    ];

    public function article()
    {
        return $this->belongsTo(NewsArticle::class, 'article_id');
    }
}
