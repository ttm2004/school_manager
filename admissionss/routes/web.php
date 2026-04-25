<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\HomeController;

Route::get('/', [HomeController::class, 'index']) -> name('home');