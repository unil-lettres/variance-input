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
use App\Http\Controllers\HealthController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\TaskMonitorController;
use App\Http\Controllers\AccountController;
use App\Models\Author;
use App\Models\Work;

Route::get('/health', [HealthController::class, 'index']);

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->to(admin_path('login'));
    }

    return view('pages.main', [
        'initialSelection' => null,
    ]);
})->middleware('auth');

Route::get('/select/{authorSlug}/{workSlug?}', function (string $authorSlug, ?string $workSlug = null) {
    if (! auth()->check()) {
        return redirect()->to(admin_path('login'));
    }

    $author = Author::where('folder', $authorSlug)->firstOrFail();

    $work = null;
    if ($workSlug !== null) {
        $work = Work::where('folder', $workSlug)
            ->where('author_id', $author->id)
            ->firstOrFail();
    }

    return view('pages.main', [
        'initialSelection' => [
            'authorId'   => $author->id,
            'authorSlug' => $author->folder,
            'workId'     => $work?->id,
            'workSlug'   => $work?->folder,
        ],
    ]);
})->where([
    'authorSlug' => '[A-Za-z0-9_-]+',
    'workSlug'   => '[A-Za-z0-9_-]+',
])->middleware('auth')->name('admin.select');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('admin.users.store');
    Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/tasks', [TaskMonitorController::class, 'index'])->name('admin.tasks.index');
    Route::get('/health/report', [HealthController::class, 'page'])->name('admin.health.report');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/account/password', [AccountController::class, 'editPassword'])->name('account.password.edit');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
});

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
Route::get('/api/versions/{version}/text-length', [VersionController::class, 'textLength'])->middleware('auth');
Route::put('/api/versions/{id}', [VersionController::class, 'update']);
Route::patch('/api/versions/{version}/pagination/done', [VersionController::class, 'togglePaginationDone']);
Route::delete('/api/versions/{id}', [VersionController::class, 'destroy']);
Route::post('/api/facsimiles/publish', [VersionController::class, 'publishFacsimiles']);
Route::post('/api/versions/{version}/page-markers', [VersionController::class, 'applyPageMarkers']);
Route::post('/api/versions/{version}/lignes', [VersionController::class, 'uploadLignes']);
Route::get('/api/versions/{version}/lignes', [VersionController::class, 'downloadLignes'])->name('versions.lignes.download');
Route::get('/view-version/{id}', [VersionController::class, 'viewXmlClean']);
Route::get('/versions/{version}/download', [VersionController::class, 'downloadText'])->name('versions.text.download');
Route::get('/versions/{version}/download-xml', [VersionController::class, 'downloadXml'])->name('versions.xml.download');
Route::post('/versions/{version}/facsimiles/toggle-ignored', [VersionController::class, 'toggleIgnoredPage'])->middleware('auth');

// Routes pour composant medite
Route::post('/api/run_medite', [MediteController::class, 'runMedite']);
Route::get('/api/task_status/{taskId}', [MediteController::class, 'taskStatus']);
Route::post('/api/comparisons', [MediteController::class, 'createComparison']);
Route::post('/save_comparison', [MediteController::class, 'saveComparison']);
Route::post('/api/comparisons/{comparison}/page-markers', [ComparisonController::class, 'applyPageMarkers']);
Route::post('/api/comparisons/{comparison}/pagination/from-xhtml', [ComparisonController::class, 'buildPaginationFromXhtml']);

// Comparisons
Route::get('/comparisons/by-work', [ComparisonController::class, 'getByWork']);
Route::get('/comparisons/{comparison}/details', [ComparisonController::class, 'details']);
Route::delete('/comparisons/{id}', [ComparisonController::class, 'destroy'])->name('comparisons.destroy');
Route::get('/comparisons/{comparison}/export', [ComparisonController::class, 'exportPublishedLegacy'])
    ->middleware('auth')
    ->name('comparisons.export');

// Editor
Route::get('/version/{version}/editor', [EditorController::class, 'versionEditor'])->middleware('auth')->name('version.editor');
Route::put('/version/{version}/editor', [EditorController::class, 'versionUpdate'])->middleware('auth')->name('version.editor.update');
Route::get('/comparison/{comparison}/editor', [EditorController::class, 'comparisonEditor'])->middleware('auth')->name('comparison.editor');
Route::put('/comparison/{comparison}/editor', [EditorController::class, 'comparisonUpdate'])->middleware('auth')->name('comparison.editor.update');

// TEI to XHTML conversion
// Route::post('/api/run_xhtml', [XhtmlController::class, 'run']);
