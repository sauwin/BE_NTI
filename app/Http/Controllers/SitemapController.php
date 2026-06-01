<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\NewsArticle;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * @tags SEO Management
 * Endpoints for generating and caching dynamic XML sitemaps, encompassing static site routes, program listings, and news article content for search engine indexing optimization.
 */
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
            \App\Models\Program::query()
                ->select(['id', 'code', 'updated_at']) 
                ->orderBy('id')
                ->chunk(200, function ($programs) use ($sitemap) {
                    foreach ($programs as $program) {
                        $programIdentifier = isset($program->code) 
                            ? str_replace('program_', '', $program->code) 
                            : $program->id;

                        $sitemap->add(
                            Url::create("/programs/" . $programIdentifier)
                                ->setLastModificationDate($program->updated_at)
                                ->setPriority(0.9)
                                ->setChangeFrequency('weekly')
                        );
                    }
                });

            // ARTICLES (DYNAMIC)
            NewsArticle::query()
                ->select(['id', 'slug', 'updated_at'])
                ->orderBy('id')
                ->chunk(200, function ($newsArticles) use ($sitemap) {
                    foreach ($newsArticles as $newsArticle) {
                        $sitemap->add(
                            Url::create("/articles/" . (isset($newsArticle->slug) ? $newsArticle->slug : $newsArticle->id))
                                ->setLastModificationDate($newsArticle->updated_at)
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