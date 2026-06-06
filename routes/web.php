<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'NTI API running']);
});

Route::get('/sitemap.xml', [SitemapController::class, 'index']);
