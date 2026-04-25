<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdmissionRegistrationController;

Route::post('/admissions/register', [AdmissionRegistrationController::class, 'store']);