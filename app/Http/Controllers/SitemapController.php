<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{

    /**
     * SEO logic
     */
    public function index()
    {
        $sitemapXml = Cache::remember('sitemap.xml', 3600, function () {

            $sitemap = Sitemap::create()

                // STATIC PAGES
                ->add(
                    Url::create('/')
                        ->setPriority(1.0)
                        ->setChangeFrequency('weekly')
                )
                ->add(
                    Url::create('/about')
                        ->setPriority(0.8)
                        ->setChangeFrequency('monthly')
                )
                ->add(
                    Url::create('/faq')
                        ->setPriority(0.8)
                        ->setChangeFrequency('monthly')
                );

            // PROGRAMS (DYNAMIC)
            Program::query()
                ->select(['id', 'slug', 'updated_at'])
                ->orderBy('id')
                ->chunk(200, function ($programs) use ($sitemap) {
                    foreach ($programs as $program) {
                        $sitemap->add(
                            Url::create("/programs/" . (isset($program->slug) ? $program->slug : $program->id))
                                ->setLastModificationDate($program->updated_at)
                                ->setPriority(0.9)
                                ->setChangeFrequency('weekly')
                        );
                    }
                });

            // ARTICLES (DYNAMIC)
            Article::query()
                ->select(['id', 'slug', 'updated_at'])
                ->orderBy('id')
                ->chunk(200, function ($articles) use ($sitemap) {
                    foreach ($articles as $article) {
                        $sitemap->add(
                            Url::create("/articles/" . (isset($article->slug) ? $article->slug : $article->id))
                                ->setLastModificationDate($article->updated_at)
                                ->setPriority(0.8)
                                ->setChangeFrequency('monthly')
                        );
                    }
                });

            return $sitemap->render();
        });

        return response($sitemapXml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}