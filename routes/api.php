<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VerificationController;

Route::middleware(['throttle:api'])->group(function () {
    Route::get('/verification/qrcode', [VerificationController::class, 'qrcode'])->name('api.verification.qrcode');  

    Route::post('/verification/tester', [VerificationController::class, 'tester'])->name('api.verification.tester');  

    Route::middleware('web')->get('/verification/checker', [VerificationController::class, 'checker'])->name('api.verification.checker'); 
});
