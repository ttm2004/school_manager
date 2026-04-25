<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdmissionRegistrationController;
use App\Http\Controllers\Api\LocationController;

Route::post('/admissions/register', [AdmissionRegistrationController::class, 'store']);
Route::get('/districts', [App\Http\Controllers\Api\LocationController::class, 'districts']);