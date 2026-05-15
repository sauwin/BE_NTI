<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsArticleTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id', 'language', 'title', 'excerpt', 'content',
    ];

    public function article()
    {
        return $this->belongsTo(NewsArticle::class, 'article_id');
    }
}
