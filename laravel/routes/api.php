<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\FacsimileController;

Route::post('/publish_xhtml', [PublishController::class, 'publish']);
Route::delete('/publish_xhtml/{comparison}', [PublishController::class, 'unpublish']);
Route::post('/upload_facsimiles', [FacsimileController::class, 'store']);
Route::get('/facsimiles', [FacsimileController::class, 'index']);
