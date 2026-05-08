<?php

namespace App\Services;

use App\Models\NewsArticleImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ArticleImageService
{
    public function createCover(
        UploadedFile $file,
        int $articleId
    ): NewsArticleImage {

        $path = $file->store('images', 'public');

        return NewsArticleImage::create([
            'article_id' => $articleId,
            'image_path' => $path,
            'type' => 'cover',
        ]);
    }

    public function replaceCover(
      UploadedFile $file,
      NewsArticleImage $image
    ): NewsArticleImage {

        Storage::disk('public')
            ->delete($image->image_path);

        $path = $file->store('images', 'public');

        $image->update([
            'image_path' => $path
        ]);

        return $image;
    }

    public function deleteCover(
        NewsArticleImage $image
    ): void {

        Storage::disk('public')
            ->delete($image->image_path);

        $image->delete();
    }
}