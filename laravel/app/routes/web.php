<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\WorkController;

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

// status
Route::get('/works/{workId}/status', [WorkController::class, 'getStatus'])->name('works.status.get');
Route::post('/works/{workId}/status', [WorkController::class, 'updateStatus'])->name('works.status.update');

// Description
Route::get('/works/{id}/description', [WorkController::class, 'getDescription'])->name('works.description');
Route::post('/works/{workId}/description', [WorkController::class, 'updateDescription']);

