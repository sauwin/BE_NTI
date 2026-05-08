<?php

namespace App\Models;

use App\Models\User;
use App\Models\NewsArticleTranslation;
use App\Models\NewsArticleImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'author_id', 'is_published', 'published_at'
    ];
    
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function translations()
    {
        return $this->hasMany(NewsArticleTranslation::class, 'article_id');
    }

    public function coverImage()
    {
        return $this->hasOne(NewsArticleImage::class, 'article_id')
            ->where('type', 'cover');
    }

    public function contentImages()
    {
        return $this->hasMany(NewsArticleImage::class, 'article_id')
            ->where('type', 'inline');
    }

    public function images()
    {
        return $this->hasMany(NewsArticleImage::class, 'article_id');
    }

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];
}
