<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => [
        LaravelLocalizationRoutes::class,
        LocaleSessionRedirect::class,
        LaravelLocalizationRedirectFilter::class,
    ],
], static function (): void {

    Route::get('/', static function () {
        return view('welcome');
    });

});
