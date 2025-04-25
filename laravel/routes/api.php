<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XhtmlController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\FacsimileController;

Route::post('/run_xhtml', [XhtmlController::class, 'run']);
Route::post('/publish_xhtml', [PublishController::class, 'publish']);
Route::post('/upload_facsimiles', [FacsimileController::class, 'store']);
Route::get('/facsimiles', [FacsimileController::class, 'index']);