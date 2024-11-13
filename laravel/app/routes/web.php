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

Route::get('/api/authors', [AuthorController::class, 'index'])->middleware('auth');
Route::post('/api/authors', [AuthorController::class, 'store'])->middleware('auth');
Route::post('/api/works', [WorkController::class, 'store'])->middleware('auth');
Route::get('/api/author/{authorId}/works', [AuthorController::class, 'getWorksByAuthor'])->middleware('auth');