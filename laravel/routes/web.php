<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\WorkController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\MediteController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\EditorController;

Route::get('/', function () {
    return auth()->check() ? view('pages.main') : redirect('/login');
})->middleware('auth');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// MAIN PAGE COMPONENTS

// Route pour déterminer la permission de l'utilisateur relativement à cette oeuvre
Route::get('/works/{id}/can-edit', [WorkController::class, 'canEdit'])->name('works.canEdit');

// work_selector
Route::get('/api/authors', [AuthorController::class, 'index'])->middleware('auth');
Route::post('/api/authors', [AuthorController::class, 'store'])->middleware('auth');
Route::post('/api/works', [WorkController::class, 'store'])->middleware('auth');
Route::get('/api/author/{authorId}/works', [AuthorController::class, 'getWorksByAuthor'])->middleware('auth');
Route::put('/api/authors/{id}', [AuthorController::class, 'update']);
Route::get('/api/works/{id}', [WorkController::class, 'show']);
Route::put('/api/works/{id}', [WorkController::class, 'update']);
Route::delete('/api/works/{id}', [WorkController::class, 'destroy']);
Route::delete('/api/authors/{id}', [AuthorController::class, 'destroy']);

// status
Route::get('/works/{workId}/status', [WorkController::class, 'getStatus'])->name('works.status.get');
Route::post('/works/{workId}/status', [WorkController::class, 'updateStatus'])->name('works.status.update');

// Description
Route::get('/works/{id}/description', [WorkController::class, 'getDescription'])->name('works.description');
Route::post('/works/{workId}/description', [WorkController::class, 'updateDescription']);

// Media
// Route::post('/api/works/{id}/media', [WorkController::class, 'storeMedia']);
// Route::get('/works/{id}/media', [WorkController::class, 'getMedia']);
// Route::delete('/api/works/{workId}/media/{mediaId}', [WorkController::class, 'destroyMedia']);

// Media (upload, lecture, suppression)
Route::post('/api/works/{work}/media',                 [MediaController::class, 'store'])
     ->middleware('auth')
     ->name('works.media.store');

Route::get('/works/{work}/media',                      [MediaController::class, 'index'])
     ->middleware('auth')
     ->name('works.media.index');

Route::delete('/api/works/{work}/media/{type}',        [MediaController::class, 'destroy'])
     ->middleware('auth')
     ->name('works.media.destroy');

// Versions
Route::post('/api/versions', [VersionController::class, 'store']);
Route::get('/api/versions', [VersionController::class, 'index']);
Route::put('/api/versions/{id}', [VersionController::class, 'update']);
Route::delete('/api/versions/{id}', [VersionController::class, 'destroy']);
Route::post('/api/facsimiles/publish', [VersionController::class, 'publishFacsimiles']);
Route::post('/api/versions/{version}/page-markers', [VersionController::class, 'applyPageMarkers']);
Route::post('/api/versions/{version}/lignes', [VersionController::class, 'uploadLignes']);
Route::get('/api/versions/{version}/lignes', [VersionController::class, 'downloadLignes'])->name('versions.lignes.download');
Route::get('/view-version/{id}', [VersionController::class, 'viewXmlClean']);

// Routes pour composant medite
Route::post('/api/run_medite', [MediteController::class, 'runMedite']);
Route::get('/api/task_status/{taskId}', [MediteController::class, 'taskStatus']);
Route::post('/api/comparisons', [MediteController::class, 'createComparison']);
Route::post('/save_comparison', [MediteController::class, 'saveComparison']);
Route::post('/api/comparisons/{comparison}/page-markers', [ComparisonController::class, 'applyPageMarkers']);

// Comparisons
Route::get('/comparisons/by-work', [ComparisonController::class, 'getByWork']);
Route::delete('/comparisons/{id}', [ComparisonController::class, 'destroy'])->name('comparisons.destroy');

// Editor
Route::get('comparison/{comparison}/editor', [EditorController::class, 'comparisonEditor'])->middleware('auth')->name('comparison.editor');
Route::put('comparison/{comparison}/editor', [EditorController::class, 'comparisonUpdate'])->middleware('auth')->name('comparison.editor.update');

// TEI to XHTML conversion
// Route::post('/api/run_xhtml', [XhtmlController::class, 'run']);
