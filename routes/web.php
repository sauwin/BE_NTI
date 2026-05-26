<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SitemapController;

Route::get('/', function () {
    return response()->json(['status' => 'NTI API running']);
});

Route::get('/sitemap.xml', [SitemapController::class, 'index']);