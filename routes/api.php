<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Route;

Route::get('/quote', function () {
    Artisan::command('inspire', function () {
        $this->comment(Inspiring::quote());
    })->purpose('Display an inspiring quote');
});
