<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XhtmlController;

Route::post('/run_xhtml', [XhtmlController::class, 'run']);
