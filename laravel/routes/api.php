<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\FacsimileController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\ComparisonController;

Route::post('/publish_xhtml', [PublishController::class, 'publish']);
Route::delete('/publish_xhtml/{comparison}', [PublishController::class, 'unpublish']);
Route::post('/upload_facsimiles', [FacsimileController::class, 'store']);
Route::get('/facsimiles', [FacsimileController::class, 'index']);
Route::get('/facsimiles/space', [FacsimileController::class, 'freeSpace']);
Route::get('/versions/{version}/page-markers/progress', [VersionController::class, 'pageMarkersProgress']);
Route::get('/versions/{version}/pagination-info', [VersionController::class, 'paginationInfo']);
Route::delete('/versions/{version}/lignes', [VersionController::class, 'cancelLignes']);
Route::delete('/versions/{version}/lignes/file', [VersionController::class, 'deleteLignesFile']);
Route::delete('/versions/{version}/facsimiles', [VersionController::class, 'cancelFacsimiles']);
Route::get('/versions/{version}/facsimiles/progress', [VersionController::class, 'facsimilesProgress']);
Route::get('/versions/{version}/comparisons', [VersionController::class, 'manifestComparisons']);
Route::put('/versions/{version}/manifests/{comparison}', [VersionController::class, 'updateManifestImages']);
Route::get('/comparisons/{comparison}/page-markers/progress', [ComparisonController::class, 'pageMarkersProgress']);
Route::post('/comparisons/{comparison}/page-markers/cancel', [ComparisonController::class, 'cancelPageMarkers']);
Route::post('/comparisons/{comparison}/page-markers/restore', [ComparisonController::class, 'restorePageMarkers']);

Route::post('/versions/{version}/pagination/from-pb', [VersionController::class, 'createPaginationFromPb']);
Route::get('/comparisons/{comparison}/manifests/{role}', [ComparisonController::class, 'showManifest'])
    ->where('role', 'source|target')
    ->name('comparisons.manifest');
Route::get('/comparisons/publication-counts', [ComparisonController::class, 'publicationCounts']);
