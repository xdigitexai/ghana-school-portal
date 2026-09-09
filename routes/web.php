<?php

use App\Http\Controllers\SchoolPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['app'=>'Ghana School Portal','status'=>'ok']));

Route::middleware('auth')->prefix('school')->name('school.')->group(function () {
    Route::get('/dashboard', [SchoolPortalController::class,'dashboard']);
    Route::post('/students', [SchoolPortalController::class,'registerStudent']);
    Route::post('/scores', [SchoolPortalController::class,'saveScore']);
    Route::post('/scores/{scoreId}/unlock', [SchoolPortalController::class,'unlockScore']);
    Route::post('/results/publication', [SchoolPortalController::class,'publishResults']);
    Route::get('/students/{studentId}/results', [SchoolPortalController::class,'studentResults']);
    Route::get('/broadsheet', [SchoolPortalController::class,'broadsheet']);
    Route::post('/payments', [SchoolPortalController::class,'recordPayment']);
});
