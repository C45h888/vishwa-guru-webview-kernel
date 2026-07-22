<?php

declare(strict_types=1);

use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\CmsMedia\ShowController as CmsMediaShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/media/{id}', CmsMediaShowController::class)
    ->where('id', '[A-Za-z0-9]+')
    ->name('cms.public-media.show');
